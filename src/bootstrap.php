<?php
/**
 * MEUIP.RS — bootstrap: carrega config, define timezone, erros e autoloader.
 */
declare(strict_types=1);

define('MEUIP_BOOTED', true);
define('MEUIP_ROOT', dirname(__DIR__)); // .../meuip.rs
define('MEUIP_SRC', __DIR__);

// Configuração: usa config.php (real, gitignored) e cai para o exemplo se ausente.
$configFile = MEUIP_ROOT . '/config.php';
$config = is_file($configFile)
    ? require $configFile
    : require MEUIP_ROOT . '/config.example.php';
$GLOBALS['meuip_config'] = is_array($config) ? $config : [];

date_default_timezone_set('America/Sao_Paulo');

error_reporting(E_ALL);
ini_set('display_errors', !empty($config['debug']) ? '1' : '0');
ini_set('log_errors', '1');

// Autoloader simples para classes Meuip\*
spl_autoload_register(static function (string $class): void {
    $prefix = 'Meuip\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $path = MEUIP_SRC . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

require_once MEUIP_SRC . '/helpers.php';

/** Acesso à configuração: meuip_config() ou meuip_config('db'). */
function meuip_config(?string $key = null)
{
    $c = $GLOBALS['meuip_config'] ?? [];
    return $key === null ? $c : ($c[$key] ?? null);
}
