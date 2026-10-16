<?php

namespace App\Support;

/** Short human label for a user agent, e.g. "Chrome di macOS". Rough on purpose: only for the session list. */
class DeviceLabel
{
    /** Order matters: Edge and Opera also say "Chrome", Chrome also says "Safari". */
    private const BROWSERS = [
        '/Edg(e|A|iOS)?\//' => 'Edge',
        '/OPR\/|Opera/' => 'Opera',
        '/SamsungBrowser\//' => 'Samsung Internet',
        '/Firefox\/|FxiOS\//' => 'Firefox',
        '/Chrome\/|CriOS\//' => 'Chrome',
        '/Version\/[\d.]+.*Safari\//' => 'Safari',
    ];

    /** Order matters: iPhone and iPad say "like Mac OS X", Android says "Linux". */
    private const SYSTEMS = [
        '/iPhone/' => 'iPhone',
        '/iPad/' => 'iPad',
        '/Android/' => 'Android',
        '/Windows/' => 'Windows',
        '/CrOS/' => 'ChromeOS',
        '/Macintosh|Mac OS X/' => 'macOS',
        '/Linux/' => 'Linux',
    ];

    public static function from(?string $userAgent): string
    {
        $browser = self::match((string) $userAgent, self::BROWSERS);
        $system = self::match((string) $userAgent, self::SYSTEMS);

        return match (true) {
            $browser && $system => "$browser di $system",
            (bool) $browser => $browser,
            (bool) $system => "Peramban di $system",
            default => 'Perangkat tidak dikenal',
        };
    }

    private static function match(string $userAgent, array $patterns): ?string
    {
        foreach ($patterns as $pattern => $label) {
            if (preg_match($pattern, $userAgent)) {
                return $label;
            }
        }

        return null;
    }
}
