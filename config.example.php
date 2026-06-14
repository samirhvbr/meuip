<?php
/**
 * MEUIP.RS — configuração
 *
 * Copie este arquivo para `config.php` e preencha com os valores reais.
 * `config.php` NÃO é versionado (ver .gitignore) e o acesso direto a ele é
 * bloqueado pelo .htaccess. Em produção, prefira definir as variáveis de
 * ambiente correspondentes (getenv) e manter o config fora do docroot.
 *
 * Samir Hanna Verza (samir@blue3.com.br) — BLUE3
 */

return [
    // Banco de dados (MySQL/MariaDB) onde as visitas são registradas.
    // O admin da INTRANET lê esta mesma tabela (conexão "meuip").
    'db' => [
        'host'     => getenv('MEUIP_DB_HOST')     ?: '100.64.66.211',
        'port'     => (int) (getenv('MEUIP_DB_PORT') ?: 3306),
        'database' => getenv('MEUIP_DB_NAME')     ?: 'mdata',
        'username' => getenv('MEUIP_DB_USER')     ?: 'CHANGE_ME',
        'password' => getenv('MEUIP_DB_PASS')     ?: 'CHANGE_ME',
        'charset'  => 'utf8mb4',
        // Tabela de visitas (ver schema.sql). Mantida separada do legado
        // `access_log` para não conflitar com o schema antigo.
        'table'    => 'meuip_visits',
    ],

    // Token da API ipinfo.io (fallback de geolocalização). Rotacione-o se vazar.
    'ipinfo_token' => getenv('MEUIP_IPINFO_TOKEN') ?: '',

    // Cache de geolocalização por IP (segundos). ASN/geo de um IP muda raramente.
    'geo_cache_ttl' => (int) (getenv('MEUIP_GEO_TTL') ?: 21600), // 6h

    // Diretório de cache (precisa ser gravável pelo PHP). Vazio = sys_get_temp_dir().
    'cache_dir' => getenv('MEUIP_CACHE_DIR') ?: '',

    // Timeouts (segundos) para as chamadas às APIs externas de geolocalização.
    'http_connect_timeout' => 2,
    'http_timeout'         => 4,

    // Teto de chamadas externas por minuto (protege a cota da API gratuita).
    // Acima disso, serve só o IP (sem geo) até o cache reidratar.
    'geo_rate_per_minute' => 40,

    // Mostrar erros na tela (apenas em desenvolvimento). Em produção: false.
    'debug' => (bool) (getenv('MEUIP_DEBUG') ?: false),
];
