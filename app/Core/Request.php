<?php
declare(strict_types=1);
namespace App\Core;

/** Request helpers. Client IP is resolved safely behind reverse proxies (TRUSTED_PROXIES). */
final class Request
{
    private const DEFAULT_PROXIES = '127.0.0.0/8,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16,::1/128,fc00::/7';
    private static ?string $ip = null;

    /** Normalised request path without a trailing slash ('/' for the root). */
    public static function path(): string
    {
        $p = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        return '/' . trim($p, '/');
    }

    public static function uri(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    /**
     * The real client IP. X-Forwarded-For is only believed when the direct peer is a trusted proxy,
     * and is read right to left so a client cannot forge its own address.
     */
    public static function ip(): string
    {
        if (self::$ip !== null) return self::$ip;
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $xff = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($xff !== '' && self::trusted($ip)) {
            $parts = array_map('trim', explode(',', $xff));
            for ($i = count($parts) - 1; $i >= 0; $i--) {
                if (filter_var($parts[$i], FILTER_VALIDATE_IP) === false) break;
                $ip = $parts[$i];
                if (!self::trusted($ip)) break;
            }
        }
        return self::$ip = substr($ip, 0, 45);
    }

    private static function trusted(string $ip): bool
    {
        foreach (explode(',', (string) Env::get('TRUSTED_PROXIES', self::DEFAULT_PROXIES)) as $cidr) {
            $cidr = trim($cidr);
            if ($cidr !== '' && self::inCidr($ip, $cidr)) return true;
        }
        return false;
    }

    private static function inCidr(string $ip, string $cidr): bool
    {
        $parts = explode('/', $cidr, 2);
        $a = @inet_pton($ip);
        $b = @inet_pton($parts[0]);
        if ($a === false || $b === false || strlen($a) !== strlen($b)) return false;
        $max = strlen($a) * 8;
        $bits = isset($parts[1]) ? max(0, min($max, (int) $parts[1])) : $max;
        $bytes = intdiv($bits, 8);
        $rem = $bits % 8;
        if ($bytes > 0 && substr($a, 0, $bytes) !== substr($b, 0, $bytes)) return false;
        if ($rem > 0) {
            $mask = (0xFF << (8 - $rem)) & 0xFF;
            if ((ord($a[$bytes]) & $mask) !== (ord($b[$bytes]) & $mask)) return false;
        }
        return true;
    }
}
