<?php
/** MEUIP.RS — traceroute ao vivo (form + tabela de saltos via SSE). */
if (!defined('MEUIP_BOOTED')) {
    exit;
}
/** @var string $ip @var bool $allowCustom @var string $initialHost @var bool $initialLoop */
$ip          = $ip ?? '';
$allowCustom = $allowCustom ?? true;
$initialHost = $initialHost ?? '';        // alvo vindo da URL (?host= ou /trace/<host>)
$initialLoop = !empty($initialLoop);      // modo contínuo pré-marcado (?loop=1)
?>
<section class="hero">
    <div class="label">Traceroute</div>
    <div class="ip" style="font-size:clamp(24px,4.5vw,38px)">Rota de rede</div>
    <div class="sub">O caminho (saltos) do servidor meuip.rs até um destino, ao vivo.</div>
</section>

<div class="card">
    <h2>Traceroute ao vivo</h2>
    <form id="trace-form" class="trace-form" autocomplete="off"
          data-self-ip="<?= e($ip) ?>" data-allow-custom="<?= $allowCustom ? '1' : '0' ?>"
          data-initial-host="<?= e($initialHost) ?>" data-autostart="1">
        <label for="trace-host" class="trace-label">Destino</label>
        <div class="trace-input-row">
            <input id="trace-host" name="host" type="text" class="trace-input mono"
                   value="<?= e($initialHost !== '' ? $initialHost : $ip) ?>"
                   placeholder="IP ou host (ex.: 8.8.8.8, google.com)"
                   spellcheck="false" inputmode="url" maxlength="253"
                   <?= $allowCustom ? '' : 'readonly' ?>>
            <button type="submit" class="trace-btn" id="trace-run"><?= $ICON_NET ?><span>Traçar rota</span></button>
            <button type="button" class="trace-btn ghost" id="trace-stop" hidden>Parar</button>
        </div>
        <label class="trace-check" for="trace-persist">
            <input type="checkbox" id="trace-persist"<?= $initialLoop ? ' checked' : '' ?>>
            Modo contínuo — fica pingando os saltos e acumulando estatísticas (perda, média)
        </label>
        <?php if ($allowCustom): ?>
            <div class="trace-hint">
                <button type="button" class="linklike" data-fill-self>usar meu IP<?= $ip !== '' ? ' (' . e($ip) . ')' : '' ?></button>
                <span class="sep">·</span>
                <button type="button" class="linklike" data-fill="8.8.8.8">8.8.8.8</button>
                <button type="button" class="linklike" data-fill="1.1.1.1">1.1.1.1</button>
                <button type="button" class="linklike" data-fill="blue3.com.br">blue3.com.br</button>
            </div>
        <?php else: ?>
            <div class="trace-hint">Traçando o caminho até o seu IP.</div>
        <?php endif; ?>
    </form>

    <div id="trace-status" class="trace-status" hidden></div>

    <table id="trace-table" class="trace-table" hidden>
        <thead id="trace-head"><tr><th class="h-hop">#</th><th class="h-host">IP</th><th class="h-rtt">RTT</th></tr></thead>
        <tbody id="trace-body"></tbody>
    </table>

    <noscript>
        <div class="trace-status">Sem JavaScript o streaming não roda — use a linha de comando abaixo.</div>
    </noscript>
</div>

<div class="card">
    <h2>Pela linha de comando</h2>
    <table class="doc">
        <tr><td>Rota até você</td><td><?= $cmd('curl meuip.rs/trace') ?></td><td class="out">texto</td></tr>
        <?php if ($allowCustom): ?>
            <tr><td>Rota até um destino</td><td><?= $cmd('curl meuip.rs/trace/1.1.1.1') ?></td><td class="out">texto</td></tr>
        <?php endif; ?>
    </table>
</div>
