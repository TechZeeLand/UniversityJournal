<?php
declare(strict_types=1);
namespace App\Core;

/**
 * A small, safe Markdown subset for editable pages. Everything is HTML-escaped first, so no raw HTML,
 * script or event handler can get through. Supported: # ## ### headings, paragraphs, **bold**, *italic*,
 * `code`, [links](url), ![images](url), - bullet and 1. numbered lists, > quotes, --- rules, ``` code blocks.
 * Links may point to http(s), mailto, a path on this site (/about) or an #anchor.
 */
final class Markdown
{
    public static function render(string $src): string
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $src));
        $out = [];
        $para = [];
        $list = null;      // ['ul'|'ol', [items]]
        $quote = [];
        $code = null;      // array of lines while inside ```

        $flush = function () use (&$out, &$para, &$list, &$quote): void {
            if ($para) { $out[] = '<p>' . self::inline(implode("\n", $para)) . '</p>'; $para = []; }
            if ($list) {
                $out[] = '<' . $list[0] . '>' . implode('', array_map(fn($i) => '<li>' . self::inline($i) . '</li>', $list[1])) . '</' . $list[0] . '>';
                $list = null;
            }
            if ($quote) { $out[] = '<blockquote><p>' . self::inline(implode("\n", $quote)) . '</p></blockquote>'; $quote = []; }
        };

        foreach ($lines as $line) {
            if ($code !== null) {
                if (preg_match('/^```\s*$/', $line) === 1) {
                    $out[] = '<pre><code>' . e(implode("\n", $code)) . '</code></pre>';
                    $code = null;
                } else {
                    $code[] = $line;
                }
                continue;
            }
            if (preg_match('/^```/', $line) === 1) { $flush(); $code = []; continue; }
            if (trim($line) === '') { $flush(); continue; }
            if (preg_match('/^(#{1,3})\s+(.+?)\s*#*\s*$/u', $line, $m) === 1) {
                $flush();
                $lvl = strlen($m[1]) + 1;      // page title is the h1, so # becomes h2
                $out[] = "<h$lvl>" . self::inline($m[2]) . "</h$lvl>";
                continue;
            }
            if (preg_match('/^\s*([-*_])(\s*\1){2,}\s*$/', $line) === 1) { $flush(); $out[] = '<hr>'; continue; }
            if (preg_match('/^>\s?(.*)$/', $line, $m) === 1) {
                if ($para || $list) $flush();
                $quote[] = $m[1];
                continue;
            }
            if (preg_match('/^\s*[-*]\s+(.+)$/u', $line, $m) === 1) {
                if ($para || $quote || ($list && $list[0] !== 'ul')) $flush();
                $list ??= ['ul', []];
                $list[1][] = $m[1];
                continue;
            }
            if (preg_match('/^\s*\d+[.)]\s+(.+)$/u', $line, $m) === 1) {
                if ($para || $quote || ($list && $list[0] !== 'ol')) $flush();
                $list ??= ['ol', []];
                $list[1][] = $m[1];
                continue;
            }
            if ($list || $quote) $flush();
            $para[] = trim($line);
        }
        if ($code !== null) $out[] = '<pre><code>' . e(implode("\n", $code)) . '</code></pre>';
        $flush();
        return implode("\n", $out);
    }

    /** Inline formatting on one block of text. */
    private static function inline(string $s): string
    {
        $s = e($s);
        $stash = [];
        $hold = function (string $html) use (&$stash): string {
            $stash[] = $html;
            return "\x01" . (count($stash) - 1) . "\x02";
        };
        // code spans first so nothing inside them is formatted
        $s = preg_replace_callback('/`([^`]+)`/', fn($m) => $hold('<code>' . $m[1] . '</code>'), $s) ?? $s;
        // images, then links
        $s = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)\)/', function ($m) use ($hold) {
            return self::safeUrl($m[2], true) ? $hold('<img src="' . $m[2] . '" alt="' . $m[1] . '" loading="lazy">') : $m[0];
        }, $s) ?? $s;
        $s = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function ($m) use ($hold) {
            if (!self::safeUrl($m[2], false)) return $m[1];
            $ext = preg_match('#^https?://#i', $m[2]) === 1;
            return $hold('<a href="' . $m[2] . '"' . ($ext ? ' rel="noopener" target="_blank"' : '') . '>' . $m[1] . '</a>');
        }, $s) ?? $s;
        $s = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s) ?? $s;
        $s = preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/s', '<em>$1</em>', $s) ?? $s;
        $s = str_replace("\n", "<br>\n", $s);
        return preg_replace_callback('/\x01(\d+)\x02/', fn($m) => $stash[(int) $m[1]] ?? '', $s) ?? $s;
    }

    /** $u is already HTML-escaped. Allowed: http(s), mailto (links only), /path, #anchor. */
    private static function safeUrl(string $u, bool $image): bool
    {
        if (preg_match('#^https?://#i', $u) === 1) return true;
        if (!$image && preg_match('#^mailto:[^\s]+$#i', $u) === 1) return true;
        if (!$image && preg_match('/^#[\w-]+$/', $u) === 1) return true;
        return preg_match('#^/(?!/)#', $u) === 1;
    }
}
