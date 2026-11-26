<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{DB, I18n};

/**
 * Editable interface texts. The language files hold the defaults; a text saved here (Tech portal > Site content > Texts)
 * replaces it for that language. Clearing a box restores the default.
 */
final class Texts
{
    /** Editor sections and the text keys they cover (by prefix). */
    public const GROUPS = [
        'home' => ['hero.', 'feat.'],
        'footer' => ['footer.'],
        'people' => ['pub.'],
        'signin' => ['auth.'],
        'errors' => ['error.', 'soon.'],
    ];
    public const LOCALES = ['en', 'bn'];
    public const MAX_LEN = 1000;

    /** @return string[] keys (sorted by the order of the English language files) */
    public static function keys(string $group): array
    {
        $prefixes = self::GROUPS[$group] ?? [];
        $out = [];
        foreach (array_keys(I18n::defaults('en')) as $k) {
            foreach ($prefixes as $p) {
                if (str_starts_with((string) $k, $p)) { $out[] = (string) $k; break; }
            }
        }
        return $out;
    }

    /** @return array<string, array<string,string>> [locale => [key => text]] */
    public static function overrides(): array
    {
        $out = ['en' => [], 'bn' => []];
        foreach (DB::all('SELECT locale, k, v FROM text_overrides') as $r) {
            if (isset($out[$r['locale']])) $out[$r['locale']][$r['k']] = (string) $r['v'];
        }
        return $out;
    }

    /**
     * Save one section. $post['t'][locale][key]. Empty or equal to the default = no override.
     * @return array{0:int,1:array<string,string>} number of changes, errors by "locale:key"
     */
    public static function save(string $group, array $post): array
    {
        $keys = self::keys($group);
        $current = self::overrides();
        $in = isset($post['t']) && is_array($post['t']) ? $post['t'] : [];
        $errors = [];
        $changes = 0;
        foreach (self::LOCALES as $loc) {
            $defaults = I18n::defaults($loc);
            foreach ($keys as $k) {
                $raw = $in[$loc][$k] ?? '';
                $v = is_string($raw) ? trim(str_replace("\r", '', $raw)) : '';
                if (mb_strlen($v) > self::MAX_LEN) { $errors[$loc . ':' . $k] = t('set.err_long', ['max' => self::MAX_LEN]); continue; }
                $have = $current[$loc][$k] ?? null;
                if ($v === '' || $v === ($defaults[$k] ?? null)) {
                    if ($have !== null) { DB::exec('DELETE FROM text_overrides WHERE locale = ? AND k = ?', [$loc, $k]); $changes++; }
                } elseif ($v !== $have) {
                    DB::exec('INSERT INTO text_overrides (locale, k, v) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$loc, $k, $v]);
                    $changes++;
                }
            }
        }
        return [$changes, $errors];
    }
}
