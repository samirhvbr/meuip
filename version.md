# Versão — MEUIP

**Versão atual:** `0.2.0`

Serviço "qual é o meu IP" da Blue3: mostra ao visitante o IP público, ASN e dados de rede.

> Este arquivo é a **fonte da verdade** da versão do projeto: quem precisar exibir ou
> reportar a versão extrai o **primeiro número semver (`X.Y.Z`)** encontrado aqui.
> Mantenha a linha **"Versão atual"** como a primeira ocorrência de um número.
>
> `0.1.0` marca o início do **versionamento**, não o início do projeto — o que veio
> antes continua no `git log`.

---

## 1. Convenção de Versionamento (`X.Y.Z`)

| Componente | Significado | Como sobe |
|---|---|---|
| **X** | Release estável | Manual |
| **Y** | Mudança estrutural — Nova área do site, refatoração grande, nova integração externa. | Manual |
| **Z** | Incremento a cada entrega (ver gatilhos) | A cada entrega |

### Gatilhos de bump do `Z`

- Criar uma **tela / rota / view** nova.
- Criar **tabela ou migration** nova.
- **Modificar layout** (estrutura visual de uma tela).
- **Renomear** botão, rótulo, rota nomeada ou coluna.
- Alterar **regra de negócio**.
- Alterar **configuração de segurança** (rate limit, throttle, CSRF, headers, auth).

> Correção de texto, comentário e formatação **não** exigem bump.

---

## 2. Formato de Commit Obrigatório

```
X.Y.Z - Descrição curta em português
```

**Regras inegociáveis:**

1. A versão **sempre** vem deste `version.md` — bumpe **no mesmo commit** da mudança.
2. Mensagem em **português**, específica o suficiente para `git log --grep`.
3. **Proibido** Conventional Commits (`feat:`, `fix:`, `chore:`…) e mensagens vagas
   ("ajuste", "update", "wip").
4. Um objetivo por commit.

> **A skill COMMITTER commita por você neste repo** (existe `.committer.yml` na raiz).
> Escreva a entrada de changelog abaixo ao concluir a entrega: é **dali** que a
> mensagem do commit sai, sem custo de modelo. Sem a entrada, a skill cai num
> fallback que gasta tokens e descreve pior do que você. Detalhe no bloco PS do
> `CLAUDE.md`.

---

## 3. Changelog

> Ordem decrescente (mais recente no topo).

### `0.2.0` — 2026-08-24 — Registra o projeto como EM PRODUÇÃO, e a spec visual como aplicada

Sem mudança de código. Esta entrada existe porque o estado real do projeto não estava
registrado em lugar nenhum: a spec `meuip-melhorias-visuais.md` (v2.1) vivia na pasta de
rascunho **já aplicada**, e spec aplicada em pasta de rascunho vira segunda fonte de verdade.

**Aferido em produção (`https://meuip.rs`, 24/08/2026) — os 12 itens da spec, um a um:**

| Item da spec | No ar |
|---|---|
| 1.1 tokens de tema (`--bg`/`--fg`/`--muted`/`--accent`) | ✅ |
| 1.2 anti-flash inline no `<head>` (`localStorage`/`matchMedia`) | ✅ |
| 1.3 toggle de tema | ✅ `<button class="theme-toggle" aria-label="Alternar tema claro/escuro">` |
| 1.4 `theme-color` (mobile) | ✅ |
| 2.1 IP copiável | ✅ `<button class="copy-btn" data-copy="…" aria-label="Copiar endereço IP">` |
| 2.2 badge IPv4/IPv6 | ✅ |
| 2.3 chips de contexto (SVG inline) | ✅ 12 `<svg>` |
| 3. copiar nos comandos `curl` | ✅ `<button class="copy-icon" data-copy="curl meuip.rs">` |
| 4.1 coordenadas → mapa | ✅ |
| 4.2 hostname sem PTR | ✅ |
| 4.3 `:focus-visible` | ✅ no `assets/style.css` |
| `prefers-reduced-motion` | ✅ 3 ocorrências no CSS |

**A restrição permanente também foi aferida** — a API texto-puro responde igual:

```console
$ curl meuip.rs       → 170.233.230.254
$ curl meuip.rs/ip    → 170.233.230.254
$ curl meuip.rs/asn   → AS265198
$ curl meuip.rs/all   → { "ip": "170.233.230.254", … }
```

⚠️ **Cuidado de medição que custou uma conclusão errada:** `curl` na home recebe a **API
texto-puro**, não o HTML — é o propósito do projeto. Auditar a página exige
`User-Agent` de navegador. A primeira leitura, sem isso, deu "nenhum item aplicado".

**Rascunho removido:** `.continue/meuip-melhorias-visuais.md` (191 linhas) saiu do repo —
estava 100% aplicado. O registro permanente é esta entrada.

_Gatilhos:_ registro de estado de produção; remoção de rascunho aplicado.



> Ordem decrescente (mais recente no topo).

### `0.1.0` — 2026-07-30 — Adota o versionamento da casa

Passa a seguir o padrão dos demais repositórios: `version.md` como fonte da verdade,
commits no formato `X.Y.Z - Descrição em português` e changelog como registro de
entrega.

O gatilho foi prático: o repo já participava da skill **COMMITTER**, mas sem
`version.md` não existia o formato da casa — o ciclo reportava e **não commitava**.
Com este arquivo, a skill passa a operar aqui pelo caminho determinístico (sem custo
de modelo), lendo a mensagem da entrada de changelog.

_Gatilhos:_ adoção de infraestrutura de versionamento.
