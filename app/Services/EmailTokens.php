<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\DB;

/** One-time links sent by email (verify an address, reset a password). Only a hash of each token is stored. */
final class EmailTokens
{
    public const VERIFY = 'verify';
    public const RESET = 'reset';
    private const TTL = [self::VERIFY => 60 * 24 * 3, self::RESET => 60];     // minutes

    public static function ttlMinutes(string $kind): int { return self::TTL[$kind] ?? 60; }

    /** Make a fresh token (older unused ones of the same kind stop working). @return string the token to put in the link */
    public static function issue(int $userId, string $kind, string $email): string
    {
        DB::exec('DELETE FROM email_tokens WHERE user_id = ? AND kind = ? AND used_at IS NULL', [$userId, $kind]);
        $token = bin2hex(random_bytes(24));
        DB::exec(
            'INSERT INTO email_tokens (user_id, kind, token_hash, email, expires_at) VALUES (?, ?, ?, ?, NOW() + INTERVAL ' . self::ttlMinutes($kind) . ' MINUTE)',
            [$userId, $kind, hash('sha256', $token), mb_substr($email, 0, 190)]
        );
        if (random_int(1, 30) === 1) DB::exec('DELETE FROM email_tokens WHERE expires_at < (NOW() - INTERVAL 7 DAY)');
        return $token;
    }

    /** Seconds since this person was last sent a token of this kind (null = never). */
    public static function ageSeconds(int $userId, string $kind): ?int
    {
        $v = DB::val('SELECT TIMESTAMPDIFF(SECOND, MAX(created_at), NOW()) FROM email_tokens WHERE user_id = ? AND kind = ?', [$userId, $kind]);
        return $v === null ? null : (int) $v;
    }

    /** The token's row when it is valid (right kind, not used, not expired), else null. Does not use it up. */
    public static function peek(string $token, string $kind): ?array
    {
        if (preg_match('/^[a-f0-9]{48}$/', $token) !== 1) return null;
        return DB::one(
            'SELECT id, user_id, email FROM email_tokens WHERE token_hash = ? AND kind = ? AND used_at IS NULL AND expires_at > NOW()',
            [hash('sha256', $token), $kind]
        );
    }

    /** Use the token up. @return bool false when somebody else used it first */
    public static function consume(int $id): bool
    {
        return DB::exec('UPDATE email_tokens SET used_at = NOW() WHERE id = ? AND used_at IS NULL', [$id]) > 0;
    }

    public static function link(string $kind, string $token): string
    {
        return abs_url(($kind === self::RESET ? '/reset/' : '/verify/') . $token);
    }
}
