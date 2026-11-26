<?php
declare(strict_types=1);
namespace App\Core;

/**
 * /theme.css: everything the Tech portal can change about the look (fonts and brand colours).
 * app.css holds the defaults; this file is loaded after it, so only the chosen values override them.
 */
final class Theme
{
    public const DEFAULT_PRIMARY = '#0f6b5c';
    public const DEFAULT_PRIMARY_DARK = '#4fc3ab';

    public static function validColor(string $c): bool
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $c) === 1;
    }

    private static function color(string $key, string $default): string
    {
        $c = strtolower(trim((string) Settings::get($key, $default)));
        return self::validColor($c) ? $c : $default;
    }

    public static function primary(): string { return self::color('brand_primary', self::DEFAULT_PRIMARY); }
    public static function primaryDark(): string { return self::color('brand_primary_dark', self::DEFAULT_PRIMARY_DARK); }

    /** Text colour that stays readable on top of the given background (black or white). */
    public static function ink(string $hex): string
    {
        $lin = static function (int $v): float {
            $c = $v / 255;
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        $l = 0.2126 * $lin(hexdec(substr($hex, 1, 2))) + 0.7152 * $lin(hexdec(substr($hex, 3, 2))) + 0.0722 * $lin(hexdec(substr($hex, 5, 2)));
        // contrast against white = 1.05/(l+0.05), against black = (l+0.05)/0.05
        return (1.05 / ($l + 0.05)) >= (($l + 0.05) / 0.05) ? '#ffffff' : '#111111';
    }

    public static function css(): string
    {
        $p = self::primary();
        $d = self::primaryDark();
        return Fonts::themeCss()
            . ":root{--primary:$p;--primary-ink:" . self::ink($p) . ";--primary-soft:color-mix(in srgb,$p 14%,#ffffff)}\n"
            . "[data-theme=\"dark\"]{--primary:$d;--primary-ink:" . self::ink($d) . ";--primary-soft:color-mix(in srgb,$d 20%,#1b1f24)}\n";
    }

    public static function version(): string
    {
        return substr(md5(Fonts::cssUrl() . self::css()), 0, 8);
    }
}
