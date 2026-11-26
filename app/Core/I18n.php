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
    /** Texts edited in the Tech portal (Site content > Texts): [locale => [key => text]]. They win over the language files. */
    private static array $ovr = [];
    private static array $files = [];

    public static function init(): void
    {
        $c = $_COOKIE['lang'] ?? '';
        $default = Settings::get('default_lang', 'en');
        self::$locale = isset(self::LOCALES[$c]) ? $c : (isset(self::LOCALES[$default]) ? $default : 'en');
        self::$fallback = self::load('en');
        self::$strings = self::$locale === 'en' ? self::$fallback : self::load(self::$locale);
        self::$ovr = self::loadOverrides(array_values(array_unique([self::$locale, 'en'])));
    }

    /** @param string[] $locales */
    private static function loadOverrides(array $locales): array
    {
        $out = ['en' => [], 'bn' => []];
        try {
            $in = implode(',', array_fill(0, count($locales), '?'));
            foreach (DB::all("SELECT locale, k, v FROM text_overrides WHERE locale IN ($in)", $locales) as $r) {
                $out[$r['locale']][$r['k']] = (string) $r['v'];
            }
        } catch (\Throwable $e) {
            // table not created yet or database not ready: use the language files only
        }
        return $out;
    }

    /** The built-in texts of a language, without Tech portal edits (the editor shows these as the defaults). */
    public static function defaults(string $loc): array
    {
        return self::$files[$loc] ??= (isset(self::LOCALES[$loc]) ? self::load($loc) : []);
    }

    /** lang/<loc>.php plus every lang/<loc>/*.php, merged. */
    private static function load(string $loc): array
    {
        $s = require APP_PATH . '/lang/' . $loc . '.php';
        $files = glob(APP_PATH . '/lang/' . $loc . '/*.php') ?: [];
        sort($files);
        foreach ($files as $f) {
            $s = array_merge($s, require $f);
        }
        return $s;
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
        $s = self::$ovr[self::$locale][$key] ?? self::$strings[$key] ?? self::$ovr['en'][$key] ?? self::$fallback[$key] ?? $key;
        foreach ($repl as $k => $v) {
            $s = str_replace('{' . $k . '}', (string) $v, $s);
        }
        return $s;
    }
}
