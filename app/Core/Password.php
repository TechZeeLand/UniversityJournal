<?php
declare(strict_types=1);
namespace App\Core;

final class Password
{
    private const COMMON = [
        'password', 'password1', 'password123', '12345678', '123456789', '1234567890', 'qwertyuiop',
        'qwerty123', 'iloveyou', 'admin123', 'letmein123', '11111111', '00000000', 'abcd1234', 'welcome123',
        'bangladesh', 'bangladesh1', 'university', 'university1',
    ];

    public static function minLength(): int
    {
        return max(6, min(64, (int) Settings::get('password_min_length', '8')));
    }

    /** @return string|null a key under "pw." in the language files, or null when the password is acceptable */
    public static function validate(string $pw, string $uid = ''): ?string
    {
        if (strlen($pw) > 72) return 'err_long';            // bcrypt only uses the first 72 bytes
        if (mb_strlen($pw) < self::minLength()) return 'err_short';
        $l = mb_strtolower($pw);
        if ($uid !== '' && $l === mb_strtolower($uid)) return 'err_same_uid';
        if (in_array($l, self::COMMON, true) || preg_match('/^(.)\1+$/u', $pw) === 1) return 'err_common';
        return null;
    }

    /** Random temporary password without look-alike characters (no 0/O, 1/l/I). */
    public static function generate(int $len = 10): string
    {
        $lower = 'abcdefghijkmnopqrstuvwxyz';
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $digit = '23456789';
        $all = $lower . $upper . $digit;
        do {
            $s = '';
            for ($i = 0; $i < $len; $i++) {
                $s .= $all[random_int(0, strlen($all) - 1)];
            }
        } while (!preg_match('/[a-z]/', $s) || !preg_match('/[A-Z]/', $s) || !preg_match('/[0-9]/', $s));
        return $s;
    }
}
