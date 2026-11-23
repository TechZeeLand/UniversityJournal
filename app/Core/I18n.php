<?php
declare(strict_types=1);
namespace App\Core;

/** Interface languages: English and Bangla. (Article content language is separate: en / bn / ar.) */
final class I18n
{
    public const LOCALES = ['en' => 'English', 'bn' => 'বাংলা'];
    private static string $locale = 'en';
    private static array $strings = [];
    private static array $fallback = [];

    public static function init(): void
    {
        $c = $_COOKIE['lang'] ?? '';
        $default = Settings::get('default_lang', 'en');
        self::$locale = isset(self::LOCALES[$c]) ? $c : (isset(self::LOCALES[$default]) ? $default : 'en');
        self::$fallback = require APP_PATH . '/lang/en.php';
        self::$strings = self::$locale === 'en' ? self::$fallback : (require APP_PATH . '/lang/' . self::$locale . '.php');
    }

    public static function locale(): string { return self::$locale; }

    public static function set(string $code): bool
    {
        if (!isset(self::LOCALES[$code])) return false;
        setcookie('lang', $code, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax']);
        return true;
    }

    public static function t(string $key, array $repl = []): string
    {
        $s = self::$strings[$key] ?? self::$fallback[$key] ?? $key;
        foreach ($repl as $k => $v) {
            $s = str_replace('{' . $k . '}', (string) $v, $s);
        }
        return $s;
    }
}
