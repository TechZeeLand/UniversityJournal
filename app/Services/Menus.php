<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{DB, I18n};

/** Header and footer links, edited in Tech portal > Site content > Menus. */
final class Menus
{
    public const LOCATIONS = ['header', 'footer'];
    public const MAX_PER_LOCATION = 12;
    private static array $cache = [];

    /** Visible links of one menu, labelled in the visitor's language. @return list<array{label:string,url:string}> */
    public static function items(string $location): array
    {
        if (!isset(self::$cache[$location])) {
            $out = [];
            try {
                foreach (DB::all('SELECT label_en, label_bn, url FROM menu_items WHERE location = ? AND visible = 1 ORDER BY sort_order, id', [$location]) as $r) {
                    $out[] = ['label' => self::label($r), 'url' => (string) $r['url']];
                }
            } catch (\Throwable $e) {
                // table missing before the migration ran: show no menu rather than an error
            }
            self::$cache[$location] = $out;
        }
        return self::$cache[$location];
    }

    public static function label(array $r): string
    {
        $bn = trim((string) ($r['label_bn'] ?? ''));
        return (I18n::locale() === 'bn' && $bn !== '') ? $bn : (string) $r['label_en'];
    }

    /** All rows of one menu for the editor (hidden ones too). */
    public static function all(string $location): array
    {
        return DB::all('SELECT id, label_en, label_bn, url, sort_order, visible FROM menu_items WHERE location = ? ORDER BY sort_order, id', [$location]);
    }

    /** A path on this site (/about), a web address, or a mail link. */
    public static function validUrl(string $u): bool
    {
        if ($u === '' || strlen($u) > 255 || preg_match('/\s/', $u) === 1) return false;
        if (preg_match('#^/(?!/)#', $u) === 1) return true;
        if (preg_match('#^mailto:[^\s@]+@[^\s@]+$#i', $u) === 1) return true;
        return preg_match('#^https?://#i', $u) === 1 && filter_var($u, FILTER_VALIDATE_URL) !== false;
    }

    public static function external(string $u): bool
    {
        return preg_match('#^https?://#i', $u) === 1;
    }
}
