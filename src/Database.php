<?php
/**
 * MEUIP.RS — conexão PDO única (lazy), com prepared statements.
 */
declare(strict_types=1);

namespace Meuip;

use PDO;
use Throwable;

final class Database
{
    private static ?PDO $pdo = null;
    private static bool $tried = false;

    /** Conexão única e lazy. Retorna null se indisponível (nunca derruba a página). */
    public static function pdo(): ?PDO
    {
        if (self::$tried) {
            return self::$pdo;
        }
        self::$tried = true;

        $cfg = meuip_config('db');
        if (!is_array($cfg) || empty($cfg['host'])) {
            return null;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            (int) ($cfg['port'] ?? 3306),
            $cfg['database'] ?? '',
            $cfg['charset'] ?? 'utf8mb4'
        );

        try {
            self::$pdo = new PDO($dsn, $cfg['username'] ?? '', $cfg['password'] ?? '', [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 3,
            ]);
        } catch (Throwable $e) {
            error_log('[meuip] falha ao conectar no banco: ' . $e->getMessage());
            self::$pdo = null;
        }

        return self::$pdo;
    }
}
