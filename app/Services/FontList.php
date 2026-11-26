<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{Fonts, Paths};

/**
 * Names of the fonts available from the fonts service, for the pickers in Tech portal > Settings > Fonts.
 * Read through this site's own cached mirror and remembered for a day. The picker also accepts typed names,
 * so it keeps working if the list cannot be read.
 */
final class FontList
{
    private const BUILTIN = [
        'Open Sans', 'Young Serif', 'Noto Sans Bengali', 'Noto Naskh Arabic', 'Noto Serif Bengali', 'Noto Sans Arabic',
        'Noto Kufi Arabic', 'Hind Siliguri', 'Tiro Bangla', 'Inter', 'Roboto', 'Lora', 'Merriweather', 'Playfair Display',
    ];

    /** @return string[] */
    public static function names(): array
    {
        $cache = Paths::runtime('fonts-list.json');
        if (is_file($cache) && filemtime($cache) > time() - 86400) {
            $c = json_decode((string) file_get_contents($cache), true);
            if (is_array($c) && $c) return $c;
        }
        $names = self::fetch();
        if ($names) {
            Paths::ensure(Paths::runtime());
            @file_put_contents($cache, json_encode($names, JSON_UNESCAPED_UNICODE));
            return $names;
        }
        return self::BUILTIN;
    }

    /** @return string[] */
    private static function fetch(): array
    {
        if (!function_exists('curl_init')) return [];
        $ch = curl_init('http://127.0.0.1/tzl-fonts/api/fonts.json');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 4, CURLOPT_CONNECTTIMEOUT => 2]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!is_string($body) || $code !== 200) return [];
        $j = json_decode($body, true);
        if (!is_array($j)) return [];
        $list = isset($j['fonts']) && is_array($j['fonts']) ? $j['fonts'] : $j;
        $out = [];
        foreach ($list as $k => $item) {
            $name = null;
            if (is_string($item)) $name = $item;
            elseif (is_array($item)) $name = $item['name'] ?? $item['family'] ?? $item['title'] ?? $item['id'] ?? null;
            elseif (is_string($k)) $name = $k;
            if (is_string($name)) {
                $name = Fonts::clean($name);
                if ($name !== '') $out[$name] = true;
            }
        }
        $names = array_keys($out);
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);
        return array_slice($names, 0, 2000);
    }
}
