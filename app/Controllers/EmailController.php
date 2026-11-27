<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Audit, Auth, DB, Flash, Password, View};
use App\Services\{EmailTokens, Mailer, Notify};

/** Links people open from their email: verify an address, choose a new password. Plus "send me the link again". */
final class EmailController
{
    // ---- verify ----------------------------------------------------------------------------------

    public static function verify(string $token): void
    {
        $row = EmailTokens::peek($token, EmailTokens::VERIFY);
        $user = $row ? DB::one('SELECT id, email FROM users WHERE id = ?', [(int) $row['user_id']]) : null;
        if ($row === null || $user === null || mb_strtolower((string) $user['email']) !== mb_strtolower((string) $row['email']) || !EmailTokens::consume((int) $row['id'])) {
            Flash::add('error', t('email.verify_bad'));
            redirect(Auth::user() ? Auth::homeFor((string) Auth::user()['role']) : '/login');
        }
        DB::exec('UPDATE users SET email_verified_at = NOW() WHERE id = ? AND email_verified_at IS NULL', [(int) $user['id']]);
        Audit::log('email.verified', '#' . (int) $user['id'], null, (int) $user['id']);
        Flash::add('ok', t('email.verify_ok'));
        $me = Auth::user();
        redirect($me ? Auth::homeFor((string) $me['role']) : '/login');
    }

    /** POST: send the verification link again (signed-in people, once a minute). */
    public static function resend(): void
    {
        $u = Auth::need();
        $home = Auth::homeFor((string) $u['role']);
        if (!Mailer::configured() || trim((string) $u['email']) === '' || $u['email_verified_at'] !== null) {
            Flash::add('info', t('email.verify_nothing'));
            redirect($home);
        }
        $age = EmailTokens::ageSeconds((int) $u['id'], EmailTokens::VERIFY);
        if ($age !== null && $age < 60) {
            Flash::add('error', t('email.verify_wait', ['n' => 60 - $age]));
            redirect($home);
        }
        Notify::verification((int) $u['id']);
        Flash::add('ok', t('email.verify_sent', ['email' => (string) $u['email']]));
        redirect($home);
    }

    // ---- reset ----------------------------------------------------------------------------------

    public static function resetForm(string $token): void
    {
        if (Auth::user()) redirect('/account/password');
        $row = EmailTokens::peek($token, EmailTokens::RESET);
        View::render('auth/reset', ['title' => t('email.reset_title'), 'valid' => $row !== null, 'token' => $token, 'errors' => []]);
    }

    public static function resetSave(string $token): void
    {
        if (Auth::user()) redirect('/account/password');
        $row = EmailTokens::peek($token, EmailTokens::RESET);
        if ($row === null) {
            http_response_code(410);
            View::render('auth/reset', ['title' => t('email.reset_title'), 'valid' => false, 'token' => $token, 'errors' => []]);
            return;
        }
        $user = DB::one('SELECT id, uid, email, role, status FROM users WHERE id = ?', [(int) $row['user_id']]);
        $pw = (string) ($_POST['password'] ?? '');
        $errors = [];
        if ($user === null || $user['status'] !== 'active' || mb_strtolower((string) $user['email']) !== mb_strtolower((string) $row['email'])) {
            $errors['form'] = t('email.reset_bad');
        } elseif (($k = Password::validate($pw, mb_strtolower((string) ($user['uid'] ?? $user['email'])))) !== null) {
            $errors['password'] = t('pw.' . $k, ['n' => Password::minLength()]);
        } elseif ($pw !== (string) ($_POST['confirm'] ?? '')) {
            $errors['confirm'] = t('pw.err_mismatch');
        }
        if ($errors) {
            http_response_code(422);
            View::render('auth/reset', ['title' => t('email.reset_title'), 'valid' => true, 'token' => $token, 'errors' => $errors]);
            return;
        }
        if (!EmailTokens::consume((int) $row['id'])) {      // somebody (or a double click) used it first
            http_response_code(410);
            View::render('auth/reset', ['title' => t('email.reset_title'), 'valid' => false, 'token' => $token, 'errors' => []]);
            return;
        }
        Auth::setPassword((int) $user['id'], $pw, false);   // also signs the person out everywhere else
        // Proving they own the mailbox also verifies it, and Google-only accounts now have a password.
        DB::exec('UPDATE users SET has_password = 1, email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?', [(int) $user['id']]);
        DB::exec("UPDATE password_reset_requests SET status = 'approved', handled_at = NOW() WHERE user_id = ? AND status = 'pending'", [(int) $user['id']]);
        Audit::log('password.reset_email', '#' . (int) $user['id'], null, (int) $user['id']);
        Flash::add('ok', t('email.reset_ok'));
        redirect('/login');
    }
}
