<?php
declare(strict_types=1);
namespace App\Core;

/**
 * Sessions, login, lockout and role checks.
 * The session only stores the user id and an "epoch"; role and status are re-read from the database
 * on every request, so suspending a user or changing a password takes effect immediately.
 */
final class Auth
{
    public const ROLES = ['student', 'teacher', 'moderator', 'tech_admin'];

    private static ?array $user = null;
    private static bool $loaded = false;
    private static ?string $dummy = null;

    // ---- current user -------------------------------------------------------------------------

    public static function user(): ?array
    {
        if (self::$loaded) return self::$user;
        self::$loaded = true;

        $id = (int) ($_SESSION['uid'] ?? 0);
        if ($id <= 0) return null;

        $idle = max(5, (int) Settings::get('session_idle_minutes', '120')) * 60;
        $last = (int) ($_SESSION['last_seen'] ?? 0);
        if ($last > 0 && time() - $last > $idle) {
            self::clear();
            return null;
        }

        $row = DB::one(
            'SELECT u.id, u.uid, u.serial_no, u.public_id, u.public_no, u.public_slug, u.role, u.status, u.must_change_password, u.email, u.auth_epoch, u.has_password, u.google_sub, u.email_verified_at, u.email_notify,
                    p.full_name, p.full_name_bn, p.photo_path, p.profile_complete, p.is_public
               FROM users u LEFT JOIN user_profiles p ON p.user_id = u.id WHERE u.id = ?',
            [$id]
        );
        if (!$row || $row['status'] !== 'active' || (int) $row['auth_epoch'] !== (int) ($_SESSION['epoch'] ?? -1)) {
            self::clear();
            return null;
        }
        $_SESSION['last_seen'] = time();
        return self::$user = $row;
    }

    public static function check(): bool { return self::user() !== null; }

    public static function isAdmin(?array $u): bool
    {
        return $u !== null && in_array($u['role'], ['moderator', 'tech_admin'], true);
    }

    public static function homeFor(string $role): string
    {
        return match ($role) {
            'tech_admin' => '/tech',
            'moderator' => '/moderator',
            'reader' => '/account',
            default => '/dashboard',
        };
    }

    private static function clear(): void
    {
        unset($_SESSION['uid'], $_SESSION['epoch'], $_SESSION['last_seen']);
        self::$user = null;
    }

    /** Require a signed-in user, otherwise send them to the login page (and back afterwards). */
    public static function need(): array
    {
        $u = self::user();
        if ($u === null) {
            $next = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' ? Request::uri() : '';
            Flash::add('info', t('auth.need_login'));
            redirect('/login' . ($next !== '' ? '?next=' . rawurlencode($next) : ''));
        }
        return $u;
    }

    public static function needRole(string ...$roles): array
    {
        $u = self::need();
        if (!in_array($u['role'], $roles, true)) abort(403);
        return $u;
    }

    // ---- login / logout -----------------------------------------------------------------------

    /**
     * Check credentials with lockout. Does NOT start the session (call login() for that).
     * @return array{ok:bool, user?:array, error?:string, wait?:int}
     */
    public static function attempt(string $uid, string $password, string $ip): array
    {
        // $uid is whatever was typed: the university ID or the login number, depending on Identity::mode().
        $raw = mb_strtolower(mb_substr(trim($uid), 0, 100));
        // Readers sign in with their email address; everyone else with the university ID or login number.
        $isEmail = str_contains($raw, '@');
        $key = $isEmail ? (mb_strlen($raw) <= 190 ? $raw : null) : Identity::normalize($raw);
        $ident = $key ?? $raw;

        $wait = self::lockWait($ident, $ip);
        if ($wait > 0) return ['ok' => false, 'error' => 'locked', 'wait' => $wait];

        $cols = 'id, uid, serial_no, password_hash, role, status, must_change_password, auth_epoch';
        $u = $key === null ? null : ($isEmail
            ? DB::one("SELECT $cols FROM users WHERE role = 'reader' AND email = ?", [$key])
            : DB::one("SELECT $cols FROM users WHERE " . Identity::column() . ' = ?', [$key]));
        // Always do one hash verification so that unknown IDs take as long as known ones.
        $ok = password_verify($password, $u['password_hash'] ?? self::dummy());

        if (!$u || !$ok) {
            self::record($ident, $ip, false);
            if ($u) Audit::log('login.fail', Identity::loginId($u), null, (int) $u['id']);
            return ['ok' => false, 'error' => 'invalid'];
        }
        if ($u['status'] !== 'active') {
            // Only revealed after the correct password, so it cannot be used to discover IDs.
            return ['ok' => false, 'error' => 'suspended'];
        }

        self::record($ident, $ip, true);
        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            DB::exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), (int) $u['id']]);
        }
        unset($u['password_hash']);
        return ['ok' => true, 'user' => $u];
    }

    /** Start the authenticated session for a user row returned by attempt(). */
    public static function login(array $u): void
    {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $u['id'];
        $_SESSION['epoch'] = (int) $u['auth_epoch'];
        $_SESSION['last_seen'] = time();
        unset($_SESSION['_csrf']);
        DB::exec('UPDATE users SET last_login_at = NOW(), pref_lang = ? WHERE id = ?', [I18n::locale(), (int) $u['id']]);
        self::$user = null;
        self::$loaded = false;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
        self::$user = null;
        self::$loaded = true;
    }

    /**
     * Set a new password and invalidate every other session of that user.
     * @return int the new epoch (the caller may store it in its own session to stay signed in)
     */
    public static function setPassword(int $userId, string $plain, bool $mustChange): int
    {
        DB::exec(
            'UPDATE users SET password_hash = ?, must_change_password = ?, password_changed_at = ' . ($mustChange ? 'NULL' : 'NOW()') . ',
                    auth_epoch = auth_epoch + 1 WHERE id = ?',
            [password_hash($plain, PASSWORD_DEFAULT), $mustChange ? 1 : 0, $userId]
        );
        return (int) DB::val('SELECT auth_epoch FROM users WHERE id = ?', [$userId]);
    }

    private static function dummy(): string
    {
        return self::$dummy ??= password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
    }

    // ---- lockout --------------------------------------------------------------------------------
    // Three scopes: this ID from this IP (tight), this ID from anywhere (looser, so one attacker
    // cannot easily lock a victim out of their own computer), and this IP overall (loosest).

    private static function limits(): array
    {
        return [max(3, (int) Settings::get('login_max_attempts', '5')), max(1, (int) Settings::get('login_lockout_minutes', '15'))];
    }

    /** Seconds left of any active lockout (0 = not locked). */
    public static function lockWait(string $ident, string $ip): int
    {
        [$max, $win] = self::limits();
        return max(
            self::remaining('ident = ? AND ip = ?', [$ident, $ip], $max, $win),
            self::remaining('ident = ?', [$ident], $max * 4, $win),
            self::remaining("ip = ? AND ident NOT IN ('~reset', '~signup')", [$ip], $max * 6, $win)
        );
    }

    private static function remaining(string $where, array $params, int $limit, int $winMin): int
    {
        $winMin = max(1, $winMin);
        $off = max(0, $limit - 1);
        $row = DB::one(
            "SELECT TIMESTAMPDIFF(SECOND, NOW(), created_at + INTERVAL $winMin MINUTE) AS s
               FROM login_attempts
              WHERE success = 0 AND created_at > (NOW() - INTERVAL $winMin MINUTE) AND $where
              ORDER BY created_at DESC, id DESC LIMIT 1 OFFSET $off",
            $params
        );
        return $row ? max(0, (int) $row['s']) : 0;
    }

    public static function record(string $ident, string $ip, bool $ok): void
    {
        DB::exec('INSERT INTO login_attempts (ident, ip, success) VALUES (?, ?, ?)', [$ident, $ip, $ok ? 1 : 0]);
        if ($ok) {
            DB::exec('DELETE FROM login_attempts WHERE ident = ? AND ip = ? AND success = 0', [$ident, $ip]);
        }
        if (random_int(1, 40) === 1) {
            DB::exec('DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 30 DAY)');
        }
    }

    /** Password-reset requests are limited to 5 per hour per IP. */
    public static function resetThrottled(string $ip): bool
    {
        $n = (int) DB::val(
            "SELECT COUNT(*) FROM login_attempts WHERE ident = '~reset' AND ip = ? AND created_at > (NOW() - INTERVAL 60 MINUTE)",
            [$ip]
        );
        return $n >= 5;
    }

    public static function recordReset(string $ip): void
    {
        DB::exec("INSERT INTO login_attempts (ident, ip, success) VALUES ('~reset', ?, 0)", [$ip]);
    }

    /** Reader sign-ups are limited to 5 per hour per IP. */
    public static function signupThrottled(string $ip): bool
    {
        return (int) DB::val("SELECT COUNT(*) FROM login_attempts WHERE ident = '~signup' AND ip = ? AND created_at > (NOW() - INTERVAL 60 MINUTE)", [$ip]) >= 5;
    }

    public static function recordSignup(string $ip): void
    {
        DB::exec("INSERT INTO login_attempts (ident, ip, success) VALUES ('~signup', ?, 0)", [$ip]);
    }
}
