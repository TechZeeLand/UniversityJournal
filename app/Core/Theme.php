<?php
declare(strict_types=1);
namespace App\Core;

/**
 * /theme.css: everything the Tech portal can change about the look (fonts and every colour).
 * app.css holds the built-in defaults; /theme.css is loaded after it, so only the chosen values override them.
 *
 * A palette is worked out in PHP (never in the browser) from a few choices, for the light and the dark theme separately:
 *   main colour  ->  buttons, links, highlights; the text colour on top of it is picked automatically
 *   accent       ->  decorative second colour (optional, otherwise derived from the main colour)
 *   tint         ->  how much of the main colour is mixed into the greys (background, cards, borders, muted text)
 *   overrides    ->  any single surface, text or status colour can be fixed by hand (optional)
 * Everything the site draws uses these CSS variables, so a change reaches the whole site.
 */
final class Theme
{
    public const DEFAULT_PRIMARY = '#0f6b5c';
    public const DEFAULT_PRIMARY_DARK = '#4fc3ab';

    /** The built-in look: what the site shows until something is configured. */
    private const BASE = [
        'light' => ['bg' => '#faf9f6', 'surface' => '#ffffff', 'surface2' => '#f1efe9', 'text' => '#1c1b19', 'muted' => '#625f58', 'border' => '#e3e0d8',
                    'danger' => '#b42318', 'danger_soft' => '#fde8e6', 'warn' => '#8a4b08', 'warn_soft' => '#fdf0d5'],
        'dark'  => ['bg' => '#13161a', 'surface' => '#1b1f24', 'surface2' => '#232830', 'text' => '#ecebe7', 'muted' => '#a4a8ae', 'border' => '#2c323a',
                    'danger' => '#ff8a80', 'danger_soft' => '#3a1d1b', 'warn' => '#f5c26b', 'warn_soft' => '#3a2f17'],
    ];

    /** Percent of the main colour mixed into each grey, per tint level. */
    private const TINT = [
        'tint_none'   => [],
        'tint_subtle' => ['bg' => 3, 'surface' => 2, 'surface2' => 7, 'border' => 9, 'muted' => 10, 'text' => 5],
        'tint_medium' => ['bg' => 6, 'surface' => 4, 'surface2' => 13, 'border' => 16, 'muted' => 18, 'text' => 9],
        'tint_strong' => ['bg' => 10, 'surface' => 7, 'surface2' => 20, 'border' => 24, 'muted' => 26, 'text' => 13],
    ];
    public const TINT_LEVELS = ['tint_none', 'tint_subtle', 'tint_medium', 'tint_strong'];

    /** Ready-made palettes: name => [light main colour, dark main colour]. Picking one only fills the two main colours. */
    public const PRESETS = [
        'teal'    => ['#0f6b5c', '#4fc3ab'],
        'ocean'   => ['#0b5cad', '#6cb4ff'],
        'indigo'  => ['#3f46b5', '#9aa2ff'],
        'violet'  => ['#6d32b0', '#c09cff'],
        'rose'    => ['#c2255c', '#ff8fb3'],
        'crimson' => ['#a8231b', '#ff8f85'],
        'orange'  => ['#b84d0a', '#ffa05c'],
        'amber'   => ['#8f5f00', '#f2bf4d'],
        'forest'  => ['#2f7d32', '#7ed67f'],
        'slate'   => ['#3d4a5c', '#aab7c9'],
    ];

    /** Every setting the palette reads, with its default ('' = automatic). */
    public const KEYS = [
        'brand_primary' => self::DEFAULT_PRIMARY, 'brand_primary_dark' => self::DEFAULT_PRIMARY_DARK,
        'theme_accent' => '', 'theme_accent_dark' => '', 'theme_tint' => 'tint_none',
        'theme_bg' => '', 'theme_bg_dark' => '', 'theme_surface' => '', 'theme_surface_dark' => '',
        'theme_text' => '', 'theme_text_dark' => '', 'theme_muted' => '', 'theme_muted_dark' => '',
        'theme_border' => '', 'theme_border_dark' => '',
        'theme_danger' => '', 'theme_danger_dark' => '', 'theme_warn' => '', 'theme_warn_dark' => '',
    ];

    // ---- colour maths -------------------------------------------------------------------------------

    public static function validColor(string $c): bool
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $c) === 1;
    }

    /** @return array{0:int,1:int,2:int} */
    private static function rgb(string $hex): array
    {
        return [(int) hexdec(substr($hex, 1, 2)), (int) hexdec(substr($hex, 3, 2)), (int) hexdec(substr($hex, 5, 2))];
    }

    private static function toHex(float $r, float $g, float $b): string
    {
        $c = static fn(float $x): int => max(0, min(255, (int) round($x)));
        return sprintf('#%02x%02x%02x', $c($r), $c($g), $c($b));
    }

    /** $pctA percent of $a, the rest of $b. */
    public static function mix(string $a, string $b, float $pctA): string
    {
        if ($pctA <= 0) return strtolower($b);
        [$ar, $ag, $ab] = self::rgb($a);
        [$br, $bg, $bb] = self::rgb($b);
        $t = min(100.0, $pctA) / 100;
        return self::toHex($ar * $t + $br * (1 - $t), $ag * $t + $bg * (1 - $t), $ab * $t + $bb * (1 - $t));
    }

    /** Hue in degrees (0 to 359). */
    public static function hue(string $hex): int
    {
        [$r, $g, $b] = array_map(static fn(int $x): float => $x / 255, self::rgb($hex));
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $d = $max - $min;
        if ($d < 0.00001) return 0;
        if ($max === $r) $h = fmod(($g - $b) / $d, 6.0);
        elseif ($max === $g) $h = (($b - $r) / $d) + 2;
        else $h = (($r - $g) / $d) + 4;
        $h *= 60;
        if ($h < 0) $h += 360;
        return ((int) round($h)) % 360;
    }

    private static function hsl(float $h, float $s, float $l): string
    {
        $h = fmod(fmod($h, 360.0) + 360.0, 360.0) / 360;
        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        $f = static function (float $t) use ($p, $q): float {
            if ($t < 0) $t += 1;
            if ($t > 1) $t -= 1;
            if ($t < 1 / 6) return $p + ($q - $p) * 6 * $t;
            if ($t < 1 / 2) return $q;
            if ($t < 2 / 3) return $p + ($q - $p) * (2 / 3 - $t) * 6;
            return $p;
        };
        return self::toHex($f($h + 1 / 3) * 255, $f($h) * 255, $f($h - 1 / 3) * 255);
    }

    public static function luminance(string $hex): float
    {
        $lin = static function (int $v): float {
            $c = $v / 255;
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        [$r, $g, $b] = self::rgb($hex);
        return 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);
    }

    /** WCAG contrast ratio between two colours (1 to 21). */
    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);
        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /** Text colour that stays readable on top of the given background (black or white). */
    public static function ink(string $hex): string
    {
        return self::contrast($hex, '#ffffff') >= self::contrast($hex, '#111111') ? '#ffffff' : '#111111';
    }

    // ---- the palette ----------------------------------------------------------------------------------

    /** A valid colour from $v[$key], or null when empty or invalid (= automatic). */
    private static function opt(array $v, string $key): ?string
    {
        $c = strtolower(trim((string) ($v[$key] ?? '')));
        return self::validColor($c) ? $c : null;
    }

    /** All the theme settings as saved. @return array<string,string> */
    public static function values(): array
    {
        $out = [];
        foreach (self::KEYS as $k => $d) $out[$k] = (string) Settings::get($k, $d);
        return $out;
    }

    public static function primary(): string { return self::opt(self::values(), 'brand_primary') ?? self::DEFAULT_PRIMARY; }
    public static function primaryDark(): string { return self::opt(self::values(), 'brand_primary_dark') ?? self::DEFAULT_PRIMARY_DARK; }

    /**
     * The finished colours of one theme.
     * @param array<string,string> $v theme settings (see KEYS); anything missing falls back to the default
     * @return array<string,string>
     */
    public static function resolve(array $v, bool $dark): array
    {
        $suf = $dark ? '_dark' : '';
        $base = self::BASE[$dark ? 'dark' : 'light'];
        $primary = self::opt($v, 'brand_primary' . $suf) ?? ($dark ? self::DEFAULT_PRIMARY_DARK : self::DEFAULT_PRIMARY);
        $level = (string) ($v['theme_tint'] ?? 'tint_none');
        $tint = self::TINT[$level] ?? [];

        $p = ['primary' => $primary];
        $hand = false;                                  // did the admin fix a background, surface or text colour by hand?
        foreach (['bg', 'surface', 'text', 'muted', 'border'] as $k) {
            $o = self::opt($v, 'theme_' . $k . $suf);
            if ($o !== null && in_array($k, ['bg', 'surface', 'text'], true)) $hand = true;
            $p[$k] = $o ?? self::mix($primary, $base[$k], (float) ($tint[$k] ?? 0));
        }
        $p['surface2'] = $hand ? self::mix($p['text'], $p['bg'], $dark ? 7 : 5) : self::mix($primary, $base['surface2'], (float) ($tint['surface2'] ?? 0));

        $p['primary_ink'] = self::ink($primary);
        $p['primary_soft'] = self::mix($primary, $p['surface'], $dark ? 20 : 14);

        // accent: chosen, else a neighbour of the main colour on the colour wheel; a second one sits on the other side
        $accent = self::opt($v, 'theme_accent' . $suf);
        $h = (float) self::hue($primary);
        $p['accent'] = $accent ?? self::hsl($h + 35, 0.65, $dark ? 0.64 : 0.52);
        $p['accent2'] = self::hsl((float) self::hue($p['accent']) - 80, 0.65, $dark ? 0.66 : 0.55);

        foreach (['danger', 'warn'] as $k) {
            $o = self::opt($v, 'theme_' . $k . $suf);
            $p[$k] = $o ?? $base[$k];
            $p[$k . '_soft'] = $o !== null ? self::mix($o, $p['surface'], $dark ? 20 : 12) : $base[$k . '_soft'];
        }
        $p['danger_ink'] = self::ink($p['danger']);
        return $p;
    }

    private static function decl(array $p, int $hue, string $darkSurface): string
    {
        return '--bg:' . $p['bg'] . ';--surface:' . $p['surface'] . ';--surface-2:' . $p['surface2'] . ';--text:' . $p['text']
            . ';--muted:' . $p['muted'] . ';--border:' . $p['border'] . ';--primary:' . $p['primary'] . ';--primary-ink:' . $p['primary_ink']
            . ';--primary-soft:' . $p['primary_soft'] . ';--accent:' . $p['accent'] . ';--accent-2:' . $p['accent2']
            . ';--danger:' . $p['danger'] . ';--danger-soft:' . $p['danger_soft'] . ';--danger-ink:' . $p['danger_ink']
            . ';--warn:' . $p['warn'] . ';--warn-soft:' . $p['warn_soft'] . ';--brand-h:' . $hue . ';--dark-surface:' . $darkSurface;
    }

    /** The CSS variable blocks for light and dark, with the selectors the caller wants. */
    public static function varsCss(array $v, string $lightSel = ':root,[data-theme="light"]', string $darkSel = '[data-theme="dark"]'): string
    {
        $l = self::resolve($v, false);
        $d = self::resolve($v, true);
        $hue = self::hue($l['primary']);
        return $lightSel . '{' . self::decl($l, $hue, $d['surface']) . "}\n" . $darkSel . '{' . self::decl($d, $hue, $d['surface']) . "}\n";
    }

    /** The variable lists as "--a:b;--c:d" strings for the live preview boxes (applied with style=""; a <style> tag would be blocked). @return array{light:string,dark:string} */
    public static function previewDecls(array $v): array
    {
        $l = self::resolve($v, false);
        $d = self::resolve($v, true);
        $hue = self::hue($l['primary']);
        return ['light' => self::decl($l, $hue, $d['surface']), 'dark' => self::decl($d, $hue, $d['surface'])];
    }

    /** What each optional colour shows while it is "automatic": key => hex. @return array<string,string> */
    public static function autoShown(array $v): array
    {
        $map = ['theme_accent' => 'accent', 'theme_bg' => 'bg', 'theme_surface' => 'surface', 'theme_text' => 'text', 'theme_muted' => 'muted',
                'theme_border' => 'border', 'theme_danger' => 'danger', 'theme_warn' => 'warn'];
        $l = self::resolve($v, false);
        $d = self::resolve($v, true);
        $out = [];
        foreach ($map as $k => $f) { $out[$k] = $l[$f]; $out[$k . '_dark'] = $d[$f]; }
        return $out;
    }

    public static function css(): string
    {
        return Fonts::themeCss() . self::varsCss(self::values());
    }

    public static function version(): string
    {
        return substr(md5(Fonts::cssUrl() . self::css()), 0, 8);
    }

    /**
     * Readability checks for the Tech portal: [label key, theme, ratio, passes 4.5:1].
     * @return list<array{0:string,1:string,2:float,3:bool}>
     */
    public static function checks(array $v): array
    {
        $rows = [];
        foreach ([false, true] as $dark) {
            $p = self::resolve($v, $dark);
            $name = $dark ? 'dark' : 'light';
            foreach ([['text', 'text', 'bg'], ['muted', 'muted', 'bg'], ['btn', 'primary_ink', 'primary'], ['link', 'primary', 'bg'], ['card', 'text', 'surface']] as [$label, $fg, $bg]) {
                $r = self::contrast($p[$fg], $p[$bg]);
                $rows[] = ['theme.chk_' . $label, $name, round($r, 1), $r >= 4.5];
            }
        }
        return $rows;
    }

    /** The built-in logo in the main colour (used until a logo is uploaded). */
    public static function logoSvg(): string
    {
        $p = self::primary();
        $i = self::ink($p);
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" fill="none"><rect width="32" height="32" rx="8" fill="' . $p . '"/>'
            . '<path d="M9 8h10a4 4 0 0 1 4 4v12H13a4 4 0 0 1-4-4V8z" stroke="' . $i . '" stroke-width="2" stroke-linejoin="round"/>'
            . '<path d="M13 13h6M13 17h6" stroke="' . $i . '" stroke-width="2" stroke-linecap="round"/></svg>';
    }
}
