<?php
declare(strict_types=1);
namespace App\Core;

/**
 * The list of browsers a person is signed in on. The PHP session holds a random token ($_SESSION['stok']);
 * user_sessions holds its SHA-256. Deleting the row signs that browser out on its next request.
 */
final class Sessions
{
    /** Short readable name of a browser, e.g. "Firefox on Linux". Never stores the full user-agent text. */
    public static function label(?string $ua = null): string
    {
        $ua = (string) ($ua ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $browser = 'Browser';
        foreach (['Edg' => 'Edge', 'OPR' => 'Opera', 'SamsungBrowser' => 'Samsung Internet', 'Firefox' => 'Firefox', 'Chrome' => 'Chrome', 'CriOS' => 'Chrome', 'FxiOS' => 'Firefox', 'Safari' => 'Safari'] as $needle => $name) {
            if (stripos($ua, $needle) !== false) { $browser = $name; break; }
        }
        $os = '';
        foreach (['Android' => 'Android', 'iPhone' => 'iOS', 'iPad' => 'iPadOS', 'Windows' => 'Windows', 'Mac OS X' => 'macOS', 'CrOS' => 'ChromeOS', 'Linux' => 'Linux'] as $needle => $name) {
            if (stripos($ua, $needle) !== false) { $os = $name; break; }
        }
        return mb_substr($os !== '' ? $browser . ' / ' . $os : $browser, 0, 120);
    }

    /** Record a new signed-in browser and remember its token in the session. */
    public static function start(int $userId): void
    {
        $token = bin2hex(random_bytes(24));
        DB::exec(
            'INSERT INTO user_sessions (user_id, token_hash, label, ip, last_seen_at) VALUES (?, ?, ?, ?, NOW())',
            [$userId, hash('sha256', $token), self::label(), Request::ip()]
        );
        $_SESSION['stok'] = $token;
        if (random_int(1, 40) === 1) {
            // Rows of browsers that were closed without signing out: gone after the longest possible idle time (24 h) plus a day.
            DB::exec('DELETE FROM user_sessions WHERE last_seen_at < (NOW() - INTERVAL 2 DAY)');
        }
    }

    /**
     * Is this browser still signed in? A session from before this feature existed has no token: it gets one.
     * Touches last_seen_at at most every 5 minutes.
     */
    public static function valid(int $userId): bool
    {
        $token = (string) ($_SESSION['stok'] ?? '');
        if ($token === '') {
            self::start($userId);
            return true;
        }
        $row = DB::one('SELECT id, user_id, last_seen_at FROM user_sessions WHERE token_hash = ?', [hash('sha256', $token)]);
        if (!$row || (int) $row['user_id'] !== $userId) return false;
        if (time() - (int) strtotime((string) $row['last_seen_at']) > 300) {
            DB::exec('UPDATE user_sessions SET last_seen_at = NOW(), ip = ? WHERE id = ?', [Request::ip(), (int) $row['id']]);
        }
        return true;
    }

    public static function currentHash(): string
    {
        $t = (string) ($_SESSION['stok'] ?? '');
        return $t === '' ? '' : hash('sha256', $t);
    }

    /** Sign this browser out (the row only; the caller clears the PHP session). */
    public static function end(): void
    {
        $h = self::currentHash();
        if ($h !== '') DB::exec('DELETE FROM user_sessions WHERE token_hash = ?', [$h]);
        unset($_SESSION['stok']);
    }

    /** @return list<array<string,mixed>> newest activity first; 'current' marks this browser */
    public static function list(int $userId): array
    {
        $cur = self::currentHash();
        $rows = DB::all('SELECT id, token_hash, label, ip, created_at, last_seen_at FROM user_sessions WHERE user_id = ? ORDER BY last_seen_at DESC, id DESC', [$userId]);
        foreach ($rows as &$r) {
            $r['current'] = $cur !== '' && hash_equals((string) $r['token_hash'], $cur);
            unset($r['token_hash']);
        }
        unset($r);
        return $rows;
    }

    /** Sign one of the person's browsers out. @return bool true when a row was removed */
    public static function revoke(int $userId, int $id): bool
    {
        return DB::exec('DELETE FROM user_sessions WHERE id = ? AND user_id = ? AND token_hash <> ?', [$id, $userId, self::currentHash()]) > 0;
    }

    /** Sign out every other browser of this person. @return int how many */
    public static function revokeOthers(int $userId): int
    {
        return DB::exec('DELETE FROM user_sessions WHERE user_id = ? AND token_hash <> ?', [$userId, self::currentHash()]);
    }

    /** Sign a person out everywhere, except (optionally) the browser making the request when it is theirs. */
    public static function revokeAll(int $userId, bool $keepCurrent): void
    {
        $keep = ($keepCurrent && (int) ($_SESSION['uid'] ?? 0) === $userId) ? self::currentHash() : '';
        DB::exec('DELETE FROM user_sessions WHERE user_id = ? AND token_hash <> ?', [$userId, $keep]);
    }
}
