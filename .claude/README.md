# Blue3 Intranet Mobile (`blue3-intranet-mobile`)

> Projeto interno da Blue3. Sistema com **três peças**: API REST + área admin web (Laravel) + app mobile (Flutter). Documentação interna — não publicar.

**Ver também:** [AGENTS.md](AGENTS.md) / [CLAUDE.md](CLAUDE.md) (convenções de código e do agente) · [docs/README.md](docs/README.md) (índice da documentação técnica) · [flutter/](flutter/) (cliente Flutter).

---

## Sumário

1. [O que é](#o-que-é)
2. [Estrutura do repositório](#estrutura-do-repositório)
3. [Stack](#stack)
4. [Ambientes](#ambientes)
5. [Versão pública (`version.md`)](#versão-pública-versionmd)
6. [Setup rápido (DEV)](#setup-rápido-dev)
7. [Deploy DEV → Produção](#deploy-dev--produção)
8. [Agendamentos (Scheduler)](#agendamentos-scheduler)
9. [Integrações](#integrações)
10. [Diferenças em relação ao Blue3 Sys](#diferenças-em-relação-ao-blue3-sys)

---

## O que é

Sistema interno da Blue3 com três frentes que compartilham o mesmo backend Laravel:

- **API REST** (`/api/v1/...`) — consumida pelo app Flutter dos funcionários de campo e por integrações futuras. Autenticação via Sanctum (tokens pessoais).
- **Admin web** (`/`) — telas Blade + Cuba Premium Theme para operação no PC/notebook. Login só de funcionários, sessão estendida no IP interno.
- **App Flutter** (`mobile/app/`) — cliente mobile para Android (e iOS futuramente) consumindo a API. Distribuição interna (sem stores na v1).

Funcionalidades-alvo da v1 (lista viva, ajustar conforme implementação):

- Tela de login (somente funcionários — `App\Models\User` da intranet).
- Sessão estendida no IP interno `170.233.231.251` (login não expira lá dentro).
- Dashboard com indicadores operacionais (definir cards conforme módulos forem entrando).
- Cadastro rápido de leads comerciais (espelha `com_leads` do sys, mas pode evoluir).
- Planilha de renovação de domínios/DNS (tabela `domains` com lembretes).
- Sistema de teste por ping (jobs agendados, ver `docs/PING_MONITOR.md` quando existir).
- App mobile para uso em campo (leads, pings, consultas rápidas — definir tela a tela).

> Funcionalidades novas viram doc em `/docs/` (regra do projeto — sem doc, sem deploy).

---

## Estrutura do repositório

Monorepo: Laravel em `backend/`, cliente Flutter dentro de `mobile/app/`.

```
blue3-intranet-mobile/
├── app/                       # Código Laravel (controllers, services, models)
├── bootstrap/
├── config/
├── database/
│   ├── migrations/
│   └── seeders/
├── docs/                      # Documentação técnica do projeto
│   ├── README.md              # Índice de docs/
│   ├── INSTALACAO.md          # Setup limpo (Debian Trixie)
│   ├── ARQUITETURA.md         # Visão geral Laravel + Flutter
│   ├── API_AUTH.md            # Sanctum, tokens, IP whitelist interno
│   ├── FLUTTER_SETUP.md       # Setup do projeto Flutter
│   ├── VERSIONAMENTO.md       # version.md, build/release do app
│   └── DESIGN_RESPONSIVO.md   # 1366x768, dark mode, mobile-first
├── flutter/                   # Projeto Flutter (Dart)
│   └── README.md              # Setup específico do cliente Flutter
├── public/
├── resources/
│   ├── views/                 # Blade (admin web)
│   └── ...
├── routes/
│   ├── web.php                # Rotas admin web (login, dashboard)
│   ├── api.php                # Rotas API consumidas pelo Flutter
│   └── console.php            # Scheduler
├── storage/
├── tests/
├── .claude/
│   └── settings.json          # Configuração do agente Claude Code
├── .editorconfig
├── .env.example               # Template das variáveis de ambiente
├── .gitignore
├── AGENTS.md                  # = CLAUDE.md (espelhado p/ outras ferramentas)
├── CLAUDE.md                  # Convenções do agente
├── README.md                  # Este arquivo
├── composer.json
└── version.md                 # Versão pública (X.Y.Z)
```

> **Por que monorepo?** Mantém Laravel e Flutter versionados juntos: assim a quebra de contrato da API e a atualização do cliente saem no mesmo commit/PR, eliminando "qual versão do app fala com qual versão da API". Se em algum momento o app crescer demais ou virar repositório público (loja), separa.

---

## Stack

- **Laravel 13.x** sobre **PHP 8.4** (PHP-FPM)
- **Apache 2.4** (Debian Trixie)
- **MariaDB** (sem SQLite, sem PostgreSQL)
- **Cuba Premium Theme** + Bootstrap 5 (admin web)
- **Sanctum** — tokens pessoais para o app mobile
- **Reverb** (opcional, ativar se houver realtime) — WebSocket nativo Laravel
- **Flutter 3.x** + **Dart 3.x** (cliente mobile)
- **Riverpod** — gerenciamento de estado do Flutter
- **Dio** — cliente HTTP do Flutter (interceptor de token, retry, refresh)
- **flutter_secure_storage** — armazenamento criptografado do token no device
- **Ollama** — opcional, mesma stack de IA do Blue3 Sys (se o app vier a usar)

---

## Ambientes

| Ambiente | URL Admin Web              | Caminho             | Banco             | API Base                              |
|----------|----------------------------|---------------------|-------------------|---------------------------------------|
| DEV      | mdev.blue3.cloud           | /srv/www/mobile-dev | blue3_mobile_dev  | https://mdev.blue3.cloud/api/v1       |
| Produção | m.blue3.cloud              | /srv/www/mobile     | blue3_mobile      | https://m.blue3.cloud/api/v1          |

> **Subdomínios sugeridos** — ajustar no DNS Blue3 conforme aprovado. Manter `sys.blue3.cloud` e `adm.blue3.com.br` reservados ao projeto sys atual.

---

## Versão pública (`version.md`)

`version.md` guarda a versão exibida pelo sistema (admin web e app), no formato `X.Y.Z`:

- **X** — versão estável final (alteração manual).
- **Y** — mudança estrutural (alteração manual).
- **Z** — incremento automático: nova tela (web ou Flutter), nova tabela no banco, mudança de layout ou renomear botão → `Z+1`.

> Detalhes em [docs/VERSIONAMENTO.md](docs/VERSIONAMENTO.md). O app Flutter lê esse mesmo `version.md` no build para mostrar a versão no rodapé do drawer e enviar como header `X-Client-Version` em toda request da API.

---

## Setup rápido (DEV)

Detalhes completos em [docs/INSTALACAO.md](docs/INSTALACAO.md). Resumo:

```bash
# 1. Clonar o repo
cd /srv/www
git clone <repo> mobile-dev && cd mobile-dev

# 2. Backend Laravel
composer install
cp .env.example .env
php artisan key:generate
# Editar .env (DB_*, APP_URL, INTERNAL_SESSION_IPS, etc.)
php artisan migrate
php artisan storage:link
npm install && npm run build

# 3. Permissões (rodar como root no Debian)
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# 4. Cliente Flutter
cd flutter
flutter pub get
# Configurar lib/config/env.dart apontando pra https://mdev.blue3.cloud/api/v1
flutter run -d <device>
```

---

## Deploy DEV → Produção

Mesmo modelo do `blue3-sys`: deploy via interface web em **Database → Deploy DEV para Produção** (perfis com `admin.sensitive.database`). A implementação reaproveita o `App\Services\ProductionDeploymentService` com adaptações:

- Origem: `/srv/www/mobile-dev`
- Destino: `/srv/www/mobile`
- Backup em `/srv/backups/laravel/mobile_backup_YYYYMMDD_HHMMSS`

### Lista canônica de `--exclude` do rsync

```
.env, .env.dev, .env.example
storage/, bootstrap/cache/
.git/, vendor/
.continue/, .claude/, tmp/
README.md, AGENTS.md, CLAUDE.md, CLAUDE.local.md
.vscode/, .editorconfig
flutter/                    # Flutter não vai pra produção do servidor web
```

> O Flutter compilado (APK/AAB) **não é entregue pelo Apache** — ele é distribuído por outro caminho (Firebase App Distribution, link interno, ou upload manual). Por isso a pasta `mobile/app/` inteira está no `--exclude`. Ver [docs/VERSIONAMENTO.md](docs/VERSIONAMENTO.md) §Distribuição do APK.

### Requisito: sudoers

Mesmas regras do sys (`/etc/sudoers.d/b3sys-deploy`), só ajustar o caminho. Ver `README.md` do sys para o conteúdo completo. Em resumo: `www-data` e o usuário SSH precisam de `NOPASSWD` para `rsync`, `php`, `composer`, `chmod`, `chown`, `find`.

### Deploy manual (emergência)

Ver bloco equivalente em [docs/INSTALACAO.md](docs/INSTALACAO.md) §Deploy manual. Em resumo: rsync com os excludes acima, `composer install --no-dev`, `artisan migrate --force`, ajuste de owner/permissões, `artisan up`.

---

## Agendamentos (Scheduler)

Cron do servidor:

```cron
* * * * * cd /srv/www/mobile && php artisan schedule:run >> /dev/null 2>&1
```

A tabela de comandos agendados deve viver aqui conforme módulos entrarem. Template:

| Comando                         | Frequência                  | Tabela(s) local(is)  | Descrição                                     |
|---------------------------------|-----------------------------|----------------------|-----------------------------------------------|
| `domains:check-expiration`      | Diário 07h                  | `domains`            | Lembrete de domínios expirando em 30/15/7 dias |
| `ping:run-checks`               | A cada minuto               | `ping_checks`, `ping_results` | Executa checks de ping e grava status   |
| `mobile:expire-stale-tokens`    | Diário 03h                  | `personal_access_tokens` | Revoga tokens sem uso há >90 dias         |

> Documentar cada comando novo em `/docs/<MODULO>.md` (regra "sem doc, sem deploy").

---

## Integrações

| Serviço      | Variável `.env`         | Documentação                                |
|--------------|-------------------------|---------------------------------------------|
| IXC (ERP)    | `IXC_BASE_URL`          | https://wikiapiprovedor.ixcsoft.com.br/     |
| FCM (push)   | `FCM_PROJECT_ID`        | https://firebase.google.com/docs/cloud-messaging |
| Ollama       | `OLLAMA_BASE_URL`       | https://github.com/ollama/ollama/blob/main/docs/api.md |

> O sys já tem clientes para IXC, PHPIPAM, Shodan, Brasil CEP — **avaliar antes de duplicar**. Se o módulo for compartilhável, é melhor expor via API do sys e consumir desse projeto do que reimplementar.

---

## Diferenças em relação ao Blue3 Sys

Ambos os projetos seguem as mesmas convenções (mesmo agente, mesma stack base), mas têm escopo distinto:

| Tópico             | Blue3 Sys (`sys.blue3.cloud`)                | Blue3 Intranet Mobile (este)                    |
|--------------------|----------------------------------------------|--------------------------------------------------|
| Foco               | Sistema interno completo de gestão           | API + admin enxuto + cliente Flutter             |
| Frontend principal | Blade + Cuba (operação no PC)                | Blade + Cuba + **Flutter** (operação em campo)   |
| Autenticação API   | Sanctum opcional (módulo financeiro)         | Sanctum **central** (todo o app depende)         |
| Realtime           | Reverb (chat OPA, alertas)                   | Reverb opcional (push via FCM é o canal padrão)  |
| Push notifications | Não usa                                      | FCM (Firebase Cloud Messaging)                   |
| Banco              | `blue3_sys` (compartilhado entre módulos)    | `blue3_mobile` (próprio, sem mistura com sys)    |
| Integrações        | IXC, PHPIPAM, Shodan, OPA, Site Blue3, etc.  | Inicialmente nenhuma — adicionar sob demanda     |

**Princípio:** este projeto **não compete** com o sys, ele complementa. O que já existe e funciona bem no sys (auditoria, deploy, scheduler) é replicado aqui em forma minimalista; o que é específico de mobile (FCM, Flutter, IP interno como condição de sessão longa, headers `X-Client-*`) vive aqui sem poluir o sys.

---

**Em dúvida sobre alguma decisão?** Consulta primeiro [CLAUDE.md](CLAUDE.md). Se a regra não estiver lá, abre uma issue/conversa antes de codar.
