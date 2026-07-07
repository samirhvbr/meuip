<?php
/** MEUIP.RS — layout base (head + topbar com menu discreto + footer). */
if (!defined('MEUIP_BOOTED')) {
    exit;
}
$title   = $title   ?? 'BLUE3 — meuip.rs';
$version = $version ?? '2.0';
$navItems = [
    '/ip'      => 'IP',
    '/asn'     => 'ASN',
    '/isp'     => 'ISP',
    '/country' => 'País',
    '/region'  => 'Região',
    '/coord'   => 'Coord',
    '/all'     => 'Todos',
    '/trace'   => 'Trace',
    '/doc'     => 'Docs',
];

// Ícones SVG inline (Tabler, MIT) — zero dependência externa. Reusados pelas
// views (via $cmd e chips). O check é constante no app.js (feedback do copiar).
$svgAttrs  = 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
$ICON_COPY = '<svg viewBox="0 0 24 24" width="18" height="18" ' . $svgAttrs . '><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/></svg>';
$ICON_MOON = '<svg class="i-moon" viewBox="0 0 24 24" width="18" height="18" ' . $svgAttrs . '><path d="M12 3c.13 0 .26 0 .39.01A7.5 7.5 0 0 0 20.31 15.45 9 9 0 1 1 12 3z"/></svg>';
$ICON_SUN  = '<svg class="i-sun" viewBox="0 0 24 24" width="18" height="18" ' . $svgAttrs . '><circle cx="12" cy="12" r="4"/><path d="M3 12h1m8-9v1m8 8h1m-9 8v1M5.6 5.6l.7.7m12.1-.7l-.7.7m0 12.1l.7.7m-12.1-.7l-.7.7"/></svg>';
$ICON_PIN  = '<svg viewBox="0 0 24 24" width="14" height="14" ' . $svgAttrs . '><path d="M9 11a3 3 0 1 0 6 0a3 3 0 0 0-6 0"/><path d="M17.657 16.657 13.414 20.9a2 2 0 0 1-2.827 0l-4.244-4.243a8 8 0 1 1 11.314 0z"/></svg>';
$ICON_NET  = '<svg viewBox="0 0 24 24" width="14" height="14" ' . $svgAttrs . '><path d="M18.364 19.364a9 9 0 1 0-12.728 0"/><path d="M15.536 16.536a5 5 0 1 0-7.072 0"/><circle cx="12" cy="12" r="1"/></svg>';

// Badge de comando curl com botão copiar à direita. O texto copiado vem do
// próprio data-copy (fonte única), escapado com e() nos dois usos.
$cmd = function (string $command) use ($ICON_COPY): string {
    $c = e($command);

    return '<span class="cmd"><code>' . $c . '</code>'
        . '<button type="button" class="copy-icon" data-copy="' . $c . '" aria-label="Copiar comando">' . $ICON_COPY . '</button></span>';
};
?><!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    <meta name="description" content="Descubra seu IP público, ASN, provedor, país e região. Serviço da BLUE3 — Internet corporativa em Porto Alegre/RS.">
    <meta name="author" content="BLUE3">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/png" href="/favicon/favicon-32x32.png" sizes="32x32">
    <link rel="icon" type="image/png" href="/favicon/favicon-16x16.png" sizes="16x16">
    <link rel="apple-touch-icon" sizes="152x152" href="/favicon/apple-touch-icon-152x152.png">

    <!-- theme-color do chrome do mobile; o app.js troca no dark -->
    <meta name="theme-color" id="meta-theme" content="#f5f7fa">
    <!-- Anti-flash de tema: roda ANTES do CSS. Sem escolha salva, segue o SO. -->
    <script>
    (function () {
        var t;
        try { t = localStorage.getItem('theme'); } catch (e) {}
        if (t !== 'light' && t !== 'dark') {
            t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        document.documentElement.dataset.theme = t;
    })();
    </script>
    <?php
    // Cache-busting: o .htaccess cacheia assets por 7 dias no browser; o ?v=
    // (mtime do arquivo) força baixar a versão nova a cada deploy.
    $asset = static function (string $p): string {
        $t = @filemtime(MEUIP_ROOT . $p);
        return $p . ($t ? '?v=' . $t : '');
    };
    ?>
    <link rel="stylesheet" href="<?= e($asset('/assets/style.css')) ?>">
    <script src="<?= e($asset('/assets/app.js')) ?>" defer></script>
    <?php $matomo = meuip_config('matomo') ?? [];
    if (!empty($matomo['enabled']) && !empty($matomo['site_id'])):
        $matomoUrl = rtrim((string) ($matomo['url'] ?? 'https://a.blue3.cloud/'), '/') . '/';
        $matomoSiteId = (string) $matomo['site_id'];
        $matomoCookieDomain = (string) ($matomo['cookie_domain'] ?? '');
    ?>
    <!-- Matomo (self-hosted a.blue3.cloud) -->
    <script>
      var _paq = window._paq = window._paq || [];
      <?php if ($matomoCookieDomain !== ''): ?>
      _paq.push(["setCookieDomain", "<?= e($matomoCookieDomain) ?>"]);
      <?php endif; ?>
      _paq.push(['trackPageView']);
      _paq.push(['enableLinkTracking']);
      (function() {
        var u="<?= e($matomoUrl) ?>";
        _paq.push(['setTrackerUrl', u+'matomo.php']);
        _paq.push(['setSiteId', '<?= e($matomoSiteId) ?>']);
        var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
        g.async=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);
      })();
    </script>
    <noscript><p><img referrerpolicy="no-referrer-when-downgrade" src="<?= e($matomoUrl) ?>matomo.php?idsite=<?= e($matomoSiteId) ?>&amp;rec=1" style="border:0;" alt="" /></p></noscript>
    <!-- End Matomo -->
    <?php endif; ?>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="/"><span class="dot"></span>meuip<span>.rs</span></a>
            <nav class="nav" aria-label="Recursos">
                <?php $first = true; foreach ($navItems as $href => $label): ?>
                    <?php if (!$first): ?><span class="sep">·</span><?php endif; $first = false; ?>
                    <a href="<?= e($href) ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
                <button type="button" class="theme-toggle" data-theme-toggle aria-label="Alternar tema claro/escuro" title="Alternar tema"><?= $ICON_MOON . $ICON_SUN ?></button>
            </nav>
        </div>
    </header>

    <main class="wrap">
        <?php require $viewFile; ?>
    </main>

    <footer class="footer">
        BLUE3 &copy; <?= date('Y') ?> &nbsp;·&nbsp; meuip.rs v<?= e($version) ?>
        &nbsp;·&nbsp; <a href="https://blue3.com.br/" rel="noopener">blue3.com.br</a>
    </footer>
</body>
</html>
