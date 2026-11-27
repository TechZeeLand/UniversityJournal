<?php
namespace App\Controllers;

use App\Core\{Audit, Auth, DB, Flash, Identity, Request, Settings, View};
use App\Services\{EmailTokens, Mailer, Notify};

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
        Auth::login($u);
        Audit::log('login.ok', Identity::loginId($u), null, (int) $u['id']);
        if ((int) $u['must_change_password'] === 1) redirect('/account/password');
        redirect($next !== '' ? $next : ($u['role'] === 'reader' ? '/' : Auth::homeFor($u['role'])));
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
