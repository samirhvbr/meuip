<?php
/**
 * MEUIP.RS — front controller / roteador.
 *
 * Samir Hanna Verza (samir@blue3.com.br) — BLUE3
 * Reescrito em 2026 (v2.0): prepared statements, cURL nativo, cache, escape de
 * saída, roteamento real e separação template/lógica.
 */
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use Meuip\AccessLog;
use Meuip\GeoLookup;
use Meuip\NetTrace;
use Meuip\UserAgent;

const MEUIP_VERSION = '2.2';

// --- Entrada ---------------------------------------------------------------
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = '/' . trim(rawurldecode($path), '/');
$path = strtolower($path);

$ip        = meuip_client_ip();
$userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
$method    = (string) ($_SERVER['REQUEST_METHOD'] ?? '');

// Geo (cacheado por IP) + registro da visita (best-effort, nunca derruba a página).
$geo = GeoLookup::lookup($ip);
AccessLog::record($geo);

// --- Ferramenta de traceroute (rota com subcaminhos) -----------------------
// /trace            → página HTML (browser) ou rota até o próprio IP (curl)
// /trace/<host>     → traceroute em texto puro (curl / looking glass)
// /trace/stream[?host=] → streaming ao vivo (SSE), consumido pela página
if ($path === '/trace' || str_starts_with($path, '/trace/')) {
    if (!NetTrace::enabled()) {
        meuip_text("traceroute desabilitado\n", 404);
        exit;
    }

    $sub      = ltrim(substr($path, strlen('/trace')), '/'); // '' | 'stream' | '<host>'
    $isStream = ($sub === 'stream') || isset($_GET['stream']);

    // Alvo: subpath /trace/<host> tem prioridade; senão ?host=; senão o IP do visitante.
    $rawHost = '';
    if ($sub !== '' && $sub !== 'stream') {
        $rawHost = rawurldecode($sub);
    } elseif (isset($_GET['host'])) {
        $rawHost = (string) $_GET['host'];
    }
    $custom = trim($rawHost) !== '';

    // Sem alvo livre habilitado, tudo cai para o IP do próprio visitante.
    if ($custom && !NetTrace::allowCustom()) {
        $custom  = false;
        $rawHost = '';
    }
    $targetHost = $custom ? $rawHost : $ip;

    // Página interativa: só quando é browser em /trace sem alvo/stream.
    if (!$isStream && !$custom && $sub === '' && !meuip_is_cli_client()) {
        meuip_view('trace', [
            'title'       => 'BLUE3 — meuip.rs · traceroute',
            'version'     => MEUIP_VERSION,
            'ip'          => $ip,
            'allowCustom' => NetTrace::allowCustom(),
        ]);
        exit;
    }

    // Daqui pra baixo é execução (SSE ou texto). Rate-limit por IP.
    if (!NetTrace::rateOk($ip)) {
        if ($isStream) {
            NetTrace::sseError('Limite de traceroutes por minuto atingido. Tente daqui a pouco.');
        } else {
            meuip_text("limite por minuto atingido, tente novamente em instantes\n", 429);
        }
        exit;
    }

    $resolved = NetTrace::resolve($targetHost);
    if (!$resolved['ok']) {
        if ($isStream) {
            NetTrace::sseError($resolved['error']);
        } else {
            meuip_text('alvo inválido: ' . $resolved['error'] . "\n", 400);
        }
        exit;
    }

    if ($isStream) {
        NetTrace::streamSse($resolved);
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        echo NetTrace::runText($resolved);
    }
    exit;
}

// --- Roteamento ------------------------------------------------------------
switch ($path) {
    case '/ip':
        meuip_text($ip);
        break;

    case '/asn':
        meuip_text($geo['asn']);
        break;

    case '/isp':
        meuip_text($geo['isp']);
        break;

    case '/country':
        meuip_text($geo['country']);
        break;

    case '/region':
        meuip_text($geo['region']);
        break;

    case '/coord':
        meuip_text($geo['loc']);
        break;

    case '/all':
        meuip_json([
            'ip'       => $ip,
            'hostname' => $geo['hostname'],
            'asn'      => $geo['asn'],
            'isp'      => $geo['isp'],
            'country'  => $geo['country'],
            'country_name' => $geo['country_name'],
            'region'   => $geo['region'],
            'city'     => $geo['city'],
            'loc'      => $geo['loc'],
        ]);
        break;

    case '/doc':
        meuip_view('doc', [
            'title'   => 'BLUE3 — meuip.rs · Documentação',
            'version' => MEUIP_VERSION,
            'ip'      => $ip,
        ]);
        break;

    case '/':
        // curl meuip.rs => só o IP em texto puro (comportamento clássico).
        if (meuip_is_cli_client()) {
            meuip_text($ip);
            break;
        }
        // fall-through proposital para renderizar a página (igual a /full).
    case '/full':
        meuip_view('home', [
            'title'     => 'BLUE3 — meuip.rs',
            'version'   => MEUIP_VERSION,
            'ip'        => $ip,
            'geo'       => $geo,
            'os'        => UserAgent::os($userAgent),
            'browser'   => UserAgent::browser($userAgent),
            'userAgent' => $userAgent,
            'method'    => $method,
        ]);
        break;

    default:
        if (meuip_is_cli_client()) {
            meuip_text("404 Not Found\n", 404);
        } else {
            meuip_view('home', [
                'title'     => 'BLUE3 — meuip.rs',
                'version'   => MEUIP_VERSION,
                'ip'        => $ip,
                'geo'       => $geo,
                'os'        => UserAgent::os($userAgent),
                'browser'   => UserAgent::browser($userAgent),
                'userAgent' => $userAgent,
                'method'    => $method,
            ]);
        }
        break;
}
