<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Audit, Auth, DB, Flash, Identity, Request, Sessions, Totp, View};
use App\Services\{Notify, TwoFactor};

/** "Security" page for every signed-in person: second step (app and email), backup codes, trusted browsers, signed-in devices. */
final class SecurityController
{
    private const SETUP_TTL = 1800;   // seconds an unfinished authenticator set-up stays valid

    public static function index(): void
    {
        $u = Auth::need();
        $id = (int) $u['id'];

        $setup = $_SESSION['totp_setup'] ?? null;
        if (!is_array($setup) || time() - (int) ($setup['at'] ?? 0) > self::SETUP_TTL) { unset($_SESSION['totp_setup']); $setup = null; }
        $emailSetup = (int) ($_SESSION['email_setup'] ?? 0);
        if ($emailSetup > 0 && time() - $emailSetup > TwoFactor::codeMinutes() * 60) { unset($_SESSION['email_setup']); $emailSetup = 0; }
        $newBackup = $_SESSION['new_backup'] ?? null;
        unset($_SESSION['new_backup']);

        $hasPw = (int) ($u['has_password'] ?? 1) === 1;
        $verified = trim((string) ($u['email'] ?? '')) !== '' && ($u['email_verified_at'] ?? null) !== null;
        View::render('account/security', [
            'title' => t('sec.title'), 'u' => $u,
            'featureOn' => TwoFactor::featureOn(),
            'hasTotp' => TwoFactor::hasTotp($id), 'hasEmail' => TwoFactor::hasEmail($id),
            'totpAllowed' => TwoFactor::totpAllowed(), 'emailAllowed' => TwoFactor::emailAllowed(), 'emailVerified' => $verified,
            'required' => TwoFactor::required($u), 'mustEnroll' => TwoFactor::mustEnroll($u),
            'backupLeft' => TwoFactor::backupRemaining($id), 'newBackup' => is_array($newBackup) ? $newBackup : null,
            'setupSecret' => $setup['secret'] ?? null,
            'setupUri' => isset($setup['secret']) ? Totp::uri((string) $setup['secret'], self::account($u), site_name()) : null,
            'setupPretty' => isset($setup['secret']) ? Totp::pretty((string) $setup['secret']) : null,
            'emailSetup' => $emailSetup > 0, 'maskedEmail' => mask_email((string) ($u['email'] ?? '')),
            'hasPw' => $hasPw, 'trustDays' => TwoFactor::trustDays(),
            'trusted' => TwoFactor::trustedList($id), 'sessions' => Sessions::list($id),
        ]);
    }

    private static function account(array $u): string
    {
        return $u['role'] === 'reader' ? (string) $u['email'] : Identity::loginId($u);
    }

    /** The current password, asked again before anything that weakens or replaces a second step. Throttled like sign-in. */
    private static function passwordOk(array $u): bool
    {
        if ((int) ($u['has_password'] ?? 1) !== 1) return true;       // Google-only readers have none
        $ident = '~pw:' . (int) $u['id'];
        $ip = Request::ip();
        if (Auth::lockWait($ident, $ip) > 0) return false;
        $hash = DB::val('SELECT password_hash FROM users WHERE id = ?', [(int) $u['id']]);
        $pw = (string) ($_POST['password'] ?? '');
        $ok = is_string($hash) && $pw !== '' && strlen($pw) <= 1000 && password_verify($pw, $hash);
        Auth::record($ident, $ip, $ok);
        return $ok;
    }

    private static function back(string $type = '', string $msg = ''): never
    {
        if ($msg !== '') Flash::add($type, $msg);
        redirect('/security');
    }

    /** Would switching off $method leave a person who must have a second step with none? */
    private static function wouldViolateRule(array $u, string $method): bool
    {
        if (!TwoFactor::required($u)) return false;
        $id = (int) $u['id'];
        $other = $method === 'totp' ? TwoFactor::hasEmail($id) : TwoFactor::hasTotp($id);
        return !$other;
    }

    private static function stashBackupIfNone(int $id): void
    {
        if (TwoFactor::backupRemaining($id) === 0) $_SESSION['new_backup'] = TwoFactor::newBackupCodes($id);
    }

    // ---- authenticator app -----------------------------------------------------------------------

    public static function totpStart(): void
    {
        $u = Auth::need();
        if (!TwoFactor::totpAllowed()) self::back('error', t('sec.not_allowed'));
        if (TwoFactor::hasTotp((int) $u['id'])) self::back();
        $_SESSION['totp_setup'] = ['secret' => Totp::newSecret(), 'at' => time()];
        self::back();
    }

    public static function totpCancel(): void
    {
        Auth::need();
        unset($_SESSION['totp_setup']);
        self::back();
    }

    public static function totpConfirm(): void
    {
        $u = Auth::need();
        $id = (int) $u['id'];
        $setup = $_SESSION['totp_setup'] ?? null;
        if (!is_array($setup) || time() - (int) ($setup['at'] ?? 0) > self::SETUP_TTL || !TwoFactor::totpAllowed()) {
            unset($_SESSION['totp_setup']);
            self::back('error', t('sec.setup_expired'));
        }
        if (!self::passwordOk($u)) self::back('error', t('sec.err_password'));
        if (!TwoFactor::finishTotp($id, (string) $setup['secret'], mb_substr((string) ($_POST['code'] ?? ''), 0, 20))) {
            self::back('error', t('sec.err_code'));
        }
        unset($_SESSION['totp_setup']);
        self::stashBackupIfNone($id);
        Audit::log('2fa.totp_on', Identity::loginId($u), null, $id);
        Notify::security($id, 'totp_on');
        self::back('ok', t('sec.totp_on_ok'));
    }

    public static function totpDisable(): void
    {
        $u = Auth::need();
        $id = (int) $u['id'];
        if (!TwoFactor::hasTotp($id)) self::back();
        if (!self::passwordOk($u)) self::back('error', t('sec.err_password'));
        if (self::wouldViolateRule($u, 'totp')) self::back('error', t('sec.cannot_disable'));
        TwoFactor::disableTotp($id);
        Audit::log('2fa.totp_off', Identity::loginId($u), null, $id);
        Notify::security($id, 'totp_off');
        self::back('ok', t('sec.totp_off_ok'));
    }

    // ---- email code ---------------------------------------------------------------------------------

    public static function emailStart(): void
    {
        $u = Auth::need();
        $id = (int) $u['id'];
        if (!TwoFactor::emailAllowed()) self::back('error', t('sec.not_allowed'));
        if (TwoFactor::hasEmail($id)) self::back();
        if (trim((string) ($u['email'] ?? '')) === '' || ($u['email_verified_at'] ?? null) === null) self::back('error', t('sec.need_verified'));
        $r = TwoFactor::sendEmailCode($id, 'setup');
        if ($r === 'sent') {
            $_SESSION['email_setup'] = time();
            self::back('ok', t('tf.sent'));
        }
        if ($r === 'throttled') {
            $_SESSION['email_setup'] = time();
            self::back('info', t('tf.wait_resend'));
        }
        self::back('error', t('sec.mail_off'));
    }

    public static function emailCancel(): void
    {
        Auth::need();
        unset($_SESSION['email_setup']);
        self::back();
    }

    public static function emailConfirm(): void
    {
        $u = Auth::need();
        $id = (int) $u['id'];
        if (!TwoFactor::emailAllowed() || ($u['email_verified_at'] ?? null) === null) self::back('error', t('sec.not_allowed'));
        if (!self::passwordOk($u)) self::back('error', t('sec.err_password'));
        if (!TwoFactor::checkEmailCode($id, 'setup', mb_substr((string) ($_POST['code'] ?? ''), 0, 20))) self::back('error', t('sec.err_code'));
        unset($_SESSION['email_setup']);
        TwoFactor::enableEmail($id);
        self::stashBackupIfNone($id);
        Audit::log('2fa.email_on', Identity::loginId($u), null, $id);
        Notify::security($id, 'email_on');
        self::back('ok', t('sec.email_on_ok'));
    }

    public static function emailDisable(): void
    {
        $u = Auth::need();
        $id = (int) $u['id'];
        if (!TwoFactor::hasEmail($id)) self::back();
        if (!self::passwordOk($u)) self::back('error', t('sec.err_password'));
        if (self::wouldViolateRule($u, 'email')) self::back('error', t('sec.cannot_disable'));
        TwoFactor::disableEmail($id);
        Audit::log('2fa.email_off', Identity::loginId($u), null, $id);
        Notify::security($id, 'email_off');
        self::back('ok', t('sec.email_off_ok'));
    }

    // ---- backup codes ---------------------------------------------------------------------------------

    public static function backupNew(): void
    {
        $u = Auth::need();
        $id = (int) $u['id'];
        if (!TwoFactor::enabled($id)) self::back();
        if (!self::passwordOk($u)) self::back('error', t('sec.err_password'));
        $_SESSION['new_backup'] = TwoFactor::newBackupCodes($id);
        Audit::log('2fa.backup_new', Identity::loginId($u), null, $id);
        Notify::security($id, 'backup_new');
        self::back('ok', t('sec.backup_new_ok'));
    }

    // ---- trusted browsers and signed-in devices -----------------------------------------------------

    public static function trustedRevoke(string $id): void
    {
        $u = Auth::need();
        if (ctype_digit($id) && TwoFactor::revokeTrusted((int) $u['id'], (int) $id)) Audit::log('2fa.trusted_revoked', Identity::loginId($u), null, (int) $u['id']);
        self::back('ok', t('sec.trusted_revoked'));
    }

    public static function trustedRevokeAll(): void
    {
        $u = Auth::need();
        TwoFactor::revokeAllTrusted((int) $u['id']);
        Audit::log('2fa.trusted_revoked', Identity::loginId($u), 'all', (int) $u['id']);
        self::back('ok', t('sec.trusted_revoked'));
    }

    public static function sessionRevoke(string $id): void
    {
        $u = Auth::need();
        if (ctype_digit($id)) Sessions::revoke((int) $u['id'], (int) $id);
        self::back('ok', t('sec.session_out'));
    }

    public static function sessionsOthers(): void
    {
        $u = Auth::need();
        $n = Sessions::revokeOthers((int) $u['id']);
        Audit::log('sessions.revoked_others', Identity::loginId($u), (string) $n, (int) $u['id']);
        if ($n > 0) Notify::security((int) $u['id'], 'devices_out');
        self::back('ok', t('sec.sessions_out', ['n' => $n]));
    }
}
