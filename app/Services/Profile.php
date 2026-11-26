<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{DB, I18n, Settings};

/** Profile rules: which fields a student or teacher must complete before publishing (set in the Tech portal). */
final class Profile
{
    /** @return string[] profile column names required for this role */
    public static function required(string $role): array
    {
        if (!in_array($role, ['student', 'teacher'], true)) return [];
        $arr = json_decode((string) Settings::get('profile_required_' . $role, '[]'), true);
        return is_array($arr) ? array_values(array_filter($arr, 'is_string')) : [];
    }

    /** @param array $p a user_profiles row (or similar) @return string[] required fields that are still empty */
    public static function missing(array $p, string $role): array
    {
        $out = [];
        foreach (self::required($role) as $f) {
            $v = $p[$f] ?? null;
            if ($v === null || trim((string) $v) === '' || (string) $v === '0') $out[] = $f;
        }
        return $out;
    }

    /** Recompute and store user_profiles.profile_complete. */
    public static function recalc(int $userId): bool
    {
        $row = DB::one('SELECT u.role, p.* FROM users u JOIN user_profiles p ON p.user_id = u.id WHERE u.id = ?', [$userId]);
        if (!$row) return false;
        $ok = self::missing($row, (string) $row['role']) === [];
        DB::exec('UPDATE user_profiles SET profile_complete = ? WHERE user_id = ?', [$ok ? 1 : 0, $userId]);
        return $ok;
    }

    /** Recompute everybody's completeness (after the required fields were changed in the Tech portal). @return int people checked */
    public static function recalcAll(): int
    {
        @set_time_limit(300);
        $n = 0;
        foreach (DB::all('SELECT user_id FROM user_profiles') as $r) {
            self::recalc((int) $r['user_id']);
            $n++;
        }
        return $n;
    }

    /** @return array<string, array<int, array{id:int,name:string}>> departments grouped by faculty, in the current language */
    public static function departments(): array
    {
        $bn = I18n::locale() === 'bn';
        $out = [];
        $rows = DB::all(
            'SELECT d.id, d.name_en, d.name_bn, f.name_en AS f_en, f.name_bn AS f_bn
               FROM departments d JOIN faculties f ON f.id = d.faculty_id
              ORDER BY f.sort_order, f.id, d.sort_order, d.id'
        );
        foreach ($rows as $r) {
            $fac = ($bn && $r['f_bn']) ? $r['f_bn'] : $r['f_en'];
            $out[$fac][] = ['id' => (int) $r['id'], 'name' => ($bn && $r['name_bn']) ? $r['name_bn'] : $r['name_en']];
        }
        return $out;
    }

    public static function departmentName(?int $id): string
    {
        if (!$id) return '';
        foreach (self::departments() as $list) {
            foreach ($list as $d) {
                if ($d['id'] === $id) return $d['name'];
            }
        }
        return '';
    }
}
