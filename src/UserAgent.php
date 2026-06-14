<?php
/**
 * MEUIP.RS — detecção de SO e navegador a partir do User-Agent.
 */
declare(strict_types=1);

namespace Meuip;

final class UserAgent
{
    private const OS = [
        '/windows nt 10/i'      => 'Windows 10/11',
        '/windows nt 6.3/i'     => 'Windows 8.1',
        '/windows nt 6.2/i'     => 'Windows 8',
        '/windows nt 6.1/i'     => 'Windows 7',
        '/windows nt 6.0/i'     => 'Windows Vista',
        '/windows nt 5.1/i'     => 'Windows XP',
        '/macintosh|mac os x/i' => 'macOS',
        '/android/i'            => 'Android',
        '/iphone/i'             => 'iPhone',
        '/ipad/i'               => 'iPad',
        '/ipod/i'               => 'iPod',
        '/ubuntu/i'             => 'Ubuntu',
        '/linux/i'              => 'Linux',
        '/cros/i'               => 'ChromeOS',
    ];

    private const BROWSER = [
        '/edg/i'        => 'Edge',
        '/opr|opera/i'  => 'Opera',
        '/firefox|fxios/i' => 'Firefox',
        '/chrome|crios/i'  => 'Chrome',
        '/safari/i'     => 'Safari',
        '/msie|trident/i' => 'Internet Explorer',
        '/curl/i'       => 'curl',
        '/wget/i'       => 'wget',
    ];

    public static function os(string $ua): string
    {
        return self::match($ua, self::OS, 'Desconhecido');
    }

    public static function browser(string $ua): string
    {
        return self::match($ua, self::BROWSER, 'Desconhecido');
    }

    private static function match(string $ua, array $map, string $default): string
    {
        if ($ua === '') {
            return $default;
        }
        foreach ($map as $regex => $label) {
            if (preg_match($regex, $ua)) {
                return $label;
            }
        }
        return $default;
    }
}
