<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{Paths, Settings};

/** Profile photos: validated, re-encoded as a 512x512 square JPEG (strips metadata and any hidden payload). */
final class Avatar
{
    private const MAX_PIXELS = 24000000;
    private static function dir(): string { return Paths::uploads('avatars'); }

    /**
     * Process an uploaded file ($_FILES entry) and return its path relative to the public uploads folder.
     * @throws \RuntimeException the message is a key under "profile." in the language files
     */
    public static function store(array $f, int $userId): string
    {
        $err = (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'err_photo_size' : 'err_photo_upload');
        }
        $maxMb = max(1, (int) Settings::get('upload_max_image_mb', '5'));
        if ((int) ($f['size'] ?? 0) > $maxMb * 1048576) throw new \RuntimeException('err_photo_size');

        $tmp = (string) ($f['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) throw new \RuntimeException('err_photo_upload');

        $info = @getimagesize($tmp);
        if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
            throw new \RuntimeException('err_photo_type');
        }
        if ($info[0] * $info[1] > self::MAX_PIXELS) throw new \RuntimeException('err_photo_dims');

        $src = @imagecreatefromstring((string) file_get_contents($tmp));
        if ($src === false) throw new \RuntimeException('err_photo_type');
        $src = self::orient($src, $tmp, (int) $info[2]);

        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);
        $dst = imagecreatetruecolor(512, 512);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), 512, 512, $side, $side);

        if (!is_dir(self::dir()) && !mkdir(self::dir(), 0755, true) && !is_dir(self::dir())) throw new \RuntimeException('err_photo_upload');
        $name = $userId . '-' . bin2hex(random_bytes(5)) . '.jpg';
        if (!imagejpeg($dst, self::dir() . '/' . $name, 86)) throw new \RuntimeException('err_photo_upload');
        @chmod(self::dir() . '/' . $name, 0644);
        return 'avatars/' . $name;
    }

    public static function delete(?string $rel): void
    {
        if ($rel !== null && preg_match('#^avatars/[A-Za-z0-9._-]+\.jpg$#', $rel) === 1) {
            @unlink(self::dir() . '/' . basename($rel));
        }
    }

    /** Honour the camera's EXIF rotation (phone photos), when the exif extension is available. */
    private static function orient(\GdImage $img, string $file, int $type): \GdImage
    {
        if ($type !== IMAGETYPE_JPEG || !function_exists('exif_read_data')) return $img;
        $ex = @exif_read_data($file);
        $o = is_array($ex) ? (int) ($ex['Orientation'] ?? 1) : 1;
        $deg = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($deg !== 0) {
            $r = imagerotate($img, $deg, 0);
            if ($r !== false) return $r;
        }
        return $img;
    }
}
