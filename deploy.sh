#!/bin/bash
# versão 1.0 - 2026-07-01
#
# Deploy idempotente do MEUIP.RS (PHP nativo / Apache2) em /srv/www/meuip.
# Modelado no deploy.sh do SITE Blue3 (BLUE3-SITE-FRONT), adaptado à realidade
# DESTE projeto: PHP puro, SEM Laravel, SEM Composer, SEM npm/build.
#
# EXECUTAR COMO ROOT. O script delega cada operação ao usuário correto:
#   - APP_USER (default: b3sys)    → git (dono dos arquivos e do .git)
#   - WEB_USER (default: www-data) → usuário do Apache/PHP-FPM (lê tudo, grava LOG/)
# Permissões são ajustadas pelo próprio script ao final (root faz chown direto).
#
# - Sai cedo se nada novo no branch.
# - NÃO aplica schema.sql automaticamente: o banco é compartilhado (o admin da
#   INTRANET lê a tabela meuip_visits) e as credenciais vivem em config.php, fora
#   do versionamento. Se schema.sql mudar, o script AVISA para aplicar à mão.
# - NÃO toca em config.php (gitignored) — cada ambiente tem o seu.
# - Recarrega o PHP-FPM ao final para limpar o OPcache (código novo entra em vigor).
# - Lock impede dois deploys simultâneos.
#
# Variáveis OPCIONAIS (lidas de .env no diretório, se existir):
#   DEPLOY_BRANCH               - branch a seguir            (default: master)
#   DEPLOY_APP_USER             - dono dos arquivos/git      (default: b3sys)
#   DEPLOY_WEB_USER             - usuário do servidor web     (default: www-data)
#   DEPLOY_PHP_FPM              - versão do PHP-FPM, ex "8.4" (default: auto-detecta)
#   DEPLOY_APACHE_RELOAD        - 1 = reload Apache após deploy (default: 1)
#   DEPLOY_TELEGRAM_BOT_TOKEN   - token do bot Telegram (opcional)
#   DEPLOY_TELEGRAM_CHAT_ID     - chat id do grupo/canal (opcional)
#
# Uso (sempre como root):
#   bash /srv/www/meuip/deploy.sh
#   DEPLOY_BRANCH=main bash deploy.sh   # forçar outro branch

set -euo pipefail

# ── Root obrigatório ──────────────────────────────────────────────────────────
if [ "$(id -u)" -ne 0 ]; then
    printf '❌ Este script deve ser executado como root.\n' >&2
    printf '   Uso: bash %s\n' "${BASH_SOURCE[0]}" >&2
    exit 1
fi

# ── Config ────────────────────────────────────────────────────────────────────
DIR="/srv/www/meuip"
LOCK="${DIR}/.deploy.lock"
DEFAULT_APP_USER="b3sys"       # dono dos arquivos: roda git
DEFAULT_WEB_USER="www-data"    # usuário do Apache2/PHP-FPM (Debian usa www-data)

# ── Helpers ───────────────────────────────────────────────────────────────────
log() { printf '[%(%H:%M:%S)T] %s\n' -1 "$*"; }

# Lê uma chave do .env sem usar source (evita quebra com quoting). No-op se
# não houver .env (MEUIP normalmente usa config.php / getenv, não .env).
get_env() {
    local key=$1
    [ -f .env ] || return 0
    { grep -E "^${key}=" .env 2>/dev/null || true; } | head -1 | cut -d= -f2- | sed 's/^"//;s/"$//'
}

# Executa como o dono dos arquivos (git). -H garante HOME correto (chaves SSH, gitconfig).
as_app() { sudo -u "$APP_USER" -H "$@"; }

# Notificação Telegram (silenciosa se não configurado).
notify() {
    [ -n "${TG_BOT:-}" ] && [ -n "${TG_CHAT:-}" ] || return 0
    curl -fsS -m 5 -X POST "https://api.telegram.org/bot${TG_BOT}/sendMessage" \
        --data-urlencode "chat_id=${TG_CHAT}" \
        --data-urlencode "text=$1" \
        --data-urlencode "parse_mode=HTML" \
        >/dev/null 2>&1 || log "(notify Telegram falhou; ignorando)"
}

fail() {
    log "❌ $*"
    notify "❌ <b>Deploy MEUIP falhou</b>: $* em <code>$(as_app git rev-parse --short HEAD 2>/dev/null || echo '?')</code>"
    exit 1
}

# ── Lock ──────────────────────────────────────────────────────────────────────
exec 9>"$LOCK"
flock -n 9 || { printf '❌ outro deploy já está rodando (lock: %s)\n' "$LOCK" >&2; exit 1; }

cd "$DIR" || { printf '❌ %s não existe\n' "$DIR" >&2; exit 1; }

# ── Lê variáveis do .env (se existir) ─────────────────────────────────────────
BRANCH="${DEPLOY_BRANCH:-$(get_env DEPLOY_BRANCH)}"
BRANCH="${BRANCH:-master}"
APP_USER="$(get_env DEPLOY_APP_USER)"
APP_USER="${APP_USER:-$DEFAULT_APP_USER}"
WEB_USER="$(get_env DEPLOY_WEB_USER)"
WEB_USER="${WEB_USER:-$DEFAULT_WEB_USER}"
PHP_FPM="$(get_env DEPLOY_PHP_FPM)"
APACHE_RELOAD="$(get_env DEPLOY_APACHE_RELOAD)"
APACHE_RELOAD="${APACHE_RELOAD:-1}"
TG_BOT="$(get_env DEPLOY_TELEGRAM_BOT_TOKEN)"
TG_CHAT="$(get_env DEPLOY_TELEGRAM_CHAT_ID)"

# Valida usuários antes de qualquer operação
id "$APP_USER" &>/dev/null || { printf '❌ usuário APP_USER "%s" não existe\n' "$APP_USER" >&2; exit 1; }
id "$WEB_USER" &>/dev/null || { printf '❌ usuário WEB_USER "%s" não existe\n' "$WEB_USER" >&2; exit 1; }

log "=== Deploy MEUIP.RS — dir: $DIR | app: $APP_USER | web: $WEB_USER | branch: $BRANCH ==="

# config.php é gitignored; sem ele (e sem env vars MEUIP_*) o app não conecta ao banco.
[ -f config.php ] || log "⚠️  config.php ausente — confie nas variáveis MEUIP_* do ambiente, ou copie config.example.php."

# ── 0. Neutraliza o efeito do chmod (passo 3) aos olhos do git ────────────────
# Sem isso, o chmod recursivo deixa "mode-only diffs" no working tree que
# quebram o `git merge --ff-only` no deploy seguinte. Idempotente.
as_app git config core.fileMode false 2>/dev/null || true

# ── 1. Fetch e checagem de mudanças (como APP_USER, dono do .git) ─────────────
log "==> [1/5] Buscando alterações no branch $BRANCH..."
as_app git fetch --quiet origin "$BRANCH" || fail "git fetch falhou"

LOCAL=$(as_app git rev-parse HEAD)
REMOTE=$(as_app git rev-parse "origin/$BRANCH")

if [ "$LOCAL" = "$REMOTE" ]; then
    log "✓ Nada novo em origin/$BRANCH. Saindo."
    exit 0
fi

if ! as_app git diff --quiet || ! as_app git diff --cached --quiet; then
    log "⚠️  Working tree tem mudanças locais não commitadas — continuando."
fi

log "==> Trazendo $(as_app git rev-parse --short "$LOCAL") → $(as_app git rev-parse --short "$REMOTE")..."
as_app git merge --ff-only "origin/$BRANCH" \
    || fail "fast-forward falhou (divergiu? resolva manual e rode de novo)"

# Garantia pós-merge: o working tree TEM que estar em origin/BRANCH; se não bater,
# aborta alto em vez de servir código velho.
HEAD_NOW=$(as_app git rev-parse HEAD)
if [ "$HEAD_NOW" != "$REMOTE" ]; then
    fail "HEAD ($HEAD_NOW) != origin/$BRANCH ($REMOTE) após o merge — não vou deployar código velho"
fi

CHANGED=$(as_app git diff --name-only "$LOCAL" "$REMOTE")

# ── 2. schema.sql — só AVISA (aplicação é manual) ─────────────────────────────
# Não há runner de migrations; o banco é compartilhado (INTRANET lê meuip_visits)
# e as credenciais estão em config.php (fora do versionamento). Aplicar à mão:
#   mysql -h <host> -u <user> -p <db> < schema.sql
if echo "$CHANGED" | grep -q '^schema\.sql$'; then
    log "⚠️  [2/5] schema.sql MUDOU neste deploy — aplique manualmente no banco:"
    log "         mysql -h <host> -u <user> -p <db> < $DIR/schema.sql"
    notify "⚠️  <b>MEUIP</b>: schema.sql mudou — aplicar migração manualmente no banco."
else
    log "==> [2/5] schema.sql inalterado — nada a aplicar."
fi

# ── 3. Permissões (root faz chown direto) ─────────────────────────────────────
# Dono: APP_USER (git/escrita) | Grupo: WEB_USER (Apache lê tudo, grava em LOG/)
log "==> [3/5] Ajustando permissões ($APP_USER:$WEB_USER)..."
chown -R "$APP_USER":"$WEB_USER" .

# Arquivos: 644 | Diretórios: 755 (exceto .git/ e LOG/, tratados à parte)
find . -not -path './.git/*' -not -path './LOG/*' -type f -exec chmod 644 {} + 2>/dev/null || true
find . -not -path './.git/*' -type d -exec chmod 755 {} + 2>/dev/null || true

# LOG/ precisa ser gravável pelo WEB_USER; setgid (2775) mantém o grupo www-data
# nos arquivos que o PHP criar lá.
if [ -d LOG ]; then
    chmod 2775 LOG/ 2>/dev/null || true
    find LOG/ -type f -not -name '.gitkeep' -exec chmod 664 {} + 2>/dev/null || true
fi

# .git/ deve pertencer ao APP_USER (git roda como ele).
chown -R "$APP_USER":"$APP_USER" .git 2>/dev/null || true

# Protege config.php (dono lê/escreve, grupo www-data só lê, outros nada).
[ -f config.php ] && chmod 640 config.php 2>/dev/null || true
[ -f .env ] && chmod 640 .env 2>/dev/null || true

# ── 4. Recarrega PHP-FPM (limpa OPcache) + Apache ─────────────────────────────
log "==> [4/5] Recarregando serviços..."
# Versão do PHP-FPM: usa DEPLOY_PHP_FPM se definido; senão detecta pela CLI.
if [ -z "$PHP_FPM" ]; then
    PHP_FPM="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || true)"
fi
if [ -n "$PHP_FPM" ]; then
    FPM_SERVICE="php${PHP_FPM}-fpm"
    if systemctl is-active --quiet "$FPM_SERVICE" 2>/dev/null; then
        systemctl reload "$FPM_SERVICE" \
            && log "✓ $FPM_SERVICE recarregado (OPcache limpo)" \
            || log "⚠️  reload do $FPM_SERVICE falhou — OPcache pode servir código antigo"
    else
        log "⚠️  $FPM_SERVICE não está ativo — pulando reload (OPcache NÃO limpo)"
    fi
else
    log "⚠️  não detectei a versão do PHP-FPM — OPcache NÃO foi limpo (defina DEPLOY_PHP_FPM)"
fi

if [ "$APACHE_RELOAD" = "1" ]; then
    if systemctl is-active --quiet apache2 2>/dev/null; then
        systemctl reload apache2 \
            && log "✓ Apache recarregado" \
            || log "⚠️  reload do Apache falhou"
    else
        log "⚠️  Apache não está ativo — pulando reload"
    fi
fi

# ── 5. Relatório final ────────────────────────────────────────────────────────
log "==> [5/5] Concluído."

SHORT_SHA=$(as_app git rev-parse --short HEAD)
SUBJECT=$(as_app git log -1 --pretty=%s | head -c 80)

CHANGED_COUNT=$(echo "$CHANGED" | grep -c . || true)
log "    Arquivos alterados: $CHANGED_COUNT"
if [ "$CHANGED_COUNT" -le 20 ]; then
    echo "$CHANGED" | while read -r f; do [ -n "$f" ] && log "    ✦ $f"; done
fi

log "✅ Deploy MEUIP concluído: $SHORT_SHA — $SUBJECT"
notify "✅ <b>Deploy MEUIP concluído</b>: <code>$SHORT_SHA</code> — $SUBJECT"
