<?php
namespace App\Controllers;

use App\Core\{Audit, Auth, DB, Flash, Request, View};

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
        Audit::log('login.ok', $u['uid'], null, (int) $u['id']);
        if ((int) $u['must_change_password'] === 1) redirect('/account/password');
        redirect($next !== '' ? $next : Auth::homeFor($u['role']));
    }

    public static function logout(): void
    {
        $u = Auth::user();
        if ($u) Audit::log('logout', $u['uid'], null, (int) $u['id']);
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
            View::render('auth/forgot', ['title' => t('auth.forgot_title'), 'done' => false, 'error' => t('auth.err_uid_required'), 'uid' => '']);
            return;
        }
        if (Auth::resetThrottled($ip)) {
            http_response_code(429);
            View::render('auth/forgot', ['title' => t('auth.forgot_title'), 'done' => false, 'error' => t('auth.forgot_limit'), 'uid' => $uid]);
            return;
        }
        Auth::recordReset($ip);

        $row = DB::one('SELECT id, uid, status FROM users WHERE uid = ?', [$uid]);
        if ($row && $row['status'] === 'active'
            && DB::val("SELECT 1 FROM password_reset_requests WHERE user_id = ? AND status = 'pending'", [(int) $row['id']]) === null) {
            DB::exec(
                'INSERT INTO password_reset_requests (user_id, note, contact, ip) VALUES (?, ?, ?, ?)',
                [(int) $row['id'], nn($note), nn($contact), $ip]
            );
            Audit::log('reset.requested', $row['uid'], null, (int) $row['id']);
        }
        View::render('auth/forgot', ['title' => t('auth.forgot_title'), 'done' => true, 'error' => null, 'uid' => '']);
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
