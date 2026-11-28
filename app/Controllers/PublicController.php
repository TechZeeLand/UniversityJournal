<?php
namespace App\Controllers;

use App\Core\{DB, View};
use App\Services\{Articles, Pages, Profile, PublicUrl};

/** Pages visitors see: editable pages, the authors directory and public profiles. */
final class PublicController
{
    /** An editable page (/about, /privacy, your own pages). */
    public static function page(string $slug): void
    {
        $p = Pages::bySlug(mb_strtolower($slug), true);
        if ($p === null) abort(404);
        View::render('pages/page', [
            'title' => Pages::title($p),
            'page' => $p,
            'html' => \App\Core\Markdown::render(Pages::expand(Pages::body($p))),
        ]);
    }

    /** /authors: everyone whose public page is switched on and complete. */
    public static function authors(): void
    {
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
        $role = in_array($_GET['role'] ?? '', ['student', 'teacher'], true) ? (string) $_GET['role'] : '';
        $dept = ctype_digit((string) ($_GET['dept'] ?? '')) ? (int) $_GET['dept'] : 0;
        $page = max(1, (int) ($_GET['p'] ?? 1));
        $per = 24;

        $roles = [];
        foreach (['student' => 'students', 'teacher' => 'teachers'] as $r => $kind) {
            if (PublicUrl::enabled($kind) && ($role === '' || $role === $r)) $roles[] = $r;
        }
        $rows = [];
        $total = 0;
        if ($roles) {
            $in = implode(',', array_fill(0, count($roles), '?'));
            $where = "u.status = 'active' AND p.profile_complete = 1 AND p.is_public = 1 AND u.role IN ($in)";
            $params = $roles;
            if ($q !== '') {
                $like = '%' . addcslashes($q, '%_\\') . '%';
                $where .= ' AND (p.full_name LIKE ? OR p.full_name_bn LIKE ?)';
                array_push($params, $like, $like);
            }
            if ($dept > 0) { $where .= ' AND p.department_id = ?'; $params[] = $dept; }
            $total = (int) DB::val("SELECT COUNT(*) FROM users u JOIN user_profiles p ON p.user_id = u.id WHERE $where", $params);
            $off = ($page - 1) * $per;
            $rows = DB::all(
                "SELECT u.id, u.uid, u.public_id, u.public_no, u.public_slug, u.role, p.full_name, p.full_name_bn, p.department_id,
                        p.academic_session, p.designation, p.photo_path,
                        (SELECT COUNT(*) FROM articles a WHERE a.author_id = u.id AND a.status = 'published') AS article_count
                   FROM users u JOIN user_profiles p ON p.user_id = u.id
                  WHERE $where ORDER BY COALESCE(NULLIF(p.full_name, ''), p.full_name_bn), u.id LIMIT $per OFFSET $off",
                $params
            );
        }
        View::render('pages/authors', [
            'title' => t('nav.authors'), 'rows' => $rows, 'total' => $total, 'per' => $per, 'page' => $page,
            'q' => $q, 'role' => $role, 'dept' => $dept, 'departments' => Profile::departments(),
        ]);
    }

    /** /students/<key> and /teachers/<key>. */
    public static function profile(string $kind, string $key): void
    {
        $row = PublicUrl::find($kind, $key);
        if ($row === null) abort(404);
        $canon = PublicUrl::path($row);
        if ($canon !== null && strcasecmp(rawurldecode((string) basename($canon)), $key) !== 0) redirect($canon, 301);   // e.g. a custom address is set
        $links = json_decode((string) ($row['links'] ?? ''), true);
        // The person's published articles (the profile page used to show a fixed "no published articles" text)
        $page = max(1, (int) ($_GET['p'] ?? 1));
        $per = 12;
        $found = Articles::search(['author' => (int) $row['id'], 'sort' => 'newest'], $page, $per);
        View::render('pages/profile', [
            'articles' => $found['rows'], 'total' => $found['total'], 'per' => $per, 'page' => $page,
            'title' => display_name($row),
            'p' => $row,
            'links' => is_array($links) ? array_values(array_filter($links, 'is_string')) : [],
            'dept' => Profile::departmentName($row['department_id'] === null ? null : (int) $row['department_id']),
            'address' => $canon,
        ]);
    }
}
