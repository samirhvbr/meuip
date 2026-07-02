# meuip.rs — Melhorias visuais v2.1

Spec de implementação das melhorias de UX/visual do site. O projeto é PHP puro com front controller (`index.php` → `src/` → `views/`), servido pelo Apache em `/srv/www/meuip`. Nenhum framework, nenhum build step.

## Objetivo

Deixar o hero mais funcional e o site confortável para o público técnico: IP copiável em um clique, dados técnicos em fonte mono, badge IPv4/IPv6, dark mode com toggle, e pequenos refinamentos (link de mapa nas coordenadas, tratamento de hostname vazio).

## Restrições (não negociáveis)

1. **Não quebrar a API texto-puro.** `curl meuip.rs`, `/ip`, `/asn`, `/isp`, `/country`, `/region`, `/coord`, `/all` devem continuar retornando exatamente o mesmo output de hoje. Todas as mudanças desta spec são exclusivamente na view HTML (detecção de User-Agent/Accept que já existe permanece intocada).
2. **Zero dependências externas.** Sem CDN, sem font de ícones, sem framework JS. Ícones são SVG inline (fornecidos abaixo). O site precisa continuar leve e carregar instantâneo.
3. **Sem flash de tema errado (FOUC).** O script anti-flash roda inline no `<head>` antes de qualquer CSS.
4. **Acessibilidade mínima:** `aria-label` em todo botão só-ícone, `:focus-visible` visível nos dois temas, contraste AA, `prefers-reduced-motion` respeitado.

---

## 1. Dark mode

### 1.1 Tokens de tema

Migrar todas as cores do CSS para custom properties em `:root`, com override via `[data-theme="dark"]` no `<html>`.

| Token | Light | Dark | Uso |
|---|---|---|---|
| `--bg` | `#f4f6f8` | `#10151b` | fundo da página |
| `--card` | `#ffffff` | `#1a212b` | cards, chips, botões |
| `--text` | `#111827` | `#e7eaee` | texto principal |
| `--muted` | `#6b7280` | `#94a3b8` | labels, texto secundário |
| `--border` | `#e2e6ea` | `#2a3441` | bordas e divisores |
| `--accent` | `#1668dc` | `#5ba3f5` | azul da marca, IP, links |
| `--accent-bg` | `#e8f1fd` | `#16283e` | fundo de badge/destaque |
| `--code-bg` | `#0f172a` | `#0b0f14` | badges dos comandos curl |

> Se o azul atual do site for outro hex, manter o existente como `--accent` no light e derivar o dark clareando (~30% mais claro) para manter contraste sobre fundo escuro.

```css
:root {
  --bg: #f4f6f8; --card: #ffffff; --text: #111827; --muted: #6b7280;
  --border: #e2e6ea; --accent: #1668dc; --accent-bg: #e8f1fd; --code-bg: #0f172a;
}
[data-theme="dark"] {
  --bg: #10151b; --card: #1a212b; --text: #e7eaee; --muted: #94a3b8;
  --border: #2a3441; --accent: #5ba3f5; --accent-bg: #16283e; --code-bg: #0b0f14;
}
body { background: var(--bg); color: var(--text); transition: background .25s, color .25s; }
@media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
```

Varrer o CSS existente e substituir todo hex hardcoded pelo token equivalente (cards "Sua conexão" e "Documentação (curl)" inclusos).

### 1.2 Anti-flash (obrigatório)

Inline no `<head>`, **antes** do `<link>` do CSS:

```html
<script>
(function () {
  var t = localStorage.getItem('theme');
  if (t !== 'light' && t !== 'dark') {
    t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }
  document.documentElement.dataset.theme = t;
})();
</script>
```

### 1.3 Toggle

Botão no header (canto direito da nav), só-ícone: lua no tema claro, sol no escuro.

```js
function toggleTheme() {
  var html = document.documentElement;
  var next = html.dataset.theme === 'dark' ? 'light' : 'dark';
  html.dataset.theme = next;
  localStorage.setItem('theme', next);
  updateThemeUi(next); // troca ícone sol/lua e atualiza <meta name="theme-color">
}
```

Comportamento: sem preferência salva, segue o sistema (`prefers-color-scheme`); depois do primeiro clique, a escolha manual persiste em `localStorage` e vence o sistema.

### 1.4 theme-color (mobile)

`<meta name="theme-color" id="meta-theme" content="#f4f6f8">` no `<head>`; o `updateThemeUi()` troca o `content` para `#10151b` no dark.

---

## 2. Hero

### 2.1 IP copiável

- IP em fonte mono: `font-family: ui-monospace, 'Cascadia Code', 'JetBrains Mono', Menlo, monospace;` — manter o tamanho grande atual, `letter-spacing: -1px`, cor `var(--accent)`.
- Botão "Copiar" ao lado do IP (borda `var(--border)`, fundo `var(--card)`, ícone + texto). Ao clicar: copia, troca ícone por check verde e texto por "Copiado!" por 1,5s, depois reverte.

```js
async function copyText(btn, text) {
  try { await navigator.clipboard.writeText(text); }
  catch (e) { /* clipboard indisponível: segue com o feedback mesmo assim */ }
  var old = btn.innerHTML;
  btn.innerHTML = ICON_CHECK + (btn.dataset.label ? ' Copiado!' : '');
  btn.classList.add('ok');
  setTimeout(function () { btn.innerHTML = old; btn.classList.remove('ok'); }, 1500);
}
```

`.ok { color: #22a06b; border-color: #22a06b; }` (funciona nos dois temas).

### 2.2 Badge IPv4/IPv6

No PHP da view:

```php
$ipVersion = str_contains($ip, ':') ? 'IPv6' : 'IPv4';
```

Renderizar como chip destacado ao lado do IP ou na linha de chips: fundo `var(--accent-bg)`, texto `var(--accent)`, `border-radius: 999px`, peso 500.

### 2.3 Chips de contexto

Linha de chips centralizada logo abaixo do IP (pill: fundo `var(--card)`, borda `var(--border)`, texto `var(--muted)`, `border-radius: 999px`, `padding: 5px 12px`, `font-size: 13px`):

1. Badge `IPv4`/`IPv6` (item 2.2)
2. Ícone pin + `Porto Alegre, RS · BR` (cidade, região abreviada quando possível, país)
3. Ícone antena + `AS265198 · Blue3` (ASN + nome curto do provedor)

Os dados já existem na página (card "Sua conexão") — os chips são um resumo rápido, o card detalhado permanece como está.

### 2.4 Label do hero

Trocar `SEU ENDEREÇO IP` (all caps no HTML) por `Seu endereço IP` no HTML com `text-transform: uppercase` no CSS — mesmo visual, HTML semântico e melhor para leitores de tela.

---

## 3. Copiar nos comandos curl

No card "Documentação (curl)", cada badge de comando ganha um botãozinho só-ícone de copiar à direita (`aria-label="Copiar comando"`), reusando `copyText(btn, 'curl meuip.rs/asn')` etc. Feedback: ícone vira check verde por 1,5s (sem texto).

---

## 4. Refinamentos

1. **Coordenadas → mapa:** transformar o valor de coordenadas em link externo (nova aba, `rel="noopener"`):
   ```php
   $osmUrl = sprintf('https://www.openstreetmap.org/?mlat=%s&mlon=%s#map=12', $lat, $lon);
   ```
2. **Hostname sem PTR:** quando o reverse lookup vier vazio, renderizar `<span style="color:var(--muted)">sem PTR</span>` em vez de `—`.
3. **Foco visível:** `:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }` global.

---

## Ícones (SVG inline, 24×24, stroke)

Usar exatamente estes (derivados do Tabler, MIT), com `width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"`:

```html
<!-- copiar -->
<svg viewBox="0 0 24 24"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/></svg>
<!-- check -->
<svg viewBox="0 0 24 24"><path d="M5 12l5 5L20 7"/></svg>
<!-- lua (ativa dark) -->
<svg viewBox="0 0 24 24"><path d="M12 3c.13 0 .26 0 .39.01A7.5 7.5 0 0 0 20.31 15.45 9 9 0 1 1 12 3z"/></svg>
<!-- sol (ativa light) -->
<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M3 12h1m8-9v1m8 8h1m-9 8v1M5.6 5.6l.7.7m12.1-.7l-.7.7m0 12.1l.7.7m-12.1-.7l-.7.7"/></svg>
```

Manter os SVGs como constantes JS (`ICON_COPY`, `ICON_CHECK`, `ICON_SUN`, `ICON_MOON`) ou partials PHP — o que encaixar melhor na estrutura atual de `views/`.

---

## Fora de escopo

- Qualquer mudança nos endpoints da API ou na detecção de curl/CLI
- Frameworks, bundlers, fontes externas (Google Fonts etc.)
- Analytics, cookies (o toggle usa só `localStorage`)
- Redesign do layout dos cards — apenas tokenização de cores

## Critérios de aceite

- [ ] `curl meuip.rs` e `curl meuip.rs/all` retornam byte a byte o mesmo output de antes
- [ ] Página abre no tema do sistema sem flash de cor errada (testar hard refresh nos dois temas do SO)
- [ ] Toggle alterna e persiste após reload; escolha manual vence a preferência do sistema
- [ ] Clicar no botão do IP copia o IP e mostra "Copiado!" com check por ~1,5s
- [ ] Cada comando curl da documentação tem botão de copiar funcional
- [ ] Badge mostra IPv4 para IPs v4 e IPv6 para v6 (testar acessando pelo endereço v6, se disponível)
- [ ] Coordenadas abrem o OpenStreetMap no ponto correto em nova aba
- [ ] Hostname vazio exibe "sem PTR" em texto muted
- [ ] Contraste AA nos dois temas (checar IP azul sobre fundo escuro e texto muted)
- [ ] Navegação por teclado: foco visível em toggle e botões de copiar nos dois temas
- [ ] Nenhum request externo novo no DevTools → Network
