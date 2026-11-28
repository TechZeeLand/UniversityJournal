<?php
namespace App\Controllers;

use App\Core\{Audit, Auth, DB, Flash, Identity, Request, Settings, View};
use App\Services\{EmailTokens, Mailer, Notify, TwoFactor};

final class AuthController
{
    public static function loginForm(): void
    {
        $u = Auth::user();
        if ($u) redirect(Auth::homeFor($u['role']));
        self::showLogin('', self::safeNext((string) ($_GET['next'] ?? '')), null);
    }

    public static function login(): void
    {
        $u = Auth::user();
        if ($u) redirect(Auth::homeFor($u['role']));

        $uid = post('uid');
        $pw = (string) ($_POST['password'] ?? '');
        $next = self::safeNext(post('next'));

        if ($uid === '' || $pw === '' || strlen($pw) > 1000) {
            self::showLogin($uid, $next, t('auth.err_required'));
            return;
        }

        $res = Auth::attempt($uid, $pw, Request::ip());
        if (!$res['ok']) {
            switch ($res['error']) {
                case 'locked':
                    self::showLogin($uid, $next, t('auth.err_locked', ['min' => max(1, (int) ceil($res['wait'] / 60))]), 429);
                    return;
                case 'suspended':
                    self::showLogin($uid, $next, t('auth.err_suspended'), 403);
                    return;
                default:
                    self::showLogin($uid, $next, t('auth.err_invalid'));
                    return;
            }
        }

        $u = $res['user'];
        $id = (int) $u['id'];

        // Second step: people who set one up are asked for it, unless this browser was trusted earlier.
        if (TwoFactor::asksAtLogin($id) && !TwoFactor::browserTrusted($id)) {
            session_regenerate_id(true);
            $_SESSION['pending2fa'] = ['uid' => $id, 'next' => $next, 'at' => time(), 'tries' => 0];
            if ((TwoFactor::methods($id)[0] ?? '') === 'email') TwoFactor::sendEmailCode($id, 'login');
            redirect('/login/2fa');
        }
        self::finishLogin($u, $next, 'password');
    }

    /** Start the session and go to the right page. $how is written to the audit log. */
    private static function finishLogin(array $u, string $next, string $how): never
    {
        Auth::login($u);
        Audit::log('login.ok', Identity::loginId($u), $how === 'password' ? null : $how, (int) $u['id']);
        if ((int) $u['must_change_password'] === 1) redirect('/account/password');
        redirect($next !== '' ? $next : ($u['role'] === 'reader' ? '/' : Auth::homeFor($u['role'])));
    }

    // ---- second step -----------------------------------------------------------------------------

    /** The person who passed the password step and now owes a code, or null (the page then goes back to /login). */
    private static function pending(): ?array
    {
        $p = $_SESSION['pending2fa'] ?? null;
        if (!is_array($p) || (int) ($p['uid'] ?? 0) <= 0 || time() - (int) ($p['at'] ?? 0) > 15 * 60) {
            unset($_SESSION['pending2fa']);
            return null;
        }
        return $p;
    }

    private static function pendingUser(array $p): ?array
    {
        $u = DB::one('SELECT id, uid, serial_no, role, status, must_change_password, auth_epoch, email FROM users WHERE id = ?', [(int) $p['uid']]);
        return ($u && $u['status'] === 'active') ? $u : null;
    }

    public static function twofaForm(): void
    {
        if (Auth::user()) redirect(Auth::homeFor(Auth::user()['role']));
        $p = self::pending();
        if ($p === null) redirect('/login');
        self::showTwofa($p, (string) ($_GET['m'] ?? ''), null);
    }

    private static function showTwofa(array $p, string $method, ?string $error, int $code = 200): void
    {
        $id = (int) $p['uid'];
        $methods = TwoFactor::methods($id);
        $hasBackup = TwoFactor::backupRemaining($id) > 0;
        $avail = $methods;
        if ($hasBackup) $avail[] = 'backup';
        if ($avail === []) $avail = ['backup'];                      // nothing else works: the page explains how to get help
        if (!in_array($method, $avail, true)) $method = $avail[0];
        $row = DB::one('SELECT email FROM users WHERE id = ?', [$id]);
        http_response_code($code);
        View::render('auth/twofa', [
            'title' => t('tf.title'), 'method' => $method, 'avail' => $avail, 'error' => $error,
            'trustDays' => TwoFactor::trustDays(), 'maskedEmail' => mask_email((string) ($row['email'] ?? '')),
        ]);
    }

    public static function twofaVerify(): void
    {
        $p = self::pending();
        if ($p === null) redirect('/login');
        $u = self::pendingUser($p);
        if ($u === null) {
            unset($_SESSION['pending2fa']);
            redirect('/login');
        }
        $id = (int) $u['id'];
        $ip = Request::ip();
        $ident = '~2fa:' . $id;
        $method = post('method');
        $typed = mb_substr((string) ($_POST['code'] ?? ''), 0, 40);

        $wait = Auth::lockWait($ident, $ip);
        if ($wait > 0) {
            self::showTwofa($p, $method, t('auth.err_locked', ['min' => max(1, (int) ceil($wait / 60))]), 429);
            return;
        }
        if (trim($typed) === '') {
            self::showTwofa($p, $method, t('tf.err_empty'), 422);
            return;
        }

        $ok = match ($method) {
            'totp' => TwoFactor::verifyTotp($id, $typed),
            'email' => TwoFactor::checkEmailCode($id, 'login', $typed),
            'backup' => TwoFactor::useBackupCode($id, $typed),
            default => false,
        };

        if (!$ok) {
            Auth::record($ident, $ip, false);
            Audit::log('login.2fa_fail', Identity::loginId($u), $method, $id);
            $_SESSION['pending2fa']['tries'] = (int) ($p['tries'] ?? 0) + 1;
            if ((int) $_SESSION['pending2fa']['tries'] >= 8) {         // start over from the password
                unset($_SESSION['pending2fa']);
                Flash::add('error', t('tf.too_many'));
                redirect('/login');
            }
            self::showTwofa($p, $method, t('tf.err_code'), 422);
            return;
        }

        Auth::record($ident, $ip, true);
        if (post('trust') === '1') TwoFactor::trustThisBrowser($id);
        if ($method === 'backup') {
            $left = TwoFactor::backupRemaining($id);
            Flash::add($left <= 2 ? 'warn' : 'info', t('tf.backup_used', ['n' => $left]));
        }
        self::finishLogin($u, (string) ($p['next'] ?? ''), '2fa:' . $method);
    }

    public static function twofaSend(): void
    {
        $p = self::pending();
        if ($p === null) redirect('/login');
        $id = (int) $p['uid'];
        if (in_array('email', TwoFactor::methods($id), true)) {
            $r = TwoFactor::sendEmailCode($id, 'login');
            Flash::add($r === 'sent' ? 'ok' : 'info', t($r === 'sent' ? 'tf.sent' : 'tf.wait_resend'));
        }
        redirect('/login/2fa?m=email');
    }

    public static function twofaCancel(): void
    {
        unset($_SESSION['pending2fa']);
        redirect('/login');
    }

    public static function logout(): void
    {
        $u = Auth::user();
        if ($u) Audit::log('logout', Identity::loginId($u), null, (int) $u['id']);
        Auth::logout();
        Flash::add('ok', t('auth.logged_out'));
        redirect('/login');
    }

    public static function forgotForm(): void
    {
        if (Auth::user()) redirect('/account/password');
        View::render('auth/forgot', ['title' => t('auth.forgot_title'), 'done' => false, 'error' => null, 'uid' => '']);
    }

    /**
     * Passwords are reset by a moderator. The reply is identical whether or not the ID exists,
     * so this form cannot be used to find out which IDs are registered.
     */
    public static function forgot(): void
    {
        $ip = Request::ip();
        $uid = mb_substr(post('uid'), 0, 100);
        $contact = mb_substr(post('contact'), 0, 190);
        $note = mb_substr(post('note'), 0, 500);

        if ($uid === '') {
            http_response_code(422);
            View::render('auth/forgot', ['title' => t('auth.forgot_title'), 'done' => false, 'error' => Identity::t('auth.err_uid_required'), 'uid' => '']);
            return;
        }
        if (Auth::resetThrottled($ip)) {
            http_response_code(429);
            View::render('auth/forgot', ['title' => t('auth.forgot_title'), 'done' => false, 'error' => t('auth.forgot_limit'), 'uid' => $uid]);
            return;
        }
        Auth::recordReset($ip);

        // By email: a reset link goes to the address on the account (readers; university accounts only when the Tech portal allows it).
        if (Mailer::configured() && self::emailReset($uid)) {
            View::render('auth/forgot', ['title' => t('auth.forgot_title'), 'done' => true, 'error' => null, 'uid' => '']);
            return;
        }

        $key = Identity::normalize($uid);
        $row = $key === null ? null : DB::one('SELECT id, uid, serial_no, status FROM users WHERE ' . Identity::column() . ' = ?', [$key]);
        if ($row && $row['status'] === 'active'
            && DB::val("SELECT 1 FROM password_reset_requests WHERE user_id = ? AND status = 'pending'", [(int) $row['id']]) === null) {
            DB::exec(
                'INSERT INTO password_reset_requests (user_id, note, contact, ip) VALUES (?, ?, ?, ?)',
                [(int) $row['id'], nn($note), nn($contact), $ip]
            );
            Audit::log('reset.requested', Identity::loginId($row), null, (int) $row['id']);
        }
        View::render('auth/forgot', ['title' => t('auth.forgot_title'), 'done' => true, 'error' => null, 'uid' => '']);
    }

    /**
     * Send a reset link when this is a reader's email address, or a university ID whose verified email may be used.
     * @return bool true when the email route took the request (nothing is revealed either way: the page reply is the same)
     */
    private static function emailReset(string $typed): bool
    {
        $typed = mb_strtolower(trim($typed));
        $u = null;
        if (str_contains($typed, '@')) {
            if (Settings::get('email_reset_readers', '1') !== '1') return false;
            $u = DB::one("SELECT id, role, status FROM users WHERE role = 'reader' AND email = ?", [$typed]);
            if ($u === null) return true;                       // unknown address: same reply, nothing sent
        } else {
            if (Settings::get('email_reset_university', '0') !== '1') return false;
            $key = Identity::normalize($typed);
            $u = $key === null ? null : DB::one(
                'SELECT id, role, status FROM users WHERE ' . Identity::column() . ' = ? AND email IS NOT NULL AND email_verified_at IS NOT NULL', [$key]
            );
            if ($u === null) return false;                      // no verified email: fall back to the moderator route
        }
        $age = EmailTokens::ageSeconds((int) $u['id'], EmailTokens::RESET);
        if ($u['status'] === 'active' && ($age === null || $age > 120)) {
            Notify::passwordReset((int) $u['id']);
            Audit::log('reset.emailed', '#' . (int) $u['id'], null, (int) $u['id']);
        }
        return true;
    }

    private static function showLogin(string $uid, string $next, ?string $error, int $code = 200): void
    {
        http_response_code($code);
        View::render('auth/login', ['title' => t('auth.login_title'), 'uid' => $uid, 'next' => $next, 'error' => $error]);
    }

    /** Only same-site paths are accepted as a post-login destination (no open redirects). */
    private static function safeNext(string $n): string
    {
        if ($n === '' || $n[0] !== '/' || str_starts_with($n, '//') || str_contains($n, '\\') || preg_match('/[\x00-\x1f]/', $n)) return '';
        if (str_starts_with($n, '/login') || str_starts_with($n, '/logout') || str_starts_with($n, '/forgot')) return '';
        return $n;
    }
}
