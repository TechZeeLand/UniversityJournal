<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{DB, Password};

final class Users
{
    public const ROLES = ['student', 'teacher', 'moderator', 'tech_admin'];

    private const COLS = 'u.id, u.uid, u.public_id, u.role, u.status, u.email, u.must_change_password, u.last_login_at, u.created_at,
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
        return DB::val('SELECT 1 FROM users WHERE uid = ? AND id <> ?', [$uid, $exceptId]) !== null;
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
        return $actor['role'] === 'moderator' && in_array($target['role'], ['student', 'teacher'], true);
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
     * @param array $d uid, role, email, full_name, full_name_bn, department_id (int|null), academic_session, designation
     * @return array{id:int, password:string}
     */
    public static function create(array $d, ?int $createdBy): array
    {
        $password = Password::generate();
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            DB::exec(
                "INSERT INTO users (uid, public_id, password_hash, role, status, must_change_password, email, created_by)
                 VALUES (?, ?, ?, ?, 'active', 1, ?, ?)",
                [$d['uid'], self::newPublicId(), password_hash($password, PASSWORD_DEFAULT), $d['role'], nn((string) ($d['email'] ?? '')), $createdBy]
            );
            $id = DB::lastId();
            self::saveProfile($id, $d);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        Profile::recalc($id);
        return ['id' => $id, 'password' => $password];
    }

    /** Admin edit of the account basics (uid, role, email) and the main profile fields. */
    public static function update(int $id, array $d): void
    {
        DB::exec('UPDATE users SET uid = ?, role = ?, email = ? WHERE id = ?', [$d['uid'], $d['role'], nn((string) ($d['email'] ?? '')), $id]);
        self::saveProfile($id, $d);
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
