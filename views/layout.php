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
    '/doc'     => 'Docs',
];
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
    <link rel="stylesheet" href="/assets/style.css">
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
