<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{DB, Settings};
use App\Services\{Articles, PublicUrl};

/** robots.txt, sitemap.xml and the RSS feed. Each can be switched off in the Tech portal (Settings > Search and feeds). */
final class SeoController
{
    private static function xml(string $s): string { return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

    public static function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        if (Settings::get('seo_indexing', '1') !== '1') {
            echo "User-agent: *\nDisallow: /\n";
            return;
        }
        echo "User-agent: *\n";
        foreach (['/moderator', '/tech', '/account', '/write', '/login', '/logout', '/register', '/forgot', '/reset/', '/verify/', '/auth/', '/dashboard', '/profile'] as $p) echo "Disallow: $p\n";
        if (Settings::get('sitemap_enabled', '1') === '1') echo "\nSitemap: " . abs_url('/sitemap.xml') . "\n";
    }

    public static function sitemap(): void
    {
        if (Settings::get('sitemap_enabled', '1') !== '1' || Settings::get('seo_indexing', '1') !== '1') abort(404);
        $urls = [['/', null], ['/explore', null], ['/authors', null]];
        foreach (DB::all("SELECT slug, updated_at FROM pages WHERE status = 'published' ORDER BY slug") as $p) $urls[] = ['/' . rawurlencode((string) $p['slug']), (string) $p['updated_at']];
        foreach (DB::all("SELECT a.id, a.slug, a.updated_at FROM articles a JOIN users u ON u.id = a.author_id WHERE a.status = 'published' AND u.status = 'active' ORDER BY a.id DESC LIMIT 20000") as $a) {
            $urls[] = [Articles::url($a), (string) $a['updated_at']];
        }
        foreach (['student' => 'students', 'teacher' => 'teachers'] as $role => $kind) {
            if (!PublicUrl::enabled($kind)) continue;
            $rows = DB::all(
                "SELECT u.id, u.uid, u.public_id, u.public_no, u.public_slug, u.role, u.status, p.is_public, p.profile_complete
                   FROM users u JOIN user_profiles p ON p.user_id = u.id
                  WHERE u.role = ? AND u.status = 'active' AND p.is_public = 1 AND p.profile_complete = 1 LIMIT 5000", [$role]
            );
            foreach ($rows as $r) {
                if (PublicUrl::visible($r) && ($path = PublicUrl::path($r)) !== null) $urls[] = [$path, null];
            }
        }
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=900');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as [$path, $mod]) {
            echo '  <url><loc>' . self::xml(abs_url($path)) . '</loc>' . ($mod !== null && strtotime($mod) ? '<lastmod>' . gmdate('Y-m-d', strtotime($mod)) . '</lastmod>' : '') . "</url>\n";
        }
        echo "</urlset>\n";
    }

    public static function feed(): void
    {
        if (Settings::get('feed_enabled', '1') !== '1' || Settings::get('seo_indexing', '1') !== '1') abort(404);
        $n = max(5, min(100, (int) Settings::get('feed_items', '30')));
        $rows = Articles::latest(min(60, $n));
        $self = abs_url('/feed.xml');
        $last = $rows ? (string) $rows[0]['published_at'] : '';
        header('Content-Type: application/rss+xml; charset=utf-8');
        header('Cache-Control: public, max-age=600');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/"><channel>' . "\n";
        echo '<title>' . self::xml(site_name()) . '</title><link>' . self::xml(abs_url('/')) . '</link>';
        echo '<description>' . self::xml(site_tagline() !== '' ? site_tagline() : university_name()) . '</description>';
        echo '<language>' . self::xml(\App\Core\I18n::locale()) . '</language>';
        echo '<atom:link href="' . self::xml($self) . '" rel="self" type="application/rss+xml"/>';
        if ($last !== '' && strtotime($last)) echo '<lastBuildDate>' . gmdate('r', strtotime($last)) . '</lastBuildDate>';
        echo "\n";
        foreach ($rows as $a) {
            $link = abs_url(Articles::url($a));
            echo '<item><title>' . self::xml((string) $a['title']) . '</title><link>' . self::xml($link) . '</link><guid isPermaLink="true">' . self::xml($link) . '</guid>';
            if (strtotime((string) $a['published_at'])) echo '<pubDate>' . gmdate('r', strtotime((string) $a['published_at'])) . '</pubDate>';
            echo '<dc:creator>' . self::xml(display_name($a)) . '</dc:creator>';
            echo '<description>' . self::xml(Articles::excerpt($a, 300)) . '</description></item>' . "\n";
        }
        echo '</channel></rss>' . "\n";
    }
}
