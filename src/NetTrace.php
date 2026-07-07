<?php
/**
 * MEUIP.RS — traceroute (looking glass) com streaming ao vivo (SSE).
 *
 * Espelha a disciplina da GeoLookup: timeout curto, cache/rate-limit em arquivo,
 * nunca derruba a página. Aqui o foco é SEGURANÇA, porque roda `traceroute` num
 * servidor de ISP exposto à internet:
 *
 *   1. Sem shell        — proc_open() com argv em array (nada de string).
 *   2. Anti-SSRF        — resolve o host e bloqueia faixas privadas/reservadas
 *                          e CGNAT 100.64.0.0/10 (a infra interna vive aí).
 *   3. Anti-DoS         — traceroute -n -q1 -w1 -m<N> + `timeout` rígido.
 *   4. Anti-abuso       — rate-limit por IP + teto de execuções simultâneas.
 *
 * Samir Hanna Verza (samir@blue3.com.br) — BLUE3
 */
declare(strict_types=1);

namespace Meuip;

final class NetTrace
{
    private const BIN4 = 'traceroute';
    private const BIN6 = 'traceroute6';

    // "* * *" consecutivos para abortar cedo no stream: resto do caminho está
    // filtrado (ICMP), não vale arrastar a sessão até o max_hops.
    private const STALL_HOPS = 5;

    // Defaults quando config.php não traz o bloco 'trace' (ver config.example.php).
    private const DEFAULTS = [
        'enabled'         => true,
        'allow_custom'    => true, // false = só o IP do visitante (sem alvo livre)
        'max_hops'        => 20,
        'timeout'         => 25,   // teto de parede por execução (s)
        'rate_per_minute' => 6,    // por IP de cliente
        'max_concurrent'  => 4,    // execuções simultâneas no servidor
    ];

    // ---- Configuração ----------------------------------------------------

    private static function opt(string $key)
    {
        $c = meuip_config('trace');
        $c = is_array($c) ? $c : [];
        return $c[$key] ?? self::DEFAULTS[$key] ?? null;
    }

    public static function enabled(): bool     { return (bool) self::opt('enabled'); }
    public static function allowCustom(): bool { return (bool) self::opt('allow_custom'); }

    // ---- Validação e resolução do alvo -----------------------------------

    /**
     * Valida/resolve o alvo. Retorna:
     *   ['ok'=>true,'ip'=>'8.8.8.8','family'=>4,'display'=>'google.com (142.…)']
     *   ['ok'=>false,'error'=>'motivo']
     * Bloqueia qualquer alvo (ou resolução) em faixa privada/reservada/CGNAT.
     */
    public static function resolve(string $host): array
    {
        $host = trim($host);
        if ($host === '') {
            return self::err('alvo vazio');
        }
        if (strlen($host) > 253) {
            return self::err('alvo muito longo');
        }

        // IP literal (v4/v6).
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (!self::isPublicIp($host)) {
                return self::err('alvo em faixa privada/reservada não é permitido');
            }
            $family = strpos($host, ':') !== false ? 6 : 4;
            return ['ok' => true, 'ip' => $host, 'family' => $family, 'display' => $host, 'error' => ''];
        }

        // Hostname: sintaxe estrita (letras/dígitos/hífen, ao menos um ponto).
        $re = '/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/i';
        if (!preg_match($re, $host)) {
            return self::err('host inválido');
        }

        $ips = self::resolveAll($host);
        if (!$ips) {
            return self::err('não resolveu (DNS)');
        }
        // Anti-rebind: se QUALQUER registro aponta para faixa interna, recusa.
        foreach ($ips as $cand) {
            if (!self::isPublicIp($cand)) {
                return self::err('host resolve para faixa privada — bloqueado');
            }
        }

        // Fixa o IP resolvido (evita re-resolução no traceroute → sem TOCTOU).
        $ip     = $ips[0];
        $family = strpos($ip, ':') !== false ? 6 : 4;
        return ['ok' => true, 'ip' => $ip, 'family' => $family, 'display' => $host . ' (' . $ip . ')', 'error' => ''];
    }

    /** Resolve A + AAAA. Retorna lista de IPs (strings), sem duplicatas. */
    private static function resolveAll(string $host): array
    {
        $out = [];
        $v4 = @gethostbynamel($host);
        if (is_array($v4)) {
            $out = array_merge($out, $v4);
        }
        if (function_exists('dns_get_record')) {
            $aaaa = @dns_get_record($host, DNS_AAAA);
            if (is_array($aaaa)) {
                foreach ($aaaa as $r) {
                    if (!empty($r['ipv6'])) {
                        $out[] = (string) $r['ipv6'];
                    }
                }
            }
        }
        return array_values(array_unique($out));
    }

    /** IP público? Rejeita privado/reservado (flags do PHP) + CGNAT 100.64/10. */
    private static function isPublicIp(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        // 100.64.0.0/10 (RFC 6598, CGNAT) não é coberto pelos flags acima.
        if (strpos($ip, ':') === false) {
            $long = ip2long($ip);
            if ($long !== false && ($long & 0xFFC00000) === 0x64400000) {
                return false;
            }
        }
        return true;
    }

    private static function err(string $msg): array
    {
        return ['ok' => false, 'ip' => '', 'family' => 4, 'display' => '', 'error' => $msg];
    }

    // ---- Execução: comando -----------------------------------------------

    /** argv sem shell. `timeout` (coreutils) garante o teto de parede. */
    private static function argv(array $t, bool $live = false): array
    {
        $bin = ($t['family'] ?? 4) === 6 ? self::BIN6 : self::BIN4;
        $to  = (string) (int) self::opt('timeout');
        $max = (string) (int) self::opt('max_hops');
        // stdbuf -oL: saída line-buffered mesmo em pipe — cada salto chega ao
        // SSE na hora, em vez de acumular nos blocos de 4 KB do stdio.
        $args = ['timeout', '-k', '2', $to, 'stdbuf', '-oL', $bin, '-n', '-q', '1', '-w', '1', '-m', $max];
        if ($live) {
            // -N 1: sonda UM salto por vez (default são 16 em paralelo) — a
            // página ao vivo revela a rota salto a salto, na cadência real.
            $args[] = '-N';
            $args[] = '1';
        }
        $args[] = (string) $t['ip'];
        return $args;
    }

    // ---- Execução: streaming SSE -----------------------------------------

    /** Roda o traceroute e transmite cada salto via SSE. Bloqueia até terminar. */
    public static function streamSse(array $t): void
    {
        self::sseInit();

        $release = self::acquireSlot();
        if ($release === null) {
            self::sseEvent('error', ['message' => 'Muitas execuções simultâneas no servidor. Tente em instantes.']);
            self::sseEvent('done', ['ok' => false]);
            return;
        }

        try {
            @set_time_limit((int) self::opt('timeout') + 10);
            self::sseEvent('start', ['target' => $t['display'], 'ip' => $t['ip']]);

            $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $proc = @proc_open(self::argv($t, true), $desc, $pipes);
            if (!is_resource($proc)) {
                self::sseEvent('error', ['message' => 'Falha ao iniciar o traceroute']);
                self::sseEvent('done', ['ok' => false]);
                return;
            }

            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);

            $deadline = time() + (int) self::opt('timeout') + 3;
            $buf = '';
            $hops = 0;
            $stars = 0;      // "* * *" consecutivos (aborta cedo, ver STALL_HOPS)
            $note = '';
            $timedOut = false;
            $lastBeat = time();

            while (true) {
                if (connection_aborted()) {
                    @proc_terminate($proc);
                    break;
                }
                if (time() > $deadline) {
                    @proc_terminate($proc);
                    $timedOut = true;
                    self::sseEvent('error', ['message' => 'Tempo esgotado']);
                    break;
                }

                $read = [$pipes[1], $pipes[2]];
                $w = null;
                $x = null;
                $n = @stream_select($read, $w, $x, 1);
                if ($n === false) {
                    break;
                }
                if ($n > 0) {
                    foreach ($read as $fh) {
                        $chunk = fread($fh, 8192);
                        if ($chunk !== '' && $chunk !== false) {
                            $buf .= $chunk;
                        }
                    }
                    while (($pos = strpos($buf, "\n")) !== false) {
                        $line = substr($buf, 0, $pos);
                        $buf  = substr($buf, $pos + 1);
                        $hop  = self::parseHop($line);
                        if ($hop !== null) {
                            $hops++;
                            self::sseEvent('hop', $hop);
                            $lastBeat = time();
                            if ($hop['timeout']) {
                                if (++$stars >= self::STALL_HOPS) {
                                    @proc_terminate($proc);
                                    $note = 'Destino sem resposta nos últimos saltos (ICMP filtrado no caminho).';
                                    break 2; // sai do while (true)
                                }
                            } else {
                                $stars = 0;
                            }
                        }
                    }
                } elseif (time() - $lastBeat >= 5) {
                    echo ": keep-alive\n\n"; // comentário SSE mantém a conexão viva
                    @flush();
                    $lastBeat = time();
                }

                $status = proc_get_status($proc);
                if (!$status['running']) {
                    $rest = stream_get_contents($pipes[1]);
                    if ($rest !== false) {
                        $buf .= $rest;
                    }
                    foreach (explode("\n", $buf) as $line) {
                        $hop = self::parseHop($line);
                        if ($hop !== null) {
                            $hops++;
                            self::sseEvent('hop', $hop);
                        }
                    }
                    break;
                }
            }

            foreach ($pipes as $p) {
                if (is_resource($p)) {
                    @fclose($p);
                }
            }
            @proc_close($proc);

            $done = ['ok' => !$timedOut, 'hops' => $hops];
            if ($note !== '') {
                $done['note'] = $note;
            }
            self::sseEvent('done', $done);
        } finally {
            $release();
        }
    }

    /** Resposta SSE curta de erro (limite/alvo inválido antes de executar). */
    public static function sseError(string $message): void
    {
        self::sseInit();
        self::sseEvent('error', ['message' => $message]);
        self::sseEvent('done', ['ok' => false]);
    }

    // ---- Execução: texto puro (curl) -------------------------------------

    /** Roda o traceroute e devolve a saída em texto (endpoints curl). */
    public static function runText(array $t): string
    {
        $release = self::acquireSlot();
        if ($release === null) {
            return "traceroute indisponível: muitas execuções simultâneas\n";
        }
        try {
            @set_time_limit((int) self::opt('timeout') + 10);
            $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $proc = @proc_open(self::argv($t), $desc, $pipes);
            if (!is_resource($proc)) {
                return "falha ao iniciar o traceroute\n";
            }
            // `timeout` garante o fim; leitura bloqueante até EOF é segura aqui.
            stream_set_blocking($pipes[1], true);
            $out = (string) stream_get_contents($pipes[1]);
            $err = (string) stream_get_contents($pipes[2]);
            foreach ($pipes as $p) {
                if (is_resource($p)) {
                    @fclose($p);
                }
            }
            @proc_close($proc);
            $out = $out !== '' ? $out : $err;
            return $out !== '' ? $out : "sem saída\n";
        } finally {
            $release();
        }
    }

    // ---- Parsing de linha do traceroute ----------------------------------

    /**
     * Converte " 3  8.8.8.8  8.1 ms" → ['hop'=>3,'ip'=>'8.8.8.8','ms'=>'8.1',…].
     * Linhas sem número de salto (cabeçalho) → null. "* * *" → timeout=true.
     */
    private static function parseHop(string $line): ?array
    {
        $line = rtrim($line);
        if (!preg_match('/^\s*(\d+)\s+(.*)$/', $line, $m)) {
            return null;
        }
        $rest = trim($m[2]);
        $ip = null;
        if (preg_match('/(\d{1,3}(?:\.\d{1,3}){3})/', $rest, $mm)) {
            $ip = $mm[1];
        } elseif (preg_match('/([0-9a-f]*:[0-9a-f:]+)/i', $rest, $mm)) {
            $ip = $mm[1];
        }
        $ms = null;
        if (preg_match('/([\d.]+)\s*ms/', $rest, $mm)) {
            $ms = $mm[1];
        }
        return [
            'hop'     => (int) $m[1],
            'ip'      => $ip,
            'ms'      => $ms,
            'timeout' => $ip === null,
        ];
    }

    // ---- SSE (cabeçalhos + evento) ---------------------------------------

    private static function sseInit(): void
    {
        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', '0');
        @ini_set('implicit_flush', '1');
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
        ignore_user_abort(true);
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
        ob_implicit_flush(true);

        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // desliga buffering em proxies (nginx)

        // Padding inicial desarma buffers de proxy que só liberam após ~1-2 KB.
        echo ':' . str_repeat(' ', 2048) . "\n\n";
        @flush();
    }

    private static function sseEvent(string $event, array $data): void
    {
        echo 'event: ' . $event . "\n";
        echo 'data: ' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
        @flush();
    }

    // ---- Rate-limit por IP + semáforo de concorrência --------------------

    /** Teto de traceroutes por minuto e por IP de cliente (bucket em arquivo). */
    public static function rateOk(string $clientIp): bool
    {
        $limit = (int) self::opt('rate_per_minute');
        if ($limit <= 0) {
            return true;
        }
        $key    = $clientIp !== '' ? md5($clientIp) : 'anon';
        $bucket = self::cacheDir() . '/trace-rl-' . gmdate('YmdHi') . '-' . $key;
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

    /**
     * Reserva um "slot" de execução (protege o pool do PHP-FPM). Retorna um
     * callable que libera o slot, ou null se o teto já está lotado. Slots com
     * mais de 60 s são considerados órfãos e reciclados.
     */
    private static function acquireSlot(): ?callable
    {
        $max = (int) self::opt('max_concurrent');
        if ($max <= 0) {
            return static function (): void {};
        }
        $dir = self::cacheDir() . '/trace-slots';
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }
        foreach (glob($dir . '/*.slot') ?: [] as $f) {
            if (@filemtime($f) < time() - 60) {
                @unlink($f);
            }
        }
        if (count(glob($dir . '/*.slot') ?: []) >= $max) {
            return null;
        }
        $mine = $dir . '/' . getmypid() . '-' . substr(md5(uniqid('', true)), 0, 8) . '.slot';
        @file_put_contents($mine, '1');
        return static function () use ($mine): void {
            @unlink($mine);
        };
    }

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
}
