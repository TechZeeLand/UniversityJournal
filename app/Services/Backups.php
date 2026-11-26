<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{Env, Paths, Settings};

/**
 * Database dumps in data/backups/db-YYYYmmdd-HHMMSS.sql.gz. Uploads are plain files in data/uploads, so copying the
 * data folder (or just data/backups plus data/uploads) saves everything. Automatic dumps run from docker/backup-loop.sh.
 */
final class Backups
{
    private const NAME = '/^db-\d{8}-\d{6}\.sql\.gz$/';

    public static function dir(): string { return Paths::ensure(Paths::backups()); }

    public static function auto(): bool { return Settings::get('backup_auto', '1') === '1'; }
    public static function keep(): int { return max(1, (int) Settings::get('backup_keep', '14')); }
    public static function everyHours(): int { return max(1, (int) Settings::get('backup_every_hours', '24')); }

    public static function valid(string $name): bool { return preg_match(self::NAME, $name) === 1; }

    /** @return list<array{name:string,size:int,time:int}> newest first */
    public static function list(): array
    {
        $out = [];
        foreach (glob(self::dir() . '/db-*.sql.gz') ?: [] as $f) {
            $n = basename($f);
            if (self::valid($n)) $out[] = ['name' => $n, 'size' => (int) @filesize($f), 'time' => (int) @filemtime($f)];
        }
        usort($out, fn($a, $b) => strcmp($b['name'], $a['name']));
        return $out;
    }

    public static function path(string $name): ?string
    {
        if (!self::valid($name)) return null;
        $p = self::dir() . '/' . $name;
        return is_file($p) ? $p : null;
    }

    public static function delete(string $name): bool
    {
        $p = self::path($name);
        return $p !== null && @unlink($p);
    }

    /** Is the newest automatic backup older than the configured interval? */
    public static function due(): bool
    {
        $l = self::list();
        return !$l || $l[0]['time'] < time() - self::everyHours() * 3600 + 120;
    }

    /** Remove the oldest dumps beyond $keep. @return int removed */
    public static function prune(int $keep): int
    {
        $n = 0;
        foreach (array_slice(self::list(), max(1, $keep)) as $b) {
            if (self::delete($b['name'])) $n++;
        }
        return $n;
    }

    /** Dump the database now. @return string file name @throws \RuntimeException */
    public static function create(): string
    {
        @set_time_limit(300);
        $bin = null;
        foreach (['/usr/bin/mariadb-dump', '/usr/bin/mysqldump'] as $c) {
            if (is_executable($c)) { $bin = $c; break; }
        }
        if ($bin === null) throw new \RuntimeException('no dump tool found');

        $dir = self::dir();
        $stamp = date('Ymd-His');
        $sql = $dir . '/db-' . $stamp . '.sql';
        $cmd = [$bin];
        $host = Env::get('DB_HOST');
        $cmd[] = $host ? '--host=' . $host : '--socket=' . Env::get('DB_SOCKET', '/run/mysqld/mysqld.sock');
        array_push($cmd, '--user=' . Env::get('DB_USER', 'journal'), '--single-transaction', '--routines', '--default-character-set=utf8mb4', Env::get('DB_NAME', 'university_journal'));

        $proc = proc_open($cmd, [1 => ['file', $sql, 'w'], 2 => ['pipe', 'w']], $pipes, null,
            ['MYSQL_PWD' => Env::get('DB_PASSWORD', ''), 'PATH' => getenv('PATH') ?: '/usr/bin:/bin']);
        if (!is_resource($proc)) throw new \RuntimeException('could not start the dump');
        $err = (string) stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        if (proc_close($proc) !== 0 || !is_file($sql) || filesize($sql) < 100) {
            @unlink($sql);
            throw new \RuntimeException('dump failed: ' . trim(mb_substr($err, 0, 300)));
        }

        $gz = proc_open(['gzip', '-f', $sql], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $p2, null, ['PATH' => getenv('PATH') ?: '/usr/bin:/bin']);
        if (is_resource($gz)) {
            stream_get_contents($p2[1]);
            stream_get_contents($p2[2]);
            fclose($p2[1]);
            fclose($p2[2]);
            proc_close($gz);
        }
        if (!is_file($sql . '.gz')) {
            @unlink($sql);
            throw new \RuntimeException('compression failed');
        }
        @chmod($sql . '.gz', 0640);
        return basename($sql . '.gz');
    }
}
