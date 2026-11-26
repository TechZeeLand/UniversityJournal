<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{DB, I18n};

/** Editable pages (about, contact, privacy, terms and your own), written in Markdown in Tech portal > Site content > Pages. */
final class Pages
{
    /** Addresses that already mean something else and cannot be used for a page. */
    public const RESERVED = [
        'login', 'logout', 'forgot', 'dashboard', 'profile', 'account', 'moderator', 'tech', 'lang', 'theme.css', 'health',
        'assets', 'uploads', 'students', 'teachers', 'authors', 'explore', 'departments', 'collections', 'community',
        'advertise', 'tzl-fonts', 'icons-cdn', 'index.php', 'robots.txt', 'sitemap.xml', 'favicon.ico', 'api', 'admin',
    ];

    public static function validSlug(string $s): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9-]{1,58}[a-z0-9]$/', $s) === 1;
    }

    public static function all(): array
    {
        return DB::all('SELECT id, slug, title_en, title_bn, status, is_system, updated_at FROM pages ORDER BY is_system DESC, slug');
    }

    public static function byId(int $id): ?array
    {
        return DB::one('SELECT * FROM pages WHERE id = ?', [$id]);
    }

    public static function bySlug(string $slug, bool $publishedOnly = true): ?array
    {
        return DB::one('SELECT * FROM pages WHERE slug = ?' . ($publishedOnly ? " AND status = 'published'" : ''), [$slug]);
    }

    public static function slugTaken(string $slug, int $exceptId = 0): bool
    {
        return in_array($slug, self::RESERVED, true) || DB::val('SELECT 1 FROM pages WHERE slug = ? AND id <> ?', [$slug, $exceptId]) !== null;
    }

    /** Title in the visitor's language (Bangla when available), else English. */
    public static function title(array $p): string
    {
        $bn = trim((string) ($p['title_bn'] ?? ''));
        return (I18n::locale() === 'bn' && $bn !== '') ? $bn : (string) $p['title_en'];
    }

    public static function body(array $p): string
    {
        $bn = trim((string) ($p['body_bn'] ?? ''));
        return (I18n::locale() === 'bn' && $bn !== '') ? $bn : (string) ($p['body_en'] ?? '');
    }

    /** Fill {{site_name}}, {{university_name}}, {{contact_email}}, {{contact_phone}}, {{contact_address}}, {{year}}. A line whose value is empty is left out. */
    public static function expand(string $md): string
    {
        $vals = [
            'site_name' => site_name(),
            'university_name' => university_name(),
            'contact_email' => trim((string) setting('contact_email', '')),
            'contact_phone' => trim((string) setting('contact_phone', '')),
            'contact_address' => trim(preg_replace('/\s*\R\s*/u', ', ', (string) setting('contact_address', '')) ?? ''),
            'year' => date('Y'),
        ];
        $out = [];
        foreach (explode("\n", str_replace("\r\n", "\n", $md)) as $line) {
            $skip = false;
            $line = preg_replace_callback('/\{\{(\w+)\}\}/', function (array $m) use ($vals, &$skip): string {
                if (!array_key_exists($m[1], $vals)) return $m[0];
                if ($vals[$m[1]] === '') $skip = true;
                return $vals[$m[1]];
            }, $line) ?? $line;
            if (!$skip) $out[] = $line;
        }
        return implode("\n", $out);
    }
}
