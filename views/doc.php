<?php
/** MEUIP.RS — documentação dos endpoints. */
if (!defined('MEUIP_BOOTED')) {
    exit;
}
/** @var string $ip */
$ip = $ip ?? '';
?>
<section class="hero">
    <div class="label">Documentação</div>
    <div class="ip" style="font-size:clamp(26px,5vw,40px)">API meuip.rs</div>
    <div class="sub">Endpoints em texto puro, ideais para scripts e linha de comando.</div>
</section>

<div class="card">
    <h2>Endpoints</h2>
    <table class="doc">
        <tr><td>IP do cliente</td><td><?= $cmd('curl meuip.rs/ip') ?></td><td class="out">texto</td></tr>
        <tr><td>ASN</td><td><?= $cmd('curl meuip.rs/asn') ?></td><td class="out">texto</td></tr>
        <tr><td>Provedor (ISP)</td><td><?= $cmd('curl meuip.rs/isp') ?></td><td class="out">texto</td></tr>
        <tr><td>País (código)</td><td><?= $cmd('curl meuip.rs/country') ?></td><td class="out">texto</td></tr>
        <tr><td>Região</td><td><?= $cmd('curl meuip.rs/region') ?></td><td class="out">texto</td></tr>
        <tr><td>Coordenadas</td><td><?= $cmd('curl meuip.rs/coord') ?></td><td class="out">lat,long</td></tr>
        <tr><td>Todos os dados</td><td><?= $cmd('curl meuip.rs/all') ?></td><td class="out">JSON</td></tr>
        <tr><td>Traceroute até você</td><td><?= $cmd('curl meuip.rs/trace') ?></td><td class="out">texto</td></tr>
        <tr><td>Traceroute até um destino</td><td><?= $cmd('curl meuip.rs/trace/1.1.1.1') ?></td><td class="out">texto</td></tr>
    </table>
</div>

<div class="card">
    <h2>Exemplo</h2>
    <table class="doc">
        <tr>
            <td>Resposta JSON</td>
            <td colspan="2"><code style="white-space:pre-wrap;display:block;line-height:1.7">curl -s meuip.rs/all | jq .</code></td>
        </tr>
    </table>
</div>
