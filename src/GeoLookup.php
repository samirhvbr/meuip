<?php
/**
 * MEUIP.RS — geolocalização de IP (ASN/ISP/país/região) com cache e timeout.
 *
 * Substitui o shell_exec("curl ...") + parsing byte-a-byte do código antigo por
 * cURL nativo, parsing JSON robusto, cache por IP e fallback ip-api -> ipinfo.
 */
declare(strict_types=1);

namespace Meuip;

final class GeoLookup
{
    private const EMPTY = [
        'asn' => '', 'isp' => '', 'country' => '', 'country_name' => '',
        'region' => '', 'city' => '', 'hostname' => '', 'loc' => '',
    ];

    /** Resolve geodados de um IP. Nunca lança exceção; retorna campos vazios em falha. */
    public static function lookup(string $ip): array
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return self::EMPTY;
        }
        // IPs privados/reservados não têm geolocalização pública.
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return self::EMPTY;
        }

        $cached = self::cacheGet($ip);
        if ($cached !== null) {
            return $cached;
        }

        // Protege a cota das APIs gratuitas: acima do teto/min, serve sem geo.
        if (!self::rateOk()) {
            return self::EMPTY;
        }

        $data = self::fromIpApi($ip) ?? self::fromIpInfo($ip) ?? self::EMPTY;
        self::cacheSet($ip, $data);
        return $data;
    }

    private static function fromIpApi(string $ip): ?array
    {
        $url = 'http://ip-api.com/json/' . rawurlencode($ip)
            . '?fields=status,message,as,isp,org,countryCode,country,regionName,city,lat,lon&lang=pt-BR';
        $raw = self::http($url);
        if ($raw === null) {
            return null;
        }
        $d = json_decode($raw, true);
        if (!is_array($d) || ($d['status'] ?? '') !== 'success') {
            return null;
        }
        $asn = '';
        if (!empty($d['as'])) {
            $asn = explode(' ', trim((string) $d['as']), 2)[0];
        }
        return [
            'asn'          => $asn,
            'isp'          => (string) ($d['isp'] ?: ($d['org'] ?? '')),
            'country'      => (string) ($d['countryCode'] ?? ''),
            'country_name' => (string) ($d['country'] ?? ''),
            'region'       => (string) ($d['regionName'] ?? ''),
            'city'         => (string) ($d['city'] ?? ''),
            'hostname'     => '',
            'loc'          => (isset($d['lat'], $d['lon']) ? $d['lat'] . ',' . $d['lon'] : ''),
        ];
    }

    private static function fromIpInfo(string $ip): ?array
    {
        $token = (string) meuip_config('ipinfo_token');
        $url = 'https://ipinfo.io/' . rawurlencode($ip) . '/json'
            . ($token !== '' ? '?token=' . rawurlencode($token) : '');
        $raw = self::http($url);
        if ($raw === null) {
            return null;
        }
        $d = json_decode($raw, true);
        if (!is_array($d) || !empty($d['error'])) {
            return null;
        }
        $asn = '';
        $isp = '';
        if (!empty($d['org'])) {
            $parts = explode(' ', trim((string) $d['org']), 2);
            $asn = $parts[0];
            $isp = $parts[1] ?? '';
        }
        return [
            'asn'          => $asn,
            'isp'          => $isp,
            'country'      => (string) ($d['country'] ?? ''),
            'country_name' => (string) ($d['country'] ?? ''),
            'region'       => (string) ($d['region'] ?? ''),
            'city'         => (string) ($d['city'] ?? ''),
            'hostname'     => (string) ($d['hostname'] ?? ''),
            'loc'          => (string) ($d['loc'] ?? ''),
        ];
    }

    /** GET HTTP com timeouts curtos. Usa ext-curl; cai para file_get_contents. */
    private static function http(string $url): ?string
    {
        $connect = (int) (meuip_config('http_connect_timeout') ?: 2);
        $total   = (int) (meuip_config('http_timeout') ?: 4);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => $connect,
                CURLOPT_TIMEOUT        => $total,
                CURLOPT_USERAGENT      => 'meuip.rs/2.0',
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 2,
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($body === false || $code >= 400) {
                return null;
            }
            return (string) $body;
        }

        $ctx = stream_context_create(['http' => ['timeout' => $total, 'ignore_errors' => true]]);
        $body = @file_get_contents($url, false, $ctx);
        return $body === false ? null : $body;
    }

    // ---- Cache em arquivo por IP ----------------------------------------

    private static function cacheDir(): string
    {
        $dir = (string) meuip_config('cache_dir');
        if ($dir === '') {
            $dir = sys_get_temp_dir() . '/meuip-cache';
        }
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }
        return $dir;
    }

    private static function cacheGet(string $ip): ?array
    {
        $ttl = (int) (meuip_config('geo_cache_ttl') ?: 21600);
        $file = self::cacheDir() . '/geo-' . md5($ip) . '.json';
        if (!is_file($file) || (filemtime($file) + $ttl) < time()) {
            return null;
        }
        $raw = @file_get_contents($file);
        $d = $raw !== false ? json_decode($raw, true) : null;
        return is_array($d) ? ($d + self::EMPTY) : null;
    }

    private static function cacheSet(string $ip, array $data): void
    {
        $file = self::cacheDir() . '/geo-' . md5($ip) . '.json';
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }

    /** Limita chamadas externas por minuto (bucket por minuto, auto-reset). */
    private static function rateOk(): bool
    {
        $limit = (int) (meuip_config('geo_rate_per_minute') ?: 40);
        if ($limit <= 0) {
            return true;
        }
        $bucket = self::cacheDir() . '/rl-' . gmdate('YmdHi');
        $fh = @fopen($bucket, 'c+');
        if (!$fh) {
            return true; // sem cache gravável, não bloqueia
        }
        @flock($fh, LOCK_EX);
        $count = (int) stream_get_contents($fh) + 1;
        @rewind($fh);
        @ftruncate($fh, 0);
        @fwrite($fh, (string) $count);
        @flock($fh, LOCK_UN);
        @fclose($fh);
        return $count <= $limit;
    }
}
