<?php
declare(strict_types=1);
namespace App\Core;

final class Env
{
    public static function get(string $key, ?string $default = null): ?string
    {
        $v = getenv($key);
        return ($v === false || $v === '') ? $default : $v;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        return $v === null ? $default : in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
    }
}
