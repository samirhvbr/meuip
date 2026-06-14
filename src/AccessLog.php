<?php
/**
 * MEUIP.RS — registro de visitas (auditoria/rastreabilidade) via prepared statements.
 *
 * Best-effort: qualquer falha de banco é logada e silenciada — nunca derruba a
 * página. Os dados ficam em `meuip_visits` (ver schema.sql) e são lidos pelo
 * admin da INTRANET (conexão read-only "meuip").
 *
 * Privacidade: NÃO registramos conteúdo de cookies (diferente do código antigo).
 */
declare(strict_types=1);

namespace Meuip;

use Throwable;

final class AccessLog
{
    /** Registra a visita atual com os geodados já resolvidos. */
    public static function record(array $geo): void
    {
        try {
            $pdo = Database::pdo();
            if (!$pdo) {
                return;
            }

            $cfg   = meuip_config('db');
            $table = preg_replace('/[^A-Za-z0-9_]/', '', (string) ($cfg['table'] ?? 'meuip_visits'));
            $ua    = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512);

            $sql = "INSERT INTO `{$table}`
                (ip, method, scheme, host, request_uri, referer, user_agent, os, browser,
                 asn, isp, country, region, city, hostname)
                VALUES
                (:ip, :method, :scheme, :host, :request_uri, :referer, :user_agent, :os, :browser,
                 :asn, :isp, :country, :region, :city, :hostname)";

            $pdo->prepare($sql)->execute([
                ':ip'          => meuip_client_ip(),
                ':method'      => mb_substr((string) ($_SERVER['REQUEST_METHOD'] ?? ''), 0, 10),
                ':scheme'      => mb_substr((string) ($_SERVER['REQUEST_SCHEME'] ?? (empty($_SERVER['HTTPS']) ? 'http' : 'https')), 0, 10),
                ':host'        => mb_substr((string) ($_SERVER['HTTP_HOST'] ?? ''), 0, 191),
                ':request_uri' => mb_substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 512),
                ':referer'     => mb_substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 512),
                ':user_agent'  => $ua,
                ':os'          => UserAgent::os($ua),
                ':browser'     => UserAgent::browser($ua),
                ':asn'         => mb_substr((string) ($geo['asn'] ?? ''), 0, 32),
                ':isp'         => mb_substr((string) ($geo['isp'] ?? ''), 0, 191),
                ':country'     => mb_substr((string) ($geo['country'] ?? ''), 0, 8),
                ':region'      => mb_substr((string) ($geo['region'] ?? ''), 0, 128),
                ':city'        => mb_substr((string) ($geo['city'] ?? ''), 0, 128),
                ':hostname'    => mb_substr((string) ($geo['hostname'] ?? ''), 0, 255),
            ]);
        } catch (Throwable $e) {
            error_log('[meuip] falha ao registrar visita: ' . $e->getMessage());
        }
    }
}
