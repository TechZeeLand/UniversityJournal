<?php
namespace App\Controllers;

use App\Core\{Audit, Auth, DB, Flash, Identity, Paths, Settings, View};
use App\Services\{Backups, Branding, FontList, Mailer, Profile, TechSettings};

/** Tech portal (tech admins only). Phase 1E adds the full settings editor here. */
final class TechController
{
    public static function index(): void
    {
        $actor = Auth::needRole('tech_admin');
        $storage = Paths::data();
        View::render('tech/index', [
            'title' => t('portal.tech_title'),
            'actor' => $actor,
            'info' => [
                'PHP' => PHP_VERSION,
                'Database' => (string) DB::val('SELECT VERSION()'),
                'Migrations' => (string) (int) DB::val('SELECT COUNT(*) FROM migrations'),
                'Data folder' => $storage,
                'Disk free' => fmt_bytes((float) @disk_free_space($storage)) . ' / ' . fmt_bytes((float) @disk_total_space($storage)),
                'Latest backup' => ($b = Backups::list()) ? fmt_dt(date('Y-m-d H:i:s', $b[0]['time'])) . ' (' . $b[0]['name'] . ')' : '—',
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
        $n = TechSettings::save($group, $vals);
        if ($n > 0 && $group === 'profiles') Profile::recalcAll();     // who counts as "complete" depends on these rules
        Flash::add('ok', $n > 0 ? t('set.saved') : t('set.nochange'));
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
            'fontNames' => $group === 'fonts' ? FontList::names() : [],
            'maxMb' => max(1, (int) setting('upload_max_image_mb', '5')),
            'mailLog' => $group === 'email' ? Mailer::recent(10) : [],
            'mailTo' => (string) (DB::val('SELECT email FROM users WHERE id = ?', [(int) $actor['id']]) ?? ''),
        ]);
    }

    /** Send a test email with the saved settings and report exactly what the mail server said. */
    public static function emailTest(): void
    {
        $actor = Auth::needRole('tech_admin');
        $to = mb_substr(post('to'), 0, 190);
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) { Flash::add('error', t('set.err_email')); redirect('/tech/settings/email'); }
        if (!Mailer::configured()) { Flash::add('error', t('email.test_off')); redirect('/tech/settings/email'); }
        if (time() - (int) ($_SESSION['mail_test_at'] ?? 0) < 5) { Flash::add('error', t('email.test_fast')); redirect('/tech/settings/email'); }
        $_SESSION['mail_test_at'] = time();
        [$text, $html] = Mailer::compose(t('email.test_h'), [t('email.test_p', ['site' => site_name()])]);
        $res = Mailer::send($to, t('email.test_s', ['site' => site_name()]), $text, $html, 'test');
        Audit::log('email.test', $to, $res['ok'] ? 'sent' : (string) $res['error'], (int) $actor['id']);
        Flash::add($res['ok'] ? 'ok' : 'error', $res['ok'] ? t('email.test_ok', ['to' => $to]) : t('email.test_fail', ['err' => (string) $res['error']]));
        redirect('/tech/settings/email');
    }

    /** Upload or remove one brand picture (logo, dark logo, icon, share picture). */
    public static function brandingSave(string $slot): void
    {
        Auth::needRole('tech_admin');
        if (!in_array($slot, Branding::SLOTS, true)) abort(404);
        if (post('do') === 'remove') {
            Branding::remove($slot);
            Audit::log('branding.removed', $slot);
            Flash::add('ok', t('brand.removed'));
            redirect('/tech/settings/branding');
        }
        $file = $_FILES['file'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            Flash::add('error', t('brand.err_none'));
            redirect('/tech/settings/branding');
        }
        try {
            $path = Branding::store($slot, $file);
            Audit::log('branding.updated', $slot, $path);
            Flash::add('ok', t('brand.saved'));
        } catch (\RuntimeException $e) {
            Flash::add('error', t('brand.' . $e->getMessage(), ['mb' => max(1, (int) setting('upload_max_image_mb', '5'))]));
        }
        redirect('/tech/settings/branding');
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
