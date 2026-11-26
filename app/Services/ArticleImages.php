<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{Paths, Settings};

/** Pictures for articles: a cover (cropped to 16:9) and pictures inside the text (scaled down). Always re-encoded as JPEG, which strips metadata and hidden payloads. */
final class ArticleImages
{
    private const MAX_PIXELS = 24000000;
    private static function dir(): string { return Paths::uploads('articles'); }

    /** @throws \RuntimeException message is a key under "profile." (err_photo_*) */
    public static function cover(array $f, int $userId): string { return self::store($f, $userId, true); }

    /** @throws \RuntimeException */
    public static function inline(array $f, int $userId): string { return self::store($f, $userId, false); }

    private static function store(array $f, int $userId, bool $crop): string
    {
        $err = (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) throw new \RuntimeException(in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'err_photo_size' : 'err_photo_upload');
        $maxMb = max(1, (int) Settings::get('upload_max_image_mb', '5'));
        if ((int) ($f['size'] ?? 0) > $maxMb * 1048576) throw new \RuntimeException('err_photo_size');
        $tmp = (string) ($f['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) throw new \RuntimeException('err_photo_upload');

        $info = @getimagesize($tmp);
        if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) throw new \RuntimeException('err_photo_type');
        if ($info[0] * $info[1] > self::MAX_PIXELS) throw new \RuntimeException('err_photo_dims');
        $src = @imagecreatefromstring((string) file_get_contents($tmp));
        if ($src === false) throw new \RuntimeException('err_photo_type');
        $src = self::orient($src, $tmp, (int) $info[2]);
        $w = imagesx($src);
        $h = imagesy($src);

        if ($crop) {
            // largest 16:9 area from the middle, then 1600x900
            $cw = $w;
            $ch = (int) round($w * 9 / 16);
            if ($ch > $h) { $ch = $h; $cw = (int) round($h * 16 / 9); }
            $dw = min(1600, $cw);
            $dh = (int) round($dw * 9 / 16);
            $dst = imagecreatetruecolor($dw, $dh);
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
            imagecopyresampled($dst, $src, 0, 0, intdiv($w - $cw, 2), intdiv($h - $ch, 2), $dw, $dh, $cw, $ch);
        } else {
            $dw = min(1600, $w);
            $dh = (int) round($h * $dw / $w);
            $dst = imagecreatetruecolor($dw, $dh);
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $dw, $dh, $w, $h);
        }
        if (!is_dir(self::dir()) && !mkdir(self::dir(), 0755, true) && !is_dir(self::dir())) throw new \RuntimeException('err_photo_upload');
        $name = $userId . '-' . bin2hex(random_bytes(6)) . '.jpg';
        if (!imagejpeg($dst, self::dir() . '/' . $name, 84)) throw new \RuntimeException('err_photo_upload');
        @chmod(self::dir() . '/' . $name, 0644);
        return 'articles/' . $name;
    }

    public static function delete(?string $rel): void
    {
        if ($rel !== null && preg_match('#^articles/[A-Za-z0-9._-]+\.jpg$#', $rel) === 1) @unlink(self::dir() . '/' . basename($rel));
    }

    private static function orient(\GdImage $img, string $file, int $type): \GdImage
    {
        if ($type !== IMAGETYPE_JPEG || !function_exists('exif_read_data')) return $img;
        $ex = @exif_read_data($file);
        $o = is_array($ex) ? (int) ($ex['Orientation'] ?? 1) : 1;
        $deg = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($deg !== 0) { $r = imagerotate($img, $deg, 0); if ($r !== false) return $r; }
        return $img;
    }
}
