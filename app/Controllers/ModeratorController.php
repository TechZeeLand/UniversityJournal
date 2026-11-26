<?php
namespace App\Controllers;

use App\Core\{Audit, Auth, DB, Flash, Identity, Password, View};
use App\Services\{Profile, Users};

/** Moderator portal (moderators and tech admins): people, imports and password-reset requests. */
final class ModeratorController
{
    private const IMPORT_LIMIT = 300;

    private const CSV_ALIASES = [
        'uid' => 'uid', 'id' => 'uid', 'university_id' => 'uid', 'username' => 'uid',
        'full_name' => 'full_name', 'name' => 'full_name', 'fullname' => 'full_name',
        'full_name_bn' => 'full_name_bn', 'name_bn' => 'full_name_bn', 'bangla_name' => 'full_name_bn',
        'role' => 'role', 'department' => 'department', 'dept' => 'department',
        'session' => 'academic_session', 'academic_session' => 'academic_session',
        'designation' => 'designation', 'email' => 'email',
    ];

    private static function actor(): array
    {
        return Auth::needRole('moderator', 'tech_admin');
    }

    /** Load a user the actor is allowed to manage, or stop with 404 / 403. */
    private static function target(array $actor, string $id): array
    {
        $row = ctype_digit($id) ? Users::find((int) $id) : null;
        if ($row === null) abort(404);
        if (!Users::canManage($actor, $row)) abort(403);
        return $row;
    }

    // ---- overview ------------------------------------------------------------------------------

    public static function index(): void
    {
        $actor = self::actor();
        $counts = [];
        foreach (DB::all("SELECT role, COUNT(*) AS c FROM users WHERE status = 'active' GROUP BY role") as $r) {
            $counts[$r['role']] = (int) $r['c'];
        }
        View::render('portal/index', [
            'title' => t('portal.mod_title'),
            'actor' => $actor,
            'counts' => $counts,
            'pending' => (int) DB::val("SELECT COUNT(*) FROM password_reset_requests WHERE status = 'pending'"),
            'incomplete' => (int) DB::val("SELECT COUNT(*) FROM users u JOIN user_profiles p ON p.user_id = u.id
                                            WHERE u.status = 'active' AND u.role IN ('student','teacher') AND p.profile_complete = 0"),
            'suspended' => (int) DB::val("SELECT COUNT(*) FROM users WHERE status = 'suspended'"),
        ]);
    }

    // ---- user list -----------------------------------------------------------------------------

    public static function users(): void
    {
        $actor = self::actor();
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
        $role = (string) ($_GET['role'] ?? '');
        $status = (string) ($_GET['status'] ?? '');
        $page = max(1, (int) ($_GET['p'] ?? 1));
        $noid = ($_GET['noid'] ?? '') === '1';       // only accounts without a university ID (to fix before switching modes)
        $per = 25;

        $roles = Users::visibleRoles($actor);
        $where = ['u.role IN (' . implode(',', array_fill(0, count($roles), '?')) . ')'];
        $params = $roles;
        if (in_array($role, $roles, true)) { $where[] = 'u.role = ?'; $params[] = $role; } else { $role = ''; }
        if (in_array($status, ['active', 'suspended'], true)) { $where[] = 'u.status = ?'; $params[] = $status; } else { $status = ''; }
        if ($noid) $where[] = 'u.uid IS NULL';
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = '(u.uid LIKE ? OR CAST(u.serial_no AS CHAR) LIKE ? OR p.full_name LIKE ? OR p.full_name_bn LIKE ? OR u.email LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        $w = implode(' AND ', $where);

        $total = (int) DB::val("SELECT COUNT(*) FROM users u LEFT JOIN user_profiles p ON p.user_id = u.id WHERE $w", $params);
        $off = ($page - 1) * $per;
        $rows = DB::all(
            "SELECT u.id, u.uid, u.serial_no, u.role, u.status, u.email, u.last_login_at, u.created_at,
                    p.full_name, p.full_name_bn, p.photo_path, p.profile_complete
               FROM users u LEFT JOIN user_profiles p ON p.user_id = u.id
              WHERE $w ORDER BY u.created_at DESC, u.id DESC LIMIT $per OFFSET $off",
            $params
        );

        View::render('portal/users', [
            'title' => t('users.title'), 'actor' => $actor, 'rows' => $rows, 'total' => $total, 'per' => $per, 'page' => $page,
            'q' => $q, 'roleFilter' => $role, 'statusFilter' => $status, 'roles' => $roles, 'noid' => $noid,
        ]);
    }

    // ---- create one user -----------------------------------------------------------------------

    public static function userNewForm(): void
    {
        $actor = self::actor();
        self::showNew($actor, ['role' => 'student'], null);
    }

    public static function userCreate(): void
    {
        $actor = self::actor();
        $in = self::input();
        [$clean, $err] = self::check($in, $actor);
        if ($err !== null) {
            http_response_code(422);
            self::showNew($actor, $in, $err);
            return;
        }
        try {
            $res = Users::create($clean, (int) $actor['id']);
        } catch (\Throwable $e) {
            error_log((string) $e);
            http_response_code(422);
            self::showNew($actor, $in, t('users.err_uid_taken'));
            return;
        }
        Audit::log('user.created', Identity::tag(['uid' => $clean['uid'], 'serial_no' => $res['serial_no']]), $clean['role']);
        Flash::putSecret(['uid' => $res['login_id'], 'name' => $clean['full_name'], 'password' => $res['password']]);
        Flash::add('ok', t('users.created'));
        redirect('/moderator/users/' . $res['id']);
    }

    private static function showNew(array $actor, array $old, ?string $error): void
    {
        View::render('portal/user_new', [
            'title' => t('users.new'), 'actor' => $actor, 'old' => $old, 'error' => $error,
            'roles' => Users::assignableRoles($actor), 'departments' => Profile::departments(),
        ]);
    }

    // ---- one user ------------------------------------------------------------------------------

    public static function userShow(string $id): void
    {
        $actor = self::actor();
        $row = self::target($actor, $id);
        self::showUser($actor, $row, null, null);
    }

    public static function userUpdate(string $id): void
    {
        $actor = self::actor();
        $row = self::target($actor, $id);
        $self = (int) $row['id'] === (int) $actor['id'];

        $in = self::input();
        if ($self || !in_array($in['role'], Users::assignableRoles($actor), true)) $in['role'] = $row['role'];   // own role is fixed
        [$clean, $err] = self::check($in, $actor, (int) $row['id']);
        if ($err !== null) {
            http_response_code(422);
            self::showUser($actor, $row, $in, $err);
            return;
        }

        if ($actor['role'] !== 'tech_admin') unset($clean['public_slug']);       // only tech admins set custom public addresses
        Users::update((int) $row['id'], $clean);
        $changes = [];
        if ($clean['uid'] !== (string) $row['uid']) $changes[] = 'uid: ' . $row['uid'] . ' -> ' . $clean['uid'];
        if ($clean['role'] !== $row['role']) $changes[] = 'role: ' . $row['role'] . ' -> ' . $clean['role'];
        Audit::log('user.updated', Identity::tag(['uid' => $clean['uid'], 'serial_no' => $row['serial_no']]), $changes ? implode('; ', $changes) : null);
        Flash::add('ok', t('users.saved'));
        redirect('/moderator/users/' . (int) $row['id']);
    }

    public static function userStatus(string $id): void
    {
        $actor = self::actor();
        $row = self::target($actor, $id);
        if ((int) $row['id'] === (int) $actor['id']) abort(403);
        $new = post('status') === 'suspended' ? 'suspended' : 'active';

        DB::exec('UPDATE users SET status = ? WHERE id = ?', [$new, (int) $row['id']]);
        Audit::log($new === 'suspended' ? 'user.suspended' : 'user.activated', Identity::tag($row));
        Flash::add('ok', t($new === 'suspended' ? 'users.suspended_ok' : 'users.activated_ok'));
        redirect('/moderator/users/' . (int) $row['id']);
    }

    public static function userPassword(string $id): void
    {
        $actor = self::actor();
        $row = self::target($actor, $id);
        if ((int) $row['id'] === (int) $actor['id']) abort(403);

        $pw = Password::generate();
        Auth::setPassword((int) $row['id'], $pw, true);
        DB::exec(
            "UPDATE password_reset_requests SET status = 'approved', handled_by = ?, handled_at = NOW() WHERE user_id = ? AND status = 'pending'",
            [(int) $actor['id'], (int) $row['id']]
        );
        Audit::log('password.reset', Identity::tag($row));
        Flash::putSecret(['uid' => Identity::loginId($row), 'name' => display_name($row), 'password' => $pw]);
        redirect('/moderator/users/' . (int) $row['id']);
    }

    private static function showUser(array $actor, array $row, ?array $old, ?string $error): void
    {
        $self = (int) $row['id'] === (int) $actor['id'];
        $old ??= [
            'uid' => (string) $row['uid'], 'role' => $row['role'], 'email' => (string) $row['email'],
            'full_name' => (string) $row['full_name'], 'full_name_bn' => (string) $row['full_name_bn'],
            'department_id' => (string) $row['department_id'], 'academic_session' => (string) $row['academic_session'],
            'designation' => (string) $row['designation'], 'public_slug' => (string) $row['public_slug'],
        ];
        View::render('portal/user_show', [
            'title' => display_name($row), 'actor' => $actor, 'target' => $row, 'old' => $old, 'error' => $error, 'self' => $self,
            'roles' => Users::assignableRoles($actor), 'departments' => Profile::departments(), 'canSlug' => $actor['role'] === 'tech_admin',
            'missing' => Profile::missing(DB::one('SELECT * FROM user_profiles WHERE user_id = ?', [(int) $row['id']]) ?: [], $row['role']),
        ]);
    }

    // ---- validation shared by the single form, the edit form and the CSV import ----------------

    private static function input(): array
    {
        return [
            'uid' => mb_substr(post('uid'), 0, 40),
            'role' => post('role'),
            'full_name' => mb_substr(post('full_name'), 0, 190),
            'full_name_bn' => mb_substr(post('full_name_bn'), 0, 190),
            'department_id' => post('department_id'),
            'academic_session' => mb_substr(post('academic_session'), 0, 40),
            'designation' => mb_substr(post('designation'), 0, 120),
            'email' => mb_substr(post('email'), 0, 190),
            'public_slug' => mb_strtolower(mb_substr(post('public_slug'), 0, 40)),
        ];
    }

    /** @return array{0: array, 1: ?string} cleaned values (department_id as int|null) and an error message, if any */
    private static function check(array $in, array $actor, int $exceptId = 0): array
    {
        $c = $in;
        $c['department_id'] = null;
        $err = null;
        // University ID: required in university mode; optional in sequential mode (but valid and unique when given).
        if ($in['uid'] === '' ? !Identity::sequential() : !Users::validUid($in['uid'])) {
            $err = t('users.err_uid');
        } elseif (Users::uidTaken($in['uid'], $exceptId)) {
            $err = t('users.err_uid_taken');
        } elseif ($actor['role'] === 'tech_admin' && $in['public_slug'] !== '' && !\App\Services\PublicUrl::validSlug($in['public_slug'])) {
            $err = t('users.err_slug');
        } elseif ($actor['role'] === 'tech_admin' && \App\Services\PublicUrl::slugTaken($in['public_slug'], $exceptId)) {
            $err = t('users.err_slug_taken');
        } elseif (!in_array($in['role'], Users::assignableRoles($actor), true)) {
            $err = t('users.err_role');
        } elseif ($in['full_name'] === '') {
            $err = t('users.err_name');
        } elseif ($in['email'] !== '' && !filter_var($in['email'], FILTER_VALIDATE_EMAIL)) {
            $err = t('users.err_email');
        } elseif ($in['department_id'] !== '') {
            $d = ctype_digit($in['department_id']) ? DB::val('SELECT id FROM departments WHERE id = ?', [(int) $in['department_id']]) : null;
            if ($d === null) $err = t('users.err_dept'); else $c['department_id'] = (int) $d;
        }
        return [$c, $err];
    }

    // ---- CSV import ----------------------------------------------------------------------------

    public static function importForm(): void
    {
        $actor = self::actor();
        View::render('portal/import', [
            'title' => t('import.title'), 'actor' => $actor, 'roles' => Users::assignableRoles($actor), 'limit' => self::IMPORT_LIMIT,
        ]);
    }

    public static function import(): void
    {
        $actor = self::actor();
        set_time_limit(120);

        $f = $_FILES['csv'] ?? null;
        if (!is_array($f) || (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $f['tmp_name'])) {
            Flash::add('error', t('import.err_upload'));
            redirect('/moderator/users/import');
        }
        if ((int) $f['size'] > 1048576) {
            Flash::add('error', t('import.err_size'));
            redirect('/moderator/users/import');
        }
        $default = post('default_role', 'student');
        if (!in_array($default, Users::assignableRoles($actor), true)) $default = 'student';

        $text = (string) file_get_contents((string) $f['tmp_name']);
        if (str_starts_with($text, "\xEF\xBB\xBF")) $text = substr($text, 3);
        if (!mb_check_encoding($text, 'UTF-8')) {
            Flash::add('error', t('import.err_encoding'));
            redirect('/moderator/users/import');
        }
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        $first = (string) strtok($text, "\n");
        $delim = ',';
        foreach ([';', "\t"] as $d) {
            if (substr_count($first, $d) > substr_count($first, $delim)) $delim = $d;
        }

        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $text);
        rewind($fh);
        $header = fgetcsv($fh, 0, $delim, '"', '');
        $map = [];
        foreach ((array) $header as $i => $h) {
            $key = preg_replace('/[\s\-]+/', '_', strtolower(trim((string) $h)));
            if (isset(self::CSV_ALIASES[$key]) && !in_array(self::CSV_ALIASES[$key], $map, true)) $map[$i] = self::CSV_ALIASES[$key];
        }
        if ((!Identity::sequential() && !in_array('uid', $map, true)) || !in_array('full_name', $map, true)) {
            fclose($fh);
            Flash::add('error', t('import.err_columns'));
            redirect('/moderator/users/import');
        }

        $deptIndex = Users::departmentIndex();
        $report = [];
        $line = 1;
        $count = 0;
        $truncated = false;
        while (($row = fgetcsv($fh, 0, $delim, '"', '')) !== false) {
            $line++;
            if ($row === [null] || count(array_filter($row, fn($c) => trim((string) $c) !== '')) === 0) continue;
            if (++$count > self::IMPORT_LIMIT) { $truncated = true; break; }

            $cells = [];
            foreach ($map as $i => $key) $cells[$key] = trim((string) ($row[$i] ?? ''));

            $in = [
                'uid' => mb_substr($cells['uid'] ?? '', 0, 40),
                'role' => strtolower($cells['role'] ?? '') ?: $default,
                'full_name' => mb_substr($cells['full_name'] ?? '', 0, 190),
                'full_name_bn' => mb_substr($cells['full_name_bn'] ?? '', 0, 190),
                'department_id' => '',
                'academic_session' => mb_substr($cells['academic_session'] ?? '', 0, 40),
                'designation' => mb_substr($cells['designation'] ?? '', 0, 120),
                'email' => mb_substr($cells['email'] ?? '', 0, 190),
            ];
            $entry = ['line' => $line, 'uid' => $in['uid'], 'name' => $in['full_name'], 'role' => $in['role'], 'status' => 'error', 'msg' => '', 'password' => ''];

            $dv = $cells['department'] ?? '';
            if ($dv !== '') {
                $k = mb_strtolower($dv);
                if (isset($deptIndex[$k])) $in['department_id'] = (string) $deptIndex[$k];
                else { $entry['msg'] = t('users.err_dept') . ' (' . $dv . ')'; $report[] = $entry; continue; }
            }

            [$clean, $err] = self::check($in, $actor);
            if ($err !== null) {
                $entry['status'] = $err === t('users.err_uid_taken') ? 'skipped' : 'error';
                $entry['msg'] = $err;
                $report[] = $entry;
                continue;
            }
            try {
                $res = Users::create($clean, (int) $actor['id']);
                $entry['status'] = 'created';
                $entry['uid'] = $res['login_id'];       // in sequential mode this is how the admin learns the new numbers
                $entry['password'] = $res['password'];
            } catch (\Throwable $e) {
                error_log((string) $e);
                $entry['msg'] = t('import.err_row');
            }
            $report[] = $entry;
        }
        fclose($fh);

        $created = count(array_filter($report, fn($r) => $r['status'] === 'created'));
        Audit::log('user.import', null, $created . ' created of ' . count($report) . ' rows');
        $_SESSION['_import'] = ['at' => time(), 'rows' => $report, 'truncated' => $truncated];
        redirect('/moderator/users/import/result');
    }

    public static function importResult(): void
    {
        $actor = self::actor();
        $imp = $_SESSION['_import'] ?? null;
        if (!is_array($imp) || time() - (int) $imp['at'] > 1800) {
            unset($_SESSION['_import']);
            redirect('/moderator/users/import');
        }
        View::render('portal/import_result', ['title' => t('import.result_title'), 'actor' => $actor, 'rows' => $imp['rows'], 'truncated' => (bool) $imp['truncated'], 'limit' => self::IMPORT_LIMIT]);
    }

    /** Downloads the report (with the temporary passwords) once, then forgets it. */
    public static function importReport(): void
    {
        self::actor();
        $imp = $_SESSION['_import'] ?? null;
        if (!is_array($imp)) redirect('/moderator/users/import');
        unset($_SESSION['_import']);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="import-report-' . date('Ymd-His') . '.csv"');
        header('Cache-Control: no-store');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, ['line', 'login_id', 'full_name', 'role', 'result', 'temporary_password', 'note'], ',', '"', '');
        foreach ($imp['rows'] as $r) {
            fputcsv($out, array_map([self::class, 'csvSafe'], [(string) $r['line'], $r['uid'], $r['name'], $r['role'], $r['status'], $r['password'], $r['msg']]), ',', '"', '');
        }
        fclose($out);
    }

    public static function importTemplate(): void
    {
        self::actor();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="users-template.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, ['uid', 'full_name', 'full_name_bn', 'role', 'department', 'academic_session', 'designation', 'email'], ',', '"', '');
        fputcsv($out, ['2024-1-60-001', 'Rahim Uddin', 'রহিম উদ্দিন', 'student', 'computer-science', '2024-25', '', 'rahim@example.com'], ',', '"', '');
        fputcsv($out, ['T-1002', 'Dr. Karim Ahmed', '', 'teacher', 'physics', '', 'Associate Professor', ''], ',', '"', '');
        fclose($out);
    }

    /** Stops spreadsheet programs from running text that starts with = + - @ as a formula. */
    public static function csvSafe(string $s): string
    {
        return ($s !== '' && strpbrk($s[0], "=+-@\t\r") !== false) ? "'" . $s : $s;
    }

    // ---- password reset requests ---------------------------------------------------------------

    public static function resets(): void
    {
        $actor = self::actor();
        $sql = "SELECT r.id, r.user_id, r.note, r.contact, r.status, r.created_at, r.handled_at,
                       u.uid, u.serial_no, u.role, p.full_name, p.full_name_bn, h.uid AS handled_uid, h.serial_no AS handled_serial
                  FROM password_reset_requests r
                  JOIN users u ON u.id = r.user_id
                  LEFT JOIN user_profiles p ON p.user_id = u.id
                  LEFT JOIN users h ON h.id = r.handled_by";
        View::render('portal/resets', [
            'title' => t('resets.title'), 'actor' => $actor,
            'pendingRows' => DB::all("$sql WHERE r.status = 'pending' ORDER BY r.created_at ASC"),
            'doneRows' => DB::all("$sql WHERE r.status <> 'pending' ORDER BY r.handled_at DESC, r.id DESC LIMIT 20"),
        ]);
    }

    public static function resetApprove(string $id): void
    {
        $actor = self::actor();
        $req = ctype_digit($id) ? DB::one("SELECT * FROM password_reset_requests WHERE id = ? AND status = 'pending'", [(int) $id]) : null;
        if (!$req) {
            Flash::add('info', t('resets.gone'));
            redirect('/moderator/resets');
        }
        $target = Users::find((int) $req['user_id']);
        if (!$target || !Users::canManage($actor, $target) || (int) $target['id'] === (int) $actor['id']) abort(403);

        $pw = Password::generate();
        Auth::setPassword((int) $target['id'], $pw, true);
        DB::exec("UPDATE password_reset_requests SET status = 'approved', handled_by = ?, handled_at = NOW() WHERE id = ?", [(int) $actor['id'], (int) $req['id']]);
        Audit::log('reset.approved', Identity::tag($target));
        Flash::putSecret(['uid' => Identity::loginId($target), 'name' => display_name($target), 'password' => $pw]);
        redirect('/moderator/resets');
    }

    public static function resetReject(string $id): void
    {
        $actor = self::actor();
        $req = ctype_digit($id) ? DB::one("SELECT r.*, u.uid, u.serial_no FROM password_reset_requests r JOIN users u ON u.id = r.user_id WHERE r.id = ? AND r.status = 'pending'", [(int) $id]) : null;
        if ($req) {
            $target = Users::find((int) $req['user_id']);
            if (!$target || !Users::canManage($actor, $target)) abort(403);
            DB::exec("UPDATE password_reset_requests SET status = 'rejected', handled_by = ?, handled_at = NOW() WHERE id = ?", [(int) $actor['id'], (int) $req['id']]);
            Audit::log('reset.rejected', Identity::tag($req));
            Flash::add('ok', t('resets.rejected_ok'));
        }
        redirect('/moderator/resets');
    }
}
