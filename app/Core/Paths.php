<?php
declare(strict_types=1);
namespace App\Core;

/**
 * Where the site keeps its data. EVERYTHING lives under one folder (the "data folder"):
 *   db/                    MariaDB files
 *   uploads/public/        files visitors can open: avatars/, branding/ (logo, icon), later article images
 *   uploads/private/       files only signed-in people may get (later: attachments)
 *   backups/               database dumps (db-YYYYmmdd-HHMMSS.sql.gz)
 *   runtime/               sessions, font/icon cache, logs: disposable, safe to leave out of a backup
 * In Docker this is the mounted /data. Copy that one folder and you have copied the whole site.
 */
final class Paths
{
    private static ?string $data = null;

    public static function data(): string
    {
        if (self::$data === null) {
            $e = Env::get('DATA_PATH');
            self::$data = rtrim($e ?: (is_dir('/data') ? '/data' : BASE_PATH . '/data'), '/');
        }
        return self::$data;
    }

    /** Folder of files served at /uploads/ (the public part). */
    public static function uploads(string $sub = ''): string
    {
        return self::data() . '/uploads/public' . ($sub !== '' ? '/' . ltrim($sub, '/') : '');
    }

    public static function privateUploads(string $sub = ''): string
    {
        return self::data() . '/uploads/private' . ($sub !== '' ? '/' . ltrim($sub, '/') : '');
    }

    public static function runtime(string $sub = ''): string
    {
        return self::data() . '/runtime' . ($sub !== '' ? '/' . ltrim($sub, '/') : '');
    }

    public static function backups(): string
    {
        return self::data() . '/backups';
    }

    /** Create a folder when missing; returns the path. */
    public static function ensure(string $dir): string
    {
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        return $dir;
    }
}
