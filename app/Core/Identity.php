<?php
declare(strict_types=1);
namespace App\Core;

/**
 * The sign-in ID system, switchable from the Tech portal (setting `id_mode`):
 *   university : users.uid, the person's university ID (default)
 *   sequential : users.serial_no, a fixed number handed out at account creation, counting up from 0
 * Every account always has a serial_no, so the mode can be switched in either direction.
 */
final class Identity
{
    public const UNIVERSITY = 'university';
    public const SEQUENTIAL = 'sequential';
    public const MODES = [self::UNIVERSITY, self::SEQUENTIAL];

    public static function mode(): string
    {
        return Settings::get('id_mode', self::UNIVERSITY) === self::SEQUENTIAL ? self::SEQUENTIAL : self::UNIVERSITY;
    }

    public static function sequential(): bool { return self::mode() === self::SEQUENTIAL; }

    /** Database column that holds the sign-in ID (fixed whitelist, safe to put into SQL). */
    public static function column(): string { return self::sequential() ? 'serial_no' : 'uid'; }

    /** "University ID" or "Login number", in the current interface language. */
    public static function label(): string { return t(self::sequential() ? 'id.label_sequential' : 'id.label_university'); }

    /** Translation helper: t('x') in university mode, t('x_seq') in sequential mode. */
    public static function t(string $key, array $repl = []): string { return t($key . (self::sequential() ? '_seq' : ''), $repl); }

    /** What this person types to sign in, under the current mode. */
    public static function loginId(array $row): string
    {
        if (self::sequential() && array_key_exists('serial_no', $row)) {
            return $row['serial_no'] === null ? '' : (string) $row['serial_no'];
        }
        return (string) ($row['uid'] ?? '');
    }

    /** Short stable tag for logs and lists: the university ID if there is one, otherwise #number. */
    public static function tag(array $row): string
    {
        $uid = (string) ($row['uid'] ?? '');
        if ($uid !== '') return $uid;
        return isset($row['serial_no']) && $row['serial_no'] !== null ? '#' . $row['serial_no'] : '';
    }

    /**
     * Clean what someone typed into the sign-in box. Returns null when it cannot possibly be a valid ID
     * (so no database lookup is needed). Careful: "0" is a valid login number, so compare with === only.
     */
    public static function normalize(string $input): ?string
    {
        $s = trim($input);
        if ($s === '') return null;
        if (self::sequential()) {
            if (preg_match('/^[0-9]{1,12}$/', $s) !== 1) return null;
            return (string) (int) $s;      // "007" -> "7"
        }
        return mb_strtolower(mb_substr($s, 0, 100));
    }

    /**
     * Hand out the next login number. Must run inside the caller's transaction: the counter row is locked
     * until commit, so two accounts created at the same moment never get the same number.
     */
    public static function nextSerial(): int
    {
        $n = DB::val("SELECT v FROM counters WHERE k = 'serial_no' FOR UPDATE");
        if ($n === null) {
            $n = (int) DB::val('SELECT COALESCE(MAX(serial_no) + 1, 0) FROM users');
            DB::exec("INSERT INTO counters (k, v) VALUES ('serial_no', ?)", [$n + 1]);
            return $n;
        }
        DB::exec("UPDATE counters SET v = v + 1 WHERE k = 'serial_no'");
        return (int) $n;
    }

    /**
     * Hand out the next public number (1, 2, 3 ...) to a student or teacher. These numbers appear in public
     * addresses (/teachers/12) and are NOT the sign-in login number. Same rules: inside a transaction, never reused.
     */
    public static function nextPublicNo(): int
    {
        $n = DB::val("SELECT v FROM counters WHERE k = 'public_no' FOR UPDATE");
        if ($n === null) {
            $n = (int) DB::val('SELECT COALESCE(MAX(public_no), 0) + 1 FROM users');
            DB::exec("INSERT INTO counters (k, v) VALUES ('public_no', ?)", [$n + 1]);
            return $n;
        }
        DB::exec("UPDATE counters SET v = v + 1 WHERE k = 'public_no'");
        return (int) $n;
    }

    /** People who could not sign in if the mode were university: they have no university ID yet. */
    public static function withoutUid(): int
    {
        return (int) DB::val("SELECT COUNT(*) FROM users WHERE uid IS NULL");
    }
}
