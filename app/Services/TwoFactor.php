<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{DB, Env, Sessions, Settings, Totp};

/**
 * Two-step sign-in: authenticator app (TOTP), email code, one-time backup codes and "trusted" browsers.
 * Switches live in Tech portal > Settings > Two-step sign-in. The master switch off = nobody is asked for a second step
 * (the emergency exit if something goes wrong). The "allow" switches only decide who may SET UP a method; methods
 * people already have keep working.
 */
final class TwoFactor
{
    public const COOKIE = 'ujtd';
    private const BACKUP_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';     // no 0/O/1/I
    private const EMAIL_MAX_TRIES = 5;

    /** @var array<int, ?array> */
    private static array $rows = [];

    // ---- what the site allows --------------------------------------------------------------------

    public static function featureOn(): bool { return Settings::get('twofa_enabled', '1') === '1'; }
    public static function totpAllowed(): bool { return self::featureOn() && Settings::get('twofa_allow_totp', '1') === '1'; }
    public static function emailAllowed(): bool { return self::featureOn() && Settings::get('twofa_allow_email', '1') === '1' && Mailer::configured(); }
    public static function trustDays(): int { return max(0, min(365, (int) Settings::get('twofa_trust_days', '30'))); }
    public static function codeMinutes(): int { return max(2, min(60, (int) Settings::get('twofa_code_minutes', '10'))); }
    public static function backupCount(): int { return max(4, min(20, (int) Settings::get('twofa_backup_count', '10'))); }

    // ---- who has what -----------------------------------------------------------------------------

    public static function row(int $uid): ?array
    {
        if (!array_key_exists($uid, self::$rows)) {
            self::$rows[$uid] = DB::one('SELECT user_id, totp_secret_enc, totp_enabled_at, last_totp_step, email_enabled_at FROM user_2fa WHERE user_id = ?', [$uid]);
        }
        return self::$rows[$uid];
    }

    private static function forget(int $uid): void { unset(self::$rows[$uid]); }

    public static function hasTotp(int $uid): bool { $r = self::row($uid); return $r !== null && $r['totp_enabled_at'] !== null && $r['totp_secret_enc'] !== null; }
    public static function hasEmail(int $uid): bool { $r = self::row($uid); return $r !== null && $r['email_enabled_at'] !== null; }
    public static function enabled(int $uid): bool { return self::hasTotp($uid) || self::hasEmail($uid); }

    /** Methods a person can use right now at sign-in, best first. */
    public static function methods(int $uid): array
    {
        $m = [];
        if (self::hasTotp($uid)) $m[] = 'totp';
        if (self::hasEmail($uid) && Mailer::configured()) $m[] = 'email';
        return $m;
    }

    /** Will this person be asked for a second step when they sign in? */
    public static function asksAtLogin(int $uid): bool
    {
        return self::featureOn() && self::enabled($uid);
    }

    public static function backupRemaining(int $uid): int
    {
        return (int) DB::val('SELECT COUNT(*) FROM user_backup_codes WHERE user_id = ? AND used_at IS NULL', [$uid]);
    }

    // ---- rules by role ----------------------------------------------------------------------------

    public static function required(array $u): bool
    {
        if (!self::featureOn()) return false;
        $key = match ($u['role'] ?? '') {
            'tech_admin' => 'twofa_require_tech_admin',
            'moderator' => 'twofa_require_moderator',
            'student', 'teacher' => 'twofa_require_member',
            default => null,
        };
        return $key !== null && Settings::get($key, '0') === '1';
    }

    /** Can this person set up some method right now? (An email code needs a confirmed address and working mail.) */
    public static function canEnroll(array $u): bool
    {
        if (self::totpAllowed()) return true;
        return self::emailAllowed() && trim((string) ($u['email'] ?? '')) !== '' && ($u['email_verified_at'] ?? null) !== null;
    }

    /** True when the role requires a second step, the person has none, and they are able to set one up. They are held on the Security page. */
    public static function mustEnroll(array $u): bool
    {
        return self::required($u) && !self::enabled((int) $u['id']) && self::canEnroll($u);
    }

    // ---- secrets and codes at rest ---------------------------------------------------------------

    private static function secretKey(): string { return hash('sha256', 'uj-2fa-secret|' . (string) Env::get('APP_KEY', ''), true); }
    private static function pepper(): string { return 'uj-2fa-code|' . (string) Env::get('APP_KEY', ''); }
    private static function hashCode(string $code): string { return hash_hmac('sha256', $code, self::pepper()); }

    private static function encrypt(string $plain): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($plain, 'aes-256-gcm', self::secretKey(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($ct === false) throw new \RuntimeException('encryption failed');
        return base64_encode($iv . $tag . $ct);
    }

    private static function decrypt(string $b64): ?string
    {
        $raw = base64_decode($b64, true);
        if ($raw === false || strlen($raw) < 29) return null;
        $p = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::secretKey(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $p === false ? null : $p;
    }

    // ---- authenticator app ---------------------------------------------------------------------------

    /** Check the code from the app while setting it up, and if right, switch the method on. */
    public static function finishTotp(int $uid, string $secret, string $code): bool
    {
        $step = Totp::verify($secret, $code, 0);
        if ($step === null) return false;
        DB::exec(
            'INSERT INTO user_2fa (user_id, totp_secret_enc, totp_enabled_at, last_totp_step) VALUES (?, ?, NOW(), ?)
             ON DUPLICATE KEY UPDATE totp_secret_enc = VALUES(totp_secret_enc), totp_enabled_at = NOW(), last_totp_step = VALUES(last_totp_step)',
            [$uid, self::encrypt($secret), $step]
        );
        self::forget($uid);
        return true;
    }

    /** Check a code at sign-in. A code that was already used (or an older one) is refused. */
    public static function verifyTotp(int $uid, string $code): bool
    {
        $r = self::row($uid);
        if ($r === null || $r['totp_secret_enc'] === null || $r['totp_enabled_at'] === null) return false;
        $secret = self::decrypt((string) $r['totp_secret_enc']);
        if ($secret === null) return false;
        $step = Totp::verify($secret, $code, (int) $r['last_totp_step']);
        if ($step === null) return false;
        $ok = DB::exec('UPDATE user_2fa SET last_totp_step = ? WHERE user_id = ? AND last_totp_step < ?', [$step, $uid, $step]) > 0;   // two requests with the same code: only one wins
        self::forget($uid);
        return $ok;
    }

    public static function disableTotp(int $uid): void
    {
        DB::exec('UPDATE user_2fa SET totp_secret_enc = NULL, totp_enabled_at = NULL, last_totp_step = 0 WHERE user_id = ?', [$uid]);
        self::cleanupIfEmpty($uid);
    }

    // ---- email code -------------------------------------------------------------------------------------

    public static function enableEmail(int $uid): void
    {
        DB::exec('INSERT INTO user_2fa (user_id, email_enabled_at) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE email_enabled_at = NOW()', [$uid]);
        self::forget($uid);
    }

    public static function disableEmail(int $uid): void
    {
        DB::exec('UPDATE user_2fa SET email_enabled_at = NULL WHERE user_id = ?', [$uid]);
        DB::exec('DELETE FROM twofa_email_codes WHERE user_id = ?', [$uid]);
        self::cleanupIfEmpty($uid);
    }

    /** The person's address changed or became unconfirmed: the email method no longer applies. */
    public static function emailChanged(int $uid): void
    {
        if (self::hasEmail($uid)) self::disableEmail($uid);
    }

    /**
     * Send a 6-digit code to the person's confirmed address.
     * @param string $purpose login | setup
     * @return string sent | throttled | unavailable
     */
    public static function sendEmailCode(int $uid, string $purpose): string
    {
        if (!Mailer::configured()) return 'unavailable';
        $age = DB::val('SELECT TIMESTAMPDIFF(SECOND, MAX(created_at), NOW()) FROM twofa_email_codes WHERE user_id = ? AND purpose = ?', [$uid, $purpose]);
        if ($age !== null && (int) $age < 45) return 'throttled';
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        DB::exec('DELETE FROM twofa_email_codes WHERE user_id = ? AND purpose = ?', [$uid, $purpose]);
        DB::exec(
            'INSERT INTO twofa_email_codes (user_id, purpose, code_hash, expires_at) VALUES (?, ?, ?, NOW() + INTERVAL ' . self::codeMinutes() . ' MINUTE)',
            [$uid, $purpose, self::hashCode($code)]
        );
        return Notify::twofaCode($uid, $code, self::codeMinutes()) ? 'sent' : 'unavailable';
    }

    public static function checkEmailCode(int $uid, string $purpose, string $typed): bool
    {
        $typed = preg_replace('/\s+/', '', $typed) ?? '';
        $row = DB::one(
            'SELECT id, code_hash, attempts FROM twofa_email_codes WHERE user_id = ? AND purpose = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1',
            [$uid, $purpose]
        );
        if (!$row) return false;
        if ((int) $row['attempts'] >= self::EMAIL_MAX_TRIES) {
            DB::exec('DELETE FROM twofa_email_codes WHERE id = ?', [(int) $row['id']]);
            return false;
        }
        if (preg_match('/^[0-9]{6}$/', $typed) === 1 && hash_equals((string) $row['code_hash'], self::hashCode($typed))) {
            // Single use: whoever deletes the row first wins.
            return DB::exec('DELETE FROM twofa_email_codes WHERE id = ?', [(int) $row['id']]) > 0;
        }
        DB::exec('UPDATE twofa_email_codes SET attempts = attempts + 1 WHERE id = ?', [(int) $row['id']]);
        return false;
    }

    // ---- backup codes -------------------------------------------------------------------------------------

    /** Replace every backup code with a fresh set. @return list<string> the codes, as shown to the person (shown only once) */
    public static function newBackupCodes(int $uid): array
    {
        DB::exec('DELETE FROM user_backup_codes WHERE user_id = ?', [$uid]);
        $shown = [];
        $n = strlen(self::BACKUP_ALPHABET);
        for ($i = 0; $i < self::backupCount(); $i++) {
            $raw = '';
            for ($k = 0; $k < 10; $k++) $raw .= self::BACKUP_ALPHABET[random_int(0, $n - 1)];
            DB::exec('INSERT INTO user_backup_codes (user_id, code_hash) VALUES (?, ?)', [$uid, self::hashCode($raw)]);
            $shown[] = substr($raw, 0, 5) . '-' . substr($raw, 5);
        }
        return $shown;
    }

    public static function useBackupCode(int $uid, string $typed): bool
    {
        $raw = strtoupper(preg_replace('/[\s-]+/', '', $typed) ?? '');
        if (preg_match('/^[A-Z0-9]{10}$/', $raw) !== 1) return false;
        return DB::exec('UPDATE user_backup_codes SET used_at = NOW() WHERE user_id = ? AND code_hash = ? AND used_at IS NULL', [$uid, self::hashCode($raw)]) > 0;
    }

    // ---- switching off -------------------------------------------------------------------------------------

    /** When the last method goes, the codes and trusted browsers that depended on it go too. */
    private static function cleanupIfEmpty(int $uid): void
    {
        self::forget($uid);
        if (self::enabled($uid)) return;
        DB::exec('DELETE FROM user_2fa WHERE user_id = ?', [$uid]);
        DB::exec('DELETE FROM user_backup_codes WHERE user_id = ?', [$uid]);
        DB::exec('DELETE FROM trusted_devices WHERE user_id = ?', [$uid]);
        DB::exec('DELETE FROM twofa_email_codes WHERE user_id = ?', [$uid]);
        self::forget($uid);
    }

    /** Remove every second step of a person (admin reset, or the emergency command). */
    public static function resetAll(int $uid): void
    {
        DB::exec('DELETE FROM user_2fa WHERE user_id = ?', [$uid]);
        DB::exec('DELETE FROM user_backup_codes WHERE user_id = ?', [$uid]);
        DB::exec('DELETE FROM trusted_devices WHERE user_id = ?', [$uid]);
        DB::exec('DELETE FROM twofa_email_codes WHERE user_id = ?', [$uid]);
        self::forget($uid);
    }

    // ---- trusted browsers ---------------------------------------------------------------------------------

    private static function https(): bool
    {
        return (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }

    /** Remember this browser for the configured number of days (does nothing when the setting is 0). */
    public static function trustThisBrowser(int $uid): void
    {
        $days = self::trustDays();
        if ($days <= 0) return;
        $token = bin2hex(random_bytes(24));
        DB::exec(
            'INSERT INTO trusted_devices (user_id, token_hash, label, ip, last_used_at, expires_at) VALUES (?, ?, ?, ?, NOW(), NOW() + INTERVAL ' . $days . ' DAY)',
            [$uid, hash('sha256', $token), Sessions::label(), \App\Core\Request::ip()]
        );
        $id = DB::lastId();
        setcookie(self::COOKIE, $id . '.' . $token, [
            'expires' => time() + $days * 86400, 'path' => '/', 'secure' => self::https(), 'httponly' => true, 'samesite' => 'Lax',
        ]);
        DB::exec('DELETE FROM trusted_devices WHERE expires_at < NOW()');
    }

    /** Does this browser carry a valid "trusted" cookie for this person? */
    public static function browserTrusted(int $uid): bool
    {
        if (self::trustDays() <= 0) return false;
        $c = (string) ($_COOKIE[self::COOKIE] ?? '');
        if (preg_match('/^([0-9]{1,10})\.([a-f0-9]{48})$/', $c, $m) !== 1) return false;
        $row = DB::one('SELECT id, token_hash FROM trusted_devices WHERE id = ? AND user_id = ? AND expires_at > NOW()', [(int) $m[1], $uid]);
        if (!$row || !hash_equals((string) $row['token_hash'], hash('sha256', $m[2]))) return false;
        DB::exec('UPDATE trusted_devices SET last_used_at = NOW() WHERE id = ?', [(int) $row['id']]);
        return true;
    }

    /** @return list<array<string,mixed>> */
    public static function trustedList(int $uid): array
    {
        $cur = 0;
        $c = (string) ($_COOKIE[self::COOKIE] ?? '');
        if (preg_match('/^([0-9]{1,10})\./', $c, $m) === 1) $cur = (int) $m[1];
        $rows = DB::all('SELECT id, label, ip, created_at, last_used_at, expires_at FROM trusted_devices WHERE user_id = ? AND expires_at > NOW() ORDER BY last_used_at DESC, id DESC', [$uid]);
        foreach ($rows as &$r) $r['current'] = (int) $r['id'] === $cur;
        unset($r);
        return $rows;
    }

    public static function revokeTrusted(int $uid, int $id): bool
    {
        return DB::exec('DELETE FROM trusted_devices WHERE id = ? AND user_id = ?', [$id, $uid]) > 0;
    }

    public static function revokeAllTrusted(int $uid): void
    {
        DB::exec('DELETE FROM trusted_devices WHERE user_id = ?', [$uid]);
    }
}
