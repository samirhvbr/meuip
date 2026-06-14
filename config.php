<?php
/**
 * MEUIP.RS — configuração LOCAL (NÃO versionar).
 *
 * Este arquivo contém segredos e é ignorado pelo git (.gitignore) e bloqueado
 * para acesso web (.htaccess). Os valores abaixo foram migrados do código-fonte
 * antigo para manter o serviço funcionando.
 *
 * >>> RECOMENDADO: rotacionar a senha do MySQL e o token ipinfo, pois estiveram
 *     expostos em texto claro no código-fonte. <<<
 */

return [
    'db' => [
        'host'     => getenv('MEUIP_DB_HOST')  ?: '100.64.66.211',
        'port'     => (int) (getenv('MEUIP_DB_PORT') ?: 3306),
        'database' => getenv('MEUIP_DB_NAME')  ?: 'mdata',
        'username' => getenv('MEUIP_DB_USER')  ?: 'mdataUser',
        'password' => getenv('MEUIP_DB_PASS')  ?: '%q4j45B!@%4c#9NU2r!L9qx%LMS%7&aD',
        'charset'  => 'utf8mb4',
        'table'    => 'meuip_visits',
    ],

    'ipinfo_token' => getenv('MEUIP_IPINFO_TOKEN') ?: '418dd6100df878',

    'geo_cache_ttl' => (int) (getenv('MEUIP_GEO_TTL') ?: 21600),
    'cache_dir'     => getenv('MEUIP_CACHE_DIR') ?: '',

    'http_connect_timeout' => 2,
    'http_timeout'         => 4,
    'geo_rate_per_minute'  => 40,

    'debug' => (bool) (getenv('MEUIP_DEBUG') ?: false),
];
