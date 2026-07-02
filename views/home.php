<?php
/** MEUIP.RS — página inicial (IP + conexão + documentação). */
if (!defined('MEUIP_BOOTED')) {
    exit;
}
/** @var string $ip @var array $geo @var string $os @var string $browser @var string $userAgent @var string $method */
$ip       = $ip ?? '';
$geo      = $geo ?? [];
$os       = $os ?? '';
$browser  = $browser ?? '';
$userAgent = $userAgent ?? '';
$method   = $method ?? '';

$pais = trim(($geo['country'] ?? '') . (!empty($geo['country_name']) ? ' — ' . $geo['country_name'] : ''));
$dash = '<span style="color:var(--muted)">—</span>';
?>
<section class="hero">
    <div class="label">Seu endereço IP</div>
    <div class="ip-row">
        <span class="ip"><?= e($ip ?: 'desconhecido') ?></span>
        <?php if ($ip !== ''): ?>
            <button type="button" class="copy-btn" data-copy="<?= e($ip) ?>" data-label="1" aria-label="Copiar endereço IP">
                <?= $ICON_COPY ?><span class="copy-btn-txt">Copiar</span>
            </button>
        <?php endif; ?>
    </div>

    <?php
        // Chips-resumo (os dados detalhados seguem no card "Sua Conexão").
        $city    = trim(($geo['city'] ?? '') . (!empty($geo['region']) ? ', ' . $geo['region'] : ''), ', ');
        $localTxt = trim($city . (!empty($geo['country']) ? ' · ' . $geo['country'] : ''), ' ·');
        $netTxt   = trim(($geo['asn'] ?? '') . (!empty($geo['isp']) ? ' · ' . $geo['isp'] : ''), ' ·');
    ?>
    <?php if ($ip !== '' || $localTxt !== '' || $netTxt !== ''): ?>
    <div class="chips">
        <?php if ($ip !== ''): ?>
            <span class="chip badge"><?= str_contains($ip, ':') ? 'IPv6' : 'IPv4' ?></span>
        <?php endif; ?>
        <?php if ($localTxt !== ''): ?>
            <span class="chip"><?= $ICON_PIN ?><span class="txt"><?= e($localTxt) ?></span></span>
        <?php endif; ?>
        <?php if ($netTxt !== ''): ?>
            <span class="chip"><?= $ICON_NET ?><span class="txt"><?= e($netTxt) ?></span></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($geo['hostname'])): ?>
        <div class="sub"><?= e($geo['hostname']) ?></div>
    <?php endif; ?>
</section>

<div class="card">
    <h2>Sua Conexão</h2>
    <table class="kv">
        <tr><th>IP Address</th><td><span class="mono"><?= e($ip) ?></span></td></tr>
        <tr><th>Hostname</th><td><?= $geo['hostname'] ? e($geo['hostname']) : '<span style="color:var(--muted)">sem PTR</span>' ?></td></tr>
        <tr><th>ASN</th><td><?= $geo['asn'] ? '<span class="mono">' . e($geo['asn']) . '</span>' : $dash ?></td></tr>
        <tr><th>Provedor (ISP)</th><td><?= $geo['isp'] ? e($geo['isp']) : $dash ?></td></tr>
        <tr><th>País</th><td><?= $pais !== '' ? e($pais) : $dash ?></td></tr>
        <tr><th>Região</th><td><?= $geo['region'] ? e($geo['region']) : $dash ?></td></tr>
        <?php if (!empty($geo['city'])): ?>
            <tr><th>Cidade</th><td><?= e($geo['city']) ?></td></tr>
        <?php endif; ?>
        <tr><th>Método</th><td><span class="mono"><?= e($method) ?></span></td></tr>
        <tr><th>Navegador</th><td><?= e($browser) ?> <span style="color:var(--muted)">·</span> <?= e($os) ?></td></tr>
        <tr><th>User-Agent</th><td><span class="mono" style="font-size:12.5px"><?= e($userAgent ?: '—') ?></span></td></tr>
    </table>
</div>

<div class="card">
    <h2>Documentação (curl)</h2>
    <table class="doc">
        <tr><td>Seu IP</td><td><?= $cmd('curl meuip.rs') ?></td><td class="out"><?= e($ip) ?></td></tr>
        <tr><td>IP</td><td><?= $cmd('curl meuip.rs/ip') ?></td><td class="out"><?= e($ip) ?></td></tr>
        <tr><td>ASN</td><td><?= $cmd('curl meuip.rs/asn') ?></td><td class="out"><?= $geo['asn'] ? e($geo['asn']) : '—' ?></td></tr>
        <tr><td>Provedor</td><td><?= $cmd('curl meuip.rs/isp') ?></td><td class="out"><?= $geo['isp'] ? e($geo['isp']) : '—' ?></td></tr>
        <tr><td>País</td><td><?= $cmd('curl meuip.rs/country') ?></td><td class="out"><?= $geo['country'] ? e($geo['country']) : '—' ?></td></tr>
        <tr><td>Região</td><td><?= $cmd('curl meuip.rs/region') ?></td><td class="out"><?= $geo['region'] ? e($geo['region']) : '—' ?></td></tr>
        <tr><td>Coordenadas</td><td><?= $cmd('curl meuip.rs/coord') ?></td><td class="out"><?php
            if (!empty($geo['loc']) && str_contains($geo['loc'], ',')) {
                [$lat, $lon] = array_map('trim', explode(',', $geo['loc'], 2));
                $osmUrl = sprintf('https://www.openstreetmap.org/?mlat=%s&mlon=%s#map=12', rawurlencode($lat), rawurlencode($lon));
                echo '<a href="' . e($osmUrl) . '" target="_blank" rel="noopener">' . e($geo['loc']) . '</a>';
            } else {
                echo '—';
            }
        ?></td></tr>
        <tr><td>Tudo (JSON)</td><td><?= $cmd('curl meuip.rs/all') ?></td><td class="out">JSON</td></tr>
    </table>
</div>
