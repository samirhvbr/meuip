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
