<?php
namespace App\Controllers;

use App\Core\{Audit, Auth, DB, Flash, Identity, Settings, View};
use App\Services\TechSettings;

/** Tech portal (tech admins only). Phase 1E adds the full settings editor here. */
final class TechController
{
    public static function index(): void
    {
        $actor = Auth::needRole('tech_admin');
        $storage = '/var/www/storage';
        View::render('tech/index', [
            'title' => t('portal.tech_title'),
            'actor' => $actor,
            'info' => [
                'PHP' => PHP_VERSION,
                'Database' => (string) DB::val('SELECT VERSION()'),
                'Migrations' => (string) (int) DB::val('SELECT COUNT(*) FROM migrations'),
                'Storage free' => fmt_bytes((float) @disk_free_space($storage)) . ' / ' . fmt_bytes((float) @disk_total_space($storage)),
                'Time zone' => date_default_timezone_get() . ' (' . date('P') . ')',
                'Server time' => date('Y-m-d H:i:s'),
            ],
            'source' => (string) setting('source_repo_url', ''),
        ]);
    }

    public static function audit(): void
    {
        $actor = Auth::needRole('tech_admin');
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
        $page = max(1, (int) ($_GET['p'] ?? 1));
        $per = 50;

        $where = '1=1';
        $params = [];
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where = '(a.action LIKE ? OR a.target LIKE ? OR u.uid LIKE ? OR CAST(u.serial_no AS CHAR) LIKE ? OR a.ip LIKE ?)';
            $params = [$like, $like, $like, $like, $like];
        }
        $total = (int) DB::val("SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE $where", $params);
        $off = ($page - 1) * $per;
        $rows = DB::all(
            "SELECT a.id, a.action, a.target, a.detail, a.ip, a.created_at, COALESCE(u.uid, CONCAT('#', u.serial_no)) AS actor
               FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
              WHERE $where ORDER BY a.id DESC LIMIT $per OFFSET $off",
            $params
        );
        View::render('tech/audit', ['title' => t('audit.title'), 'actor' => $actor, 'rows' => $rows, 'total' => $total, 'per' => $per, 'page' => $page, 'q' => $q]);
    }

    // ---- settings ------------------------------------------------------------------------------

    public static function settingsIndex(): void
    {
        Auth::needRole('tech_admin');
        redirect('/tech/settings/accounts');
    }

    public static function settings(string $group): void
    {
        $actor = Auth::needRole('tech_admin');
        if (!isset(TechSettings::groups()[$group])) abort(404);
        self::showSettings($actor, $group, TechSettings::current($group), []);
    }

    public static function settingsSave(string $group): void
    {
        $actor = Auth::needRole('tech_admin');
        if (!isset(TechSettings::groups()[$group])) abort(404);
        [$vals, $errors] = TechSettings::validate($group, $_POST);
        if ($errors) {
            http_response_code(422);
            self::showSettings($actor, $group, $vals, $errors);
            return;
        }
        Flash::add('ok', TechSettings::save($group, $vals) > 0 ? t('set.saved') : t('set.nochange'));
        redirect('/tech/settings/' . $group);
    }

    private static function showSettings(array $actor, string $group, array $values, array $errors): void
    {
        $idInfo = null;
        if ($group === 'accounts') {
            $idInfo = [
                'mode' => Identity::mode(),
                'total' => (int) DB::val('SELECT COUNT(*) FROM users'),
                'noUid' => Identity::withoutUid(),
                'next' => (int) (DB::val("SELECT v FROM counters WHERE k = 'serial_no'") ?? 0),
            ];
        }
        View::render('tech/settings', [
            'title' => t('settings.title'), 'actor' => $actor, 'group' => $group, 'groups' => TechSettings::groups(),
            'fields' => TechSettings::groups()[$group]['fields'], 'values' => $values, 'errors' => $errors, 'idInfo' => $idInfo,
        ]);
    }

    /** Switch between university IDs and generated login numbers. Guarded so nobody can be locked out. */
    public static function idModeSave(): void
    {
        $actor = Auth::needRole('tech_admin');
        $new = post('id_mode');
        $old = Identity::mode();
        if (!in_array($new, Identity::MODES, true)) abort(404);
        if ($new === $old) {
            Flash::add('info', t('id.unchanged'));
            redirect('/tech/settings/accounts');
        }
        if ($new === Identity::UNIVERSITY && ($missing = Identity::withoutUid()) > 0) {
            // Those accounts could not sign in at all, so refuse until every account has a university ID.
            Flash::add('error', t('id.err_missing_uid', ['n' => $missing]));
            redirect('/tech/settings/accounts');
        }
        Settings::set('id_mode', $new, 'accounts');
        Audit::log('settings.id_mode', $new, $old . ' -> ' . $new);
        $me = DB::one('SELECT uid, serial_no FROM users WHERE id = ?', [(int) $actor['id']]) ?: [];
        Flash::add('ok', t($new === Identity::SEQUENTIAL ? 'id.switched_seq' : 'id.switched_uni', ['id' => Identity::loginId($me)]));
        redirect('/tech/settings/accounts');
    }
}
