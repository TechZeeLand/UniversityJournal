<?php
declare(strict_types=1);
namespace App\Core;

/** Key/value site settings stored in the DB (edited from the Tech portal). */
final class Settings
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                foreach (DB::all('SELECT k, v FROM settings') as $r) {
                    self::$cache[$r['k']] = $r['v'];
                }
            } catch (\Throwable $e) {
                // DB not ready: fall back to defaults so the site still renders.
            }
        }
        return self::$cache;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::all()[$key] ?? $default;
    }

    public static function set(string $key, string $value, string $group = 'general'): void
    {
        DB::exec(
            'INSERT INTO settings (k, v, grp) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v), grp = VALUES(grp)',
            [$key, $value, $group]
        );
        self::$cache = null;
    }
}
