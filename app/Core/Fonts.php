<?php
declare(strict_types=1);
namespace App\Core;

/**
 * Font settings come from the Tech portal (settings table). Fonts are loaded from the
 * TechZeeLand fonts service through this site's own cached mirror (/tzl-fonts/).
 * Script fallback works per glyph: Latin font first, then Bangla, then Arabic.
 */
final class Fonts
{
    private const DEFAULTS = [
        'font_body' => 'Open Sans',
        'font_heading' => 'Young Serif',
        'font_bn' => 'Noto Sans Bengali',
        'font_ar' => 'Noto Naskh Arabic',
        'font_weights_body' => '400;700',
        'font_weights_heading' => '400',
    ];

    public static function clean(string $name): string
    {
        return trim(preg_replace('/[^A-Za-z0-9 \-]/', '', $name));
    }

    private static function get(string $k): string
    {
        $v = self::clean((string) Settings::get($k, self::DEFAULTS[$k]));
        return $v !== '' ? $v : self::DEFAULTS[$k];
    }

    private static function weights(string $k): array
    {
        $raw = (string) Settings::get($k, self::DEFAULTS[$k]);
        $w = array_filter(array_map('intval', explode(';', $raw)), fn($n) => $n >= 100 && $n <= 900);
        $w = array_values(array_unique($w));
        sort($w);
        return $w ?: [400];
    }

    /** URL of the (cached) fonts stylesheet for the configured families. */
    public static function cssUrl(): string
    {
        $fam = [];
        $add = function (string $name, array $weights) use (&$fam): void {
            $fam[$name] = array_values(array_unique(array_merge($fam[$name] ?? [], $weights)));
            sort($fam[$name]);
        };
        $add(self::get('font_body'), self::weights('font_weights_body'));
        $add(self::get('font_heading'), self::weights('font_weights_heading'));
        $add(self::get('font_bn'), [400, 700]);
        $add(self::get('font_ar'), [400, 700]);

        $parts = [];
        foreach ($fam as $name => $w) {
            $parts[] = 'family=' . str_replace(' ', '+', $name) . ':wght@' . implode(';', $w);
        }
        return '/tzl-fonts/css2?' . implode('&', $parts) . '&display=swap';
    }

    /** CSS custom properties served at /theme.css (more branding variables can be added here later). */
    public static function themeCss(): string
    {
        $b = self::get('font_body'); $h = self::get('font_heading');
        $bn = self::get('font_bn'); $ar = self::get('font_ar');
        $tail = "system-ui,-apple-system,'Segoe UI',Roboto,'Noto Sans',Arial,sans-serif";
        return ":root{\n"
            . "  --font-body:'$b','$bn','$ar',$tail;\n"
            . "  --font-heading:'$h','$bn','$ar',Georgia,'Times New Roman',serif;\n"
            . "  --font-bn-stack:'$bn','$b',$tail;\n"
            . "  --font-ar-stack:'$ar','$b',$tail;\n"
            . "}\n";
    }

    public static function version(): string
    {
        return substr(md5(self::cssUrl() . self::themeCss()), 0, 8);
    }
}
