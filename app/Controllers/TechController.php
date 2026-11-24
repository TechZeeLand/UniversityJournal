<?php
namespace App\Controllers;

use App\Core\{Auth, DB, View};

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
            $where = '(a.action LIKE ? OR a.target LIKE ? OR u.uid LIKE ? OR a.ip LIKE ?)';
            $params = [$like, $like, $like, $like];
        }
        $total = (int) DB::val("SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE $where", $params);
        $off = ($page - 1) * $per;
        $rows = DB::all(
            "SELECT a.id, a.action, a.target, a.detail, a.ip, a.created_at, u.uid AS actor
               FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
              WHERE $where ORDER BY a.id DESC LIMIT $per OFFSET $off",
            $params
        );
        View::render('tech/audit', ['title' => t('audit.title'), 'actor' => $actor, 'rows' => $rows, 'total' => $total, 'per' => $per, 'page' => $page, 'q' => $q]);
    }
}
