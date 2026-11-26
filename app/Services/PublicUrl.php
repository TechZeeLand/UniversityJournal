<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{DB, Env, Settings};

/**
 * Public profile addresses, set in Tech portal > Settings > Public addresses.
 *   students: /students/<university ID>   (default; or the public number, or a random code)
 *   teachers: /teachers/<public number>   (default; a plain unique serial 1, 2, 3 ...; or university ID, or random code)
 * A tech admin can also give one person a custom address (users.public_slug); it always wins.
 * The public number is NOT the sign-in login number.
 */
final class PublicUrl
{
    public const STUDENT_MODES = ['university_id', 'serial', 'random'];
    public const TEACHER_MODES = ['serial', 'university_id', 'random'];

    private const COLS = 'u.id, u.uid, u.public_id, u.public_no, u.public_slug, u.role, u.status, u.created_at,
        p.full_name, p.full_name_bn, p.department_id, p.academic_session, p.designation, p.bio, p.interests, p.photo_path, p.links,
        p.profile_complete, p.is_public';

    /** 'students' | 'teachers' | null (moderators and tech admins have no public page) */
    public static function kind(string $role): ?string
    {
        return $role === 'student' ? 'students' : ($role === 'teacher' ? 'teachers' : null);
    }

    public static function roleOf(string $kind): ?string
    {
        return $kind === 'students' ? 'student' : ($kind === 'teachers' ? 'teacher' : null);
    }

    public static function mode(string $kind): string
    {
        $allowed = $kind === 'students' ? self::STUDENT_MODES : self::TEACHER_MODES;
        $m = (string) Settings::get($kind === 'students' ? 'student_url_mode' : 'teacher_url_mode', $allowed[0]);
        return in_array($m, $allowed, true) ? $m : $allowed[0];
    }

    /** Whether public pages exist at all for this kind of person (switch in the Tech portal). */
    public static function enabled(string $kind): bool
    {
        return Settings::get('profile_public_' . ($kind === 'students' ? 'student' : 'teacher'), '1') === '1';
    }

    /** The last part of the address for this person: custom address, else by mode. Null when they have no public page. */
    public static function key(array $u): ?string
    {
        $kind = self::kind((string) ($u['role'] ?? ''));
        if ($kind === null) return null;
        $slug = trim((string) ($u['public_slug'] ?? ''));
        if ($slug !== '') return $slug;
        $uid = trim((string) ($u['uid'] ?? ''));
        $no = $u['public_no'] ?? null;
        $pid = (string) ($u['public_id'] ?? '');
        $fallback = $pid !== '' ? $pid : null;
        return match (self::mode($kind)) {
            'university_id' => $uid !== '' ? $uid : $fallback,
            'serial' => ($no !== null && $no !== '') ? (string) $no : $fallback,
            default => $fallback,
        };
    }

    /** "/students/2021001" (or null). */
    public static function path(array $u): ?string
    {
        $kind = self::kind((string) ($u['role'] ?? ''));
        $key = self::key($u);
        return ($kind === null || $key === null) ? null : '/' . $kind . '/' . rawurlencode($key);
    }

    /** Full web address, built from APP_URL, for copy buttons. */
    public static function absolute(array $u): ?string
    {
        $p = self::path($u);
        return $p === null ? null : rtrim((string) Env::get('APP_URL', ''), '/') . $p;
    }

    /** Is a public page for this person visible to visitors right now? */
    public static function visible(array $u): bool
    {
        $kind = self::kind((string) ($u['role'] ?? ''));
        return $kind !== null && self::enabled($kind)
            && ($u['status'] ?? 'active') === 'active'
            && (int) ($u['profile_complete'] ?? 0) === 1
            && (int) ($u['is_public'] ?? 1) === 1;
    }

    /** Find the person behind /students/<key> or /teachers/<key>, only if their page is public. */
    public static function find(string $kind, string $key): ?array
    {
        $role = self::roleOf($kind);
        if ($role === null || !self::enabled($kind) || $key === '' || strlen($key) > 60) return null;
        $base = 'SELECT ' . self::COLS . " FROM users u JOIN user_profiles p ON p.user_id = u.id
                  WHERE u.role = ? AND u.status = 'active' AND p.profile_complete = 1 AND p.is_public = 1 AND ";
        $mode = self::mode($kind);

        $row = DB::one($base . 'u.public_slug = ?', [$role, $key]);
        if ($row === null) {
            if ($mode === 'university_id') {
                $row = DB::one($base . 'u.uid = ?', [$role, $key]);
            } elseif ($mode === 'serial' && ctype_digit($key) && strlen($key) <= 18) {
                $row = DB::one($base . 'u.public_no = ?', [$role, (int) $key]);
            } elseif ($mode === 'random') {
                $row = DB::one($base . 'u.public_id = ?', [$role, $key]);
            }
        }
        // People without a university ID (or public number) are reached by their random code instead.
        if ($row === null && $mode !== 'random' && preg_match('/^[0-9a-f]{12}$/', $key) === 1) {
            $row = DB::one($base . 'u.public_id = ? AND ' . ($mode === 'university_id' ? 'u.uid IS NULL' : 'u.public_no IS NULL'), [$role, $key]);
        }
        return $row;
    }

    // ---- custom address (tech admin only) ------------------------------------------------------

    /** Lower-case letters, digits and dashes; starts with a letter (so it can never be mistaken for a number). */
    public static function validSlug(string $s): bool
    {
        return preg_match('/^[a-z][a-z0-9-]{2,39}$/', $s) === 1 && preg_match('/^[0-9a-f]{12}$/', $s) !== 1;
    }

    public static function slugTaken(string $slug, int $exceptId = 0): bool
    {
        if ($slug === '') return false;
        return DB::val('SELECT 1 FROM users WHERE id <> ? AND (public_slug = ? OR uid = ? OR public_id = ?)', [$exceptId, $slug, $slug, $slug]) !== null;
    }
}
