<?php
namespace App\Controllers;

use App\Core\{Audit, Auth, DB, Flash, Password, Request, Settings, View};
use App\Services\{Avatar, GoogleAuth, Mailer, Notify, Readers};

/** Reader accounts: sign up (email or Google), the reader's own account page. */
final class ReaderController
{
    private static function next(): string
    {
        $n = (string) ($_GET['next'] ?? $_POST['next'] ?? '');
        return ($n !== '' && preg_match('#^/(?!/)[^\s]*$#', $n) === 1 && strlen($n) < 300) ? $n : '';
    }

    private static function enter(int $id, string $next): never
    {
        $row = Readers::loginRow($id);
        if ($row === null || $row['status'] !== 'active') { Flash::add('error', t('auth.err_suspended')); redirect('/login'); }
        Auth::login($row);
        Audit::log('login.ok', 'reader#' . $id, null, $id);
        redirect($next !== '' ? $next : '/');
    }

    // ---- sign up -------------------------------------------------------------------------------

    public static function registerForm(): void
    {
        if (Auth::user()) redirect('/');
        if (!Readers::signupOpen()) abort(404);
        View::render('auth/register', ['title' => t('reader.create'), 'errors' => [], 'old' => ['name' => '', 'email' => ''], 'next' => self::next()]);
    }

    public static function register(): void
    {
        if (Auth::user()) redirect('/');
        if (!Readers::emailOpen()) abort(404);
        $next = self::next();
        $old = ['name' => mb_substr(post('name'), 0, 80), 'email' => mb_substr(post('email'), 0, 190)];
        $pw = (string) ($_POST['password'] ?? '');
        $errors = [];

        $ip = Request::ip();
        if (post('website') !== '') redirect('/');                      // honeypot: real people leave it empty
        if (Auth::signupThrottled($ip)) $errors['form'] = t('reader.err_throttle');

        if (mb_strlen($old['name']) < 2) $errors['name'] = t('reader.err_name');
        if (filter_var($old['email'], FILTER_VALIDATE_EMAIL) === false) $errors['email'] = t('reader.err_email');
        elseif (Readers::emailTaken($old['email'])) $errors['email'] = t('reader.err_taken');
        if (($k = Password::validate($pw, mb_strtolower($old['email']))) !== null) $errors['password'] = t('pw.' . $k, ['n' => Password::minLength()]);
        elseif ($pw !== (string) ($_POST['confirm'] ?? '')) $errors['confirm'] = t('pw.err_mismatch');

        if ($errors) {
            http_response_code(422);
            View::render('auth/register', ['title' => t('reader.create'), 'errors' => $errors, 'old' => $old, 'next' => $next]);
            return;
        }
        Auth::recordSignup($ip);
        $id = Readers::create($old['name'], $old['email'], $pw, null);
        Audit::log('reader.signup', 'reader#' . $id, 'email', $id);
        $verify = Mailer::configured() && Settings::get('reader_verify_email', '1') === '1' && Notify::verification($id);
        Flash::add('ok', t($verify ? 'reader.welcome_verify' : 'reader.welcome', ['email' => $old['email']]));
        self::enter($id, $next);
    }

    // ---- Google ----------------------------------------------------------------------------------

    public static function googleStart(): void
    {
        if (!Readers::googleOpen()) abort(404);
        if (Auth::user()) redirect('/');
        $state = bin2hex(random_bytes(16));
        $_SESSION['google_state'] = $state;
        $_SESSION['google_next'] = self::next();
        redirect(GoogleAuth::authUrl($state));
    }

    public static function googleCallback(): void
    {
        if (!Readers::googleOpen()) abort(404);
        $state = (string) ($_GET['state'] ?? '');
        $expected = (string) ($_SESSION['google_state'] ?? '');
        $next = (string) ($_SESSION['google_next'] ?? '');
        unset($_SESSION['google_state'], $_SESSION['google_next']);
        if ($expected === '' || !hash_equals($expected, $state) || isset($_GET['error']) || ($code = (string) ($_GET['code'] ?? '')) === '') {
            Flash::add('error', t('reader.err_google'));
            redirect('/login');
        }
        $g = GoogleAuth::profile($code);
        if ($g === null) { Flash::add('error', t('reader.err_google')); redirect('/login'); }

        $byGoogle = DB::one('SELECT id, role FROM users WHERE google_sub = ?', [$g['sub']]);
        if ($byGoogle) self::enter((int) $byGoogle['id'], $next);

        $byEmail = DB::one('SELECT id, role FROM users WHERE LOWER(email) = ?', [mb_strtolower($g['email'])]);
        if ($byEmail) {
            if ($byEmail['role'] !== 'reader') {      // a university account: never sign in through an outside identity
                Flash::add('error', t('reader.err_university_email'));
                redirect('/login');
            }
            DB::exec('UPDATE users SET google_sub = ?, email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ? AND google_sub IS NULL', [$g['sub'], (int) $byEmail['id']]);
            self::enter((int) $byEmail['id'], $next);
        }
        $id = Readers::create($g['name'], $g['email'], null, $g['sub']);
        Audit::log('reader.signup', 'reader#' . $id, 'google', $id);
        Flash::add('ok', t('reader.welcome'));
        self::enter($id, $next);
    }

    // ---- the reader's own account ------------------------------------------------------------------

    public static function account(): void
    {
        $u = Auth::need();
        if ($u['role'] !== 'reader') redirect(Auth::homeFor($u['role']));
        self::showAccount($u, null);
    }

    private static function showAccount(array $u, ?string $error): void
    {
        $p = DB::one('SELECT full_name, photo_path FROM user_profiles WHERE user_id = ?', [(int) $u['id']]) ?: [];
        View::render('account/reader', [
            'title' => t('reader.my_account'), 'u' => $u, 'p' => $p, 'error' => $error,
            'nComments' => (int) DB::val('SELECT COUNT(*) FROM article_comments WHERE user_id = ?', [(int) $u['id']]),
            'nLikes' => (int) DB::val('SELECT COUNT(*) FROM article_reactions WHERE user_id = ? AND value = 1', [(int) $u['id']]),
        ]);
    }

    public static function accountSave(): void
    {
        $u = Auth::need();
        if ($u['role'] !== 'reader') abort(403);
        $name = mb_substr(post('full_name'), 0, 80);
        if (mb_strlen($name) < 2) { http_response_code(422); self::showAccount($u, t('reader.err_name')); return; }
        $old = DB::val('SELECT photo_path FROM user_profiles WHERE user_id = ?', [(int) $u['id']]);
        $photo = $old;
        if (isset($_FILES['photo']) && (int) ($_FILES['photo']['error'] ?? 4) !== UPLOAD_ERR_NO_FILE) {
            try { $photo = Avatar::store($_FILES['photo'], (int) $u['id']); }
            catch (\RuntimeException $e) { http_response_code(422); self::showAccount($u, t('profile.' . $e->getMessage(), ['mb' => max(1, (int) setting('upload_max_image_mb', '5'))])); return; }
        } elseif (post('remove_photo') === '1') { $photo = null; }
        DB::exec('UPDATE user_profiles SET full_name = ?, photo_path = ? WHERE user_id = ?', [$name, $photo, (int) $u['id']]);
        DB::exec('UPDATE users SET email_notify = ? WHERE id = ?', [post('email_notify') === '1' ? 1 : 0, (int) $u['id']]);
        if ($old && $old !== $photo) Avatar::delete((string) $old);
        Flash::add('ok', t('reader.saved'));
        redirect('/account');
    }
}
