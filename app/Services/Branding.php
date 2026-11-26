<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{Paths, Settings};

/**
 * Logo and icons, uploaded in Tech portal > Settings > Branding. Files live in data/uploads/public/branding/
 * and are served at /uploads/branding/... Pictures are re-encoded (metadata and hidden payloads removed);
 * SVG files are cleaned (scripts, event handlers and outside references are removed).
 *   logo       header logo (light theme)      logo_dark  optional header logo for the dark theme
 *   icon       square site icon / favicon     og         picture shown when a link is shared
 */
final class Branding
{
    public const SLOTS = ['logo', 'logo_dark', 'icon', 'og'];

    private const CFG = [
        'logo'      => ['svg' => true,  'max' => 1024, 'square' => false, 'out' => 'png'],
        'logo_dark' => ['svg' => true,  'max' => 1024, 'square' => false, 'out' => 'png'],
        'icon'      => ['svg' => true,  'max' => 512,  'square' => true,  'out' => 'png'],
        'og'        => ['svg' => false, 'max' => 1600, 'square' => false, 'out' => 'jpg'],
    ];
    private const MAX_PIXELS = 24000000;
    private const MAX_SVG_BYTES = 524288;

    private static function key(string $slot): string { return 'brand_' . $slot; }

    /** Stored path like "branding/logo-1a2b3c4d.png", or null when nothing is uploaded. */
    public static function path(string $slot): ?string
    {
        $v = (string) Settings::get(self::key($slot), '');
        return preg_match('#^branding/[A-Za-z0-9._-]+$#', $v) === 1 ? $v : null;
    }

    public static function url(string $slot): ?string
    {
        $p = self::path($slot);
        return $p === null ? null : '/uploads/' . $p;
    }

    public static function mime(string $slot): string
    {
        $p = (string) self::path($slot);
        return match (strtolower(pathinfo($p, PATHINFO_EXTENSION))) {
            'svg' => 'image/svg+xml', 'jpg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', default => 'image/png',
        };
    }

    /**
     * Process an uploaded file ($_FILES entry), make it the picture for $slot, delete the previous one.
     * @return string stored path
     * @throws \RuntimeException message is a key under "brand." in the language files
     */
    public static function store(string $slot, array $f): string
    {
        if (!isset(self::CFG[$slot])) throw new \RuntimeException('err_upload');
        $cfg = self::CFG[$slot];
        $err = (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'err_size' : 'err_upload');
        }
        $maxMb = max(1, (int) Settings::get('upload_max_image_mb', '5'));
        if ((int) ($f['size'] ?? 0) > $maxMb * 1048576) throw new \RuntimeException('err_size');
        $tmp = (string) ($f['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) throw new \RuntimeException('err_upload');
        $raw = (string) file_get_contents($tmp);

        $dir = Paths::ensure(Paths::uploads('branding'));
        $base = $slot . '-' . bin2hex(random_bytes(4));

        if ($cfg['svg'] && preg_match('/<svg[\s>]/i', substr($raw, 0, 4096)) === 1) {
            $file = $base . '.svg';
            if (file_put_contents($dir . '/' . $file, self::cleanSvg($raw)) === false) throw new \RuntimeException('err_upload');
        } else {
            $info = @getimagesizefromstring($raw);
            if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
                throw new \RuntimeException($cfg['svg'] ? 'err_type' : 'err_type_raster');
            }
            if ($info[0] * $info[1] > self::MAX_PIXELS) throw new \RuntimeException('err_dims');
            $src = @imagecreatefromstring($raw);
            if ($src === false) throw new \RuntimeException('err_type');
            $file = $base . '.' . $cfg['out'];
            self::writeRaster($src, $dir . '/' . $file, $cfg);
        }
        @chmod($dir . '/' . $file, 0644);

        $old = self::path($slot);
        Settings::set(self::key($slot), 'branding/' . $file, 'branding');
        if ($old !== null) self::unlink($old);
        return 'branding/' . $file;
    }

    public static function remove(string $slot): void
    {
        $old = self::path($slot);
        Settings::set(self::key($slot), '', 'branding');
        if ($old !== null) self::unlink($old);
    }

    private static function unlink(string $rel): void
    {
        if (preg_match('#^branding/[A-Za-z0-9._-]+$#', $rel) === 1) @unlink(Paths::uploads($rel));
    }

    private static function writeRaster(\GdImage $src, string $dest, array $cfg): void
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $png = $cfg['out'] === 'png';
        if ($cfg['square']) {
            $side = max($w, $h);
            $cw = $ch = max(1, min($side, (int) $cfg['max']));
            $scale = $cw / $side;
        } else {
            $scale = min(1.0, $cfg['max'] / max($w, $h));
            $cw = max(1, (int) round($w * $scale));
            $ch = max(1, (int) round($h * $scale));
        }
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($cw, $ch);
        if ($png) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, (int) imagecolorallocatealpha($dst, 0, 0, 0, 127));
        } else {
            imagefill($dst, 0, 0, (int) imagecolorallocate($dst, 255, 255, 255));
        }
        imagecopyresampled($dst, $src, intdiv($cw - $nw, 2), intdiv($ch - $nh, 2), 0, 0, $nw, $nh, $w, $h);
        $ok = $png ? imagepng($dst, $dest, 9) : imagejpeg($dst, $dest, 88);
        if (!$ok) throw new \RuntimeException('err_upload');
    }

    /** Remove everything from an SVG that could run code or load something from elsewhere. */
    public static function cleanSvg(string $raw): string
    {
        if (strlen($raw) > self::MAX_SVG_BYTES) throw new \RuntimeException('err_size');
        if (preg_match('/<!ENTITY|<!DOCTYPE[^>]*\[/i', $raw) === 1) throw new \RuntimeException('err_svg');
        $prev = libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $ok = $doc->loadXML($raw, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $ok ? $doc->documentElement : null;
        if ($root === null || strtolower($root->localName) !== 'svg') throw new \RuntimeException('err_svg');

        $badTags = ['script', 'foreignobject', 'iframe', 'object', 'embed', 'audio', 'video', 'canvas', 'handler', 'listener',
                    'animate', 'set', 'animatetransform', 'animatemotion', 'discard', 'a'];
        $xp = new \DOMXPath($doc);
        $nodes = [];
        foreach ($xp->query('//*') ?: [] as $n) $nodes[] = $n;
        foreach ($nodes as $n) {
            if (!$n instanceof \DOMElement || $n->parentNode === null) continue;
            $tag = strtolower($n->localName);
            if (in_array($tag, $badTags, true)) { $n->parentNode->removeChild($n); continue; }
            if ($tag === 'style' && self::badCss($n->textContent)) { $n->parentNode->removeChild($n); continue; }
            $drop = [];
            foreach ($n->attributes ?? [] as $a) {
                $name = strtolower($a->nodeName);
                $val = trim($a->nodeValue ?? '');
                if (str_starts_with($name, 'on')) { $drop[] = $a->nodeName; continue; }
                if ($name === 'href' || $name === 'xlink:href') {
                    if (!(str_starts_with($val, '#') || preg_match('#^data:image/(png|jpe?g|gif|webp);base64,#i', $val) === 1)) $drop[] = $a->nodeName;
                    continue;
                }
                if (preg_match('/url\(\s*[\'"]?\s*(?!#)/i', $val) === 1 || preg_match('/javascript:|expression\(/i', $val) === 1) $drop[] = $a->nodeName;
            }
            foreach ($drop as $d) $n->removeAttribute($d);
        }
        if (!$root->hasAttribute('xmlns')) $root->setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        $out = $doc->saveXML($root);
        if ($out === false || $out === '') throw new \RuntimeException('err_svg');
        return $out;
    }

    private static function badCss(string $css): bool
    {
        return preg_match('/@import|javascript:|expression\(|url\(\s*[\'"]?\s*(?!#)/i', $css) === 1;
    }
}
