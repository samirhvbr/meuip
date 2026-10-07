<?php
/**
 * MEUIP.RS — helpers globais (escape de saída, IP do cliente, respostas).
 */
declare(strict_types=1);

if (!defined('MEUIP_BOOTED')) {
    http_response_code(403);
    exit('Forbidden');
}

/** Escapa para saída HTML segura (corrige o XSS refletido do código antigo). */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Measurement id of the "meuip.rs – GA4" property. Public: it is in the page source. */
const MEUIP_GOOGLE_TAG_ID = 'G-2Y0G9PYWYT';

/**
 * Google tag (gtag.js) measurement id for the HTML pages; '' means the tag is off.
 *
 * config.php is gitignored and per environment, so a missing 'google' => ['tag_id']
 * key falls back to the production id and a deploy ships the tag without touching
 * the server's config; an explicit '' turns it off. Debug mode (development only)
 * turns it off too, so a local copy sends no hits. A value that is not a Google
 * measurement id is dropped, which keeps it safe inside the inline script.
 * Only views/layout.php calls this: the plain-text and JSON answers never carry it.
 */
function meuip_google_tag_id(): string
{
    if (!empty(meuip_config('debug'))) {
        return '';
    }
    $google = meuip_config('google');
    $id = is_array($google) && array_key_exists('tag_id', $google)
        ? trim((string) $google['tag_id'])
        : MEUIP_GOOGLE_TAG_ID;

    return preg_match('/^[A-Z]{1,3}-[A-Z0-9]{4,20}$/', $id) === 1 ? $id : '';
}

/** IP do cliente, validado. Retorna '' se inválido. */
function meuip_client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return filter_var($ip, FILTER_VALIDATE_IP) ? (string) $ip : '';
}

/** Detecta clientes de linha de comando (curl/wget/...) para responder em texto puro. */
function meuip_is_cli_client(): bool
{
    $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($ua === '') {
        return true; // sem User-Agent: provavelmente curl
    }
    foreach (['curl', 'wget', 'httpie', 'libcurl', 'python-requests', 'go-http', 'powershell', 'fetch'] as $needle) {
        if (strpos($ua, $needle) !== false) {
            return true;
        }
    }
    return false;
}

/** Resposta em texto puro (endpoints curl). */
function meuip_text(string $body, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo $body . "\n";
}

/** Resposta JSON. */
function meuip_json($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/** Renderiza uma view dentro do layout. */
function meuip_view(string $view, array $data = []): void
{
    header('Cache-Control: no-store'); // página personalizada por visitante
    extract($data, EXTR_SKIP);
    $viewFile = MEUIP_ROOT . '/views/' . $view . '.php';
    require MEUIP_ROOT . '/views/layout.php';
}
