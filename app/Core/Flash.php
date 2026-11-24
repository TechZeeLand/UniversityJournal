<?php
declare(strict_types=1);
namespace App\Core;

/** One-time messages shown on the next page, plus one-time "secret" values (temporary passwords). */
final class Flash
{
    /** @param string $type ok | error | info | warn */
    public static function add(string $type, string $msg): void
    {
        $_SESSION['_flash'][] = ['t' => $type, 'm' => $msg];
    }

    public static function pull(): array
    {
        $f = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return is_array($f) ? $f : [];
    }

    /** A temporary password to show once to an admin. */
    public static function putSecret(array $s): void
    {
        $_SESSION['_secret'] = $s + ['at' => time()];
    }

    public static function pullSecret(): ?array
    {
        $s = $_SESSION['_secret'] ?? null;
        unset($_SESSION['_secret']);
        if (!is_array($s) || time() - (int) ($s['at'] ?? 0) > 900) return null;
        return $s;
    }
}
