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
