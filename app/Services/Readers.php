<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{DB, Identity, Settings};

/** Reader accounts: people outside the university who can read, like, dislike and comment. */
final class Readers
{
    public static function signupOpen(): bool { return Settings::get('reader_signup', '1') === '1'; }
    public static function emailOpen(): bool { return self::signupOpen() && Settings::get('reader_email_signup', '1') === '1'; }
    public static function googleOpen(): bool { return self::signupOpen() && GoogleAuth::configured(); }

    /** Readers may have to confirm their email before they can like or comment (Tech portal, Email tab; needs working email). */
    public static function mayInteract(array $u): bool
    {
        if (($u['role'] ?? '') !== 'reader') return true;
        if (Settings::get('reader_require_verified', '0') !== '1' || !Mailer::configured()) return true;
        return ($u['email_verified_at'] ?? null) !== null;
    }

    public static function emailTaken(string $email): bool
    {
        return DB::val('SELECT 1 FROM users WHERE LOWER(email) = ?', [mb_strtolower($email)]) !== null;
    }

    /**
     * Create a reader. $password null = Google-only account (a random, unusable password is stored).
     * @return int new user id
     */
    public static function create(string $name, string $email, ?string $password, ?string $googleSub): int      // a Google account counts as verified
    {
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $serial = Identity::nextSerial();
            $hash = password_hash($password ?? bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
            DB::exec(
                "INSERT INTO users (uid, serial_no, public_id, public_no, password_hash, role, status, must_change_password, email, google_sub, has_password, email_verified_at, pref_lang)
                 VALUES (NULL, ?, ?, NULL, ?, 'reader', 'active', 0, ?, ?, ?, ?, ?)",
                [$serial, Users::newPublicId(), $hash, mb_strtolower($email), $googleSub, $password === null ? 0 : 1, $googleSub !== null ? date('Y-m-d H:i:s') : null, \App\Core\I18n::locale()]
            );
            $id = DB::lastId();
            DB::exec('INSERT INTO user_profiles (user_id, full_name, profile_complete) VALUES (?, ?, 1)', [$id, mb_substr($name, 0, 190)]);
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** The user row Auth::login() needs. */
    public static function loginRow(int $id): ?array
    {
        return DB::one('SELECT id, uid, serial_no, role, status, must_change_password, auth_epoch FROM users WHERE id = ?', [$id]);
    }
}
