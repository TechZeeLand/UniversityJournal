<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{DB, Identity, Password};

final class Users
{
    public const ROLES = ['student', 'teacher', 'moderator', 'tech_admin'];
    /** Roles that have a public profile page and a public number. */
    public const PUBLIC_ROLES = ['student', 'teacher'];

    private const COLS = 'u.id, u.uid, u.serial_no, u.public_id, u.public_no, u.public_slug, u.role, u.status, u.email, u.must_change_password, u.last_login_at, u.created_at,
                          p.full_name, p.full_name_bn, p.department_id, p.academic_session, p.designation, p.photo_path, p.profile_complete';

    /** University IDs: 3 to 40 characters, letters, digits, dot, dash, underscore. */
    public static function validUid(string $uid): bool
    {
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,39}$/', $uid) === 1;
    }

    public static function find(int $id): ?array
    {
        return DB::one('SELECT ' . self::COLS . ' FROM users u LEFT JOIN user_profiles p ON p.user_id = u.id WHERE u.id = ?', [$id]);
    }

    public static function uidTaken(string $uid, int $exceptId = 0): bool
    {
        if ($uid === '') return false;      // an empty university ID (allowed in sequential mode) is stored as NULL
        // also refuse an ID that is someone's custom public address, so two addresses can never collide
        return DB::val('SELECT 1 FROM users WHERE (uid = ? OR public_slug = ?) AND id <> ?', [$uid, $uid, $exceptId]) !== null;
    }

    /** Roles an admin may see in lists. */
    public static function visibleRoles(array $actor): array
    {
        return $actor['role'] === 'tech_admin' ? self::ROLES : ['student', 'teacher'];
    }

    /** Roles an admin may give to accounts: moderators create students and teachers, tech admins anything. */
    public static function assignableRoles(array $actor): array
    {
        return self::visibleRoles($actor);
    }

    public static function canManage(array $actor, array $target): bool
    {
        if ($actor['role'] === 'tech_admin') return true;
        return $actor['role'] === 'moderator' && in_array($target['role'], ['student', 'teacher', 'reader'], true);
    }

    public static function newPublicId(): string
    {
        do {
            $id = bin2hex(random_bytes(6));
        } while (DB::val('SELECT 1 FROM users WHERE public_id = ?', [$id]) !== null);
        return $id;
    }

    /**
     * Create an account with a generated temporary password (the user must change it at first login).
     * @param array $d uid (may be empty in sequential mode), role, email, full_name, full_name_bn, department_id (int|null), academic_session, designation
     * @return array{id:int, password:string, serial_no:int, login_id:string}
     */
    public static function create(array $d, ?int $createdBy): array
    {
        $password = Password::generate();
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $serial = Identity::nextSerial();       // locks the counter until commit
            $publicNo = in_array($d['role'], self::PUBLIC_ROLES, true) ? Identity::nextPublicNo() : null;
            DB::exec(
                "INSERT INTO users (uid, serial_no, public_id, public_no, password_hash, role, status, must_change_password, email, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, 'active', 1, ?, ?)",
                [nn((string) ($d['uid'] ?? '')), $serial, self::newPublicId(), $publicNo, password_hash($password, PASSWORD_DEFAULT), $d['role'], nn((string) ($d['email'] ?? '')), $createdBy]
            );
            $id = DB::lastId();
            self::saveProfile($id, $d);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        Profile::recalc($id);
        $loginId = Identity::loginId(['uid' => nn((string) ($d['uid'] ?? '')), 'serial_no' => $serial]);
        return ['id' => $id, 'password' => $password, 'serial_no' => $serial, 'login_id' => $loginId];
    }

    /**
     * Admin edit of the account basics (uid, role, email) and the main profile fields.
     * Only when $d has a 'public_slug' key (tech admins) is the custom public address changed.
     */
    public static function update(int $id, array $d): void
    {
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            DB::exec('UPDATE users SET uid = ?, role = ?, email = ? WHERE id = ?', [nn((string) ($d['uid'] ?? '')), $d['role'], nn((string) ($d['email'] ?? '')), $id]);
            if (array_key_exists('public_slug', $d)) {
                DB::exec('UPDATE users SET public_slug = ? WHERE id = ?', [nn((string) $d['public_slug']), $id]);
            }
            // someone who becomes a student or teacher gets their public number now (never reused, never changed later)
            if (in_array($d['role'], self::PUBLIC_ROLES, true) && DB::val('SELECT public_no FROM users WHERE id = ?', [$id]) === null) {
                DB::exec('UPDATE users SET public_no = ? WHERE id = ?', [Identity::nextPublicNo(), $id]);
            }
            self::saveProfile($id, $d);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        Profile::recalc($id);
    }

    private static function saveProfile(int $id, array $d): void
    {
        DB::exec(
            'INSERT INTO user_profiles (user_id, full_name, full_name_bn, department_id, academic_session, designation)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), full_name_bn = VALUES(full_name_bn),
                department_id = VALUES(department_id), academic_session = VALUES(academic_session), designation = VALUES(designation)',
            [
                $id,
                nn((string) ($d['full_name'] ?? '')),
                nn((string) ($d['full_name_bn'] ?? '')),
                $d['department_id'] ?? null,
                nn((string) ($d['academic_session'] ?? '')),
                nn((string) ($d['designation'] ?? '')),
            ]
        );
    }

    /** Lower-cased slug / English name / Bangla name of every department mapped to its id (for CSV import). */
    public static function departmentIndex(): array
    {
        $map = [];
        foreach (DB::all('SELECT id, slug, name_en, name_bn FROM departments') as $r) {
            foreach (['slug', 'name_en', 'name_bn'] as $k) {
                if ($r[$k] !== null && $r[$k] !== '') $map[mb_strtolower((string) $r[$k])] = (int) $r['id'];
            }
        }
        return $map;
    }
}
