<?php
declare(strict_types=1);
namespace App\Core;

/** Time-based one-time passwords (RFC 6238: HMAC-SHA1, 30 seconds, 6 digits), as used by Google Authenticator, Aegis, 2FAS, Microsoft Authenticator, etc. */
final class Totp
{
    public const PERIOD = 30;
    public const DIGITS = 6;
    private const B32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** A new random secret (20 bytes = 160 bits) as Base32 text. */
    public static function newSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    public static function base32Encode(string $bin): string
    {
        $bits = '';
        $n = strlen($bin);
        for ($i = 0; $i < $n; $i++) $bits .= str_pad(decbin(ord($bin[$i])), 8, '0', STR_PAD_LEFT);
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::B32[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }
        return $out;
    }

    /** @return string|null the raw bytes, or null when the text is not valid Base32 */
    public static function base32Decode(string $text): ?string
    {
        $text = strtoupper(preg_replace('/[\s=-]+/', '', $text) ?? '');
        if ($text === '' || preg_match('/^[A-Z2-7]+$/', $text) !== 1) return null;
        $bits = '';
        $n = strlen($text);
        for ($i = 0; $i < $n; $i++) $bits .= str_pad(decbin((int) strpos(self::B32, $text[$i])), 5, '0', STR_PAD_LEFT);
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) $out .= chr(bindec($byte));     // trailing padding bits are dropped
        }
        return $out;
    }

    /** The code for one 30-second step. */
    public static function codeForStep(string $secretBase32, int $step): string
    {
        $key = self::base32Decode($secretBase32);
        if ($key === null) return '';
        $hash = hash_hmac('sha1', pack('J', $step), $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $bin = (ord($hash[$offset]) & 0x7F) << 24 | ord($hash[$offset + 1]) << 16 | ord($hash[$offset + 2]) << 8 | ord($hash[$offset + 3]);
        return str_pad((string) ($bin % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    public static function step(?int $time = null): int
    {
        return intdiv($time ?? time(), self::PERIOD);
    }

    /**
     * Check a typed code, allowing one step either side for clock drift.
     * @param int $afterStep steps up to and including this one are refused (stops a code being used twice)
     * @return int|null the step that matched, or null
     */
    public static function verify(string $secretBase32, string $typed, int $afterStep = 0, ?int $time = null): ?int
    {
        $typed = preg_replace('/\s+/', '', $typed) ?? '';
        if (preg_match('/^[0-9]{' . self::DIGITS . '}$/', $typed) !== 1) return null;
        $now = self::step($time);
        $found = null;
        for ($d = -1; $d <= 1; $d++) {                     // check all three, so timing does not reveal which one matched
            $s = $now + $d;
            if ($s > $afterStep && hash_equals(self::codeForStep($secretBase32, $s), $typed)) $found = $s;
        }
        return $found;
    }

    /** The otpauth:// address an authenticator app reads from the QR code. */
    public static function uri(string $secretBase32, string $account, string $issuer): string
    {
        $label = rawurlencode($issuer . ':' . $account);
        return 'otpauth://totp/' . $label . '?secret=' . $secretBase32 . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::PERIOD;
    }

    /** "ABCD EFGH IJKL ..." for reading the secret aloud or typing it. */
    public static function pretty(string $secretBase32): string
    {
        return trim(chunk_split($secretBase32, 4, ' '));
    }
}
