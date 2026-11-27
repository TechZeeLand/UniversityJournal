<?php
namespace App\Controllers;

use App\Core\{Audit, Auth, DB, Flash, Markdown, View};
use App\Services\{Articles, Engagement, Notify};

/** Moderator portal: review queue, comments, reader accounts. */
final class ModerationController
{
    private static function actor(): array { return Auth::needRole('moderator', 'tech_admin'); }

    public static function articles(): void
    {
        $actor = self::actor();
        $status = in_array($_GET['status'] ?? '', ['submitted', 'changes_requested', 'published', 'rejected', 'archived'], true) ? (string) $_GET['status'] : 'submitted';
        View::render('portal/articles', ['title' => t('review.title'), 'actor' => $actor, 'status' => $status, 'rows' => Articles::queue($status), 'counts' => Articles::counts()]);
    }

    public static function articleShow(string $id): void
    {
        $actor = self::actor();
        $a = ctype_digit($id) ? Articles::find((int) $id) : null;
        if ($a === null || $a['status'] === 'draft') abort(404);
        View::render('portal/article_review', ['title' => (string) $a['title'], 'actor' => $actor, 'a' => $a, 'html' => Markdown::render((string) $a['body'])]);
    }

    public static function articleReview(string $id): void
    {
        $actor = self::actor();
        $a = ctype_digit($id) ? Articles::find((int) $id) : null;
        if ($a === null) abort(404);
        $action = post('action');
        $note = post('note');
        if (in_array($action, ['changes', 'reject'], true) && $note === '') {
            Flash::add('error', t('review.err_note'));
            redirect('/moderator/articles/' . (int) $a['id']);
        }
        if (Articles::review((int) $a['id'], $action, (int) $actor['id'], $note, post('label'))) {
            Audit::log('article.' . $action, (string) $a['id'], mb_substr((string) $a['title'], 0, 80), (int) $actor['id']);
            Notify::articleDecision($a, $action, $note);
            Flash::add('ok', t('review.done_' . $action));
        } else {
            Flash::add('error', t('review.err_state'));
        }
        redirect($action === 'archive' ? '/moderator/articles?status=published' : '/moderator/articles');
    }

    public static function articleFeature(string $id): void
    {
        $actor = self::actor();
        $a = ctype_digit($id) ? Articles::find((int) $id) : null;
        if ($a === null) abort(404);
        Articles::feature((int) $a['id'], post('on') === '1');
        Audit::log('article.feature', (string) $a['id'], post('on') === '1' ? 'on' : 'off', (int) $actor['id']);
        redirect('/moderator/articles/' . (int) $a['id']);
    }

    public static function comments(): void
    {
        $actor = self::actor();
        View::render('portal/comments', ['title' => t('review.comments'), 'actor' => $actor, 'rows' => Engagement::recent(60)]);
    }

    public static function readers(): void
    {
        $actor = self::actor();
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
        $page = max(1, (int) ($_GET['p'] ?? 1));
        $per = 25;
        $where = "u.role = 'reader'";
        $params = [];
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where .= ' AND (u.email LIKE ? OR p.full_name LIKE ?)';
            array_push($params, $like, $like);
        }
        $total = (int) DB::val("SELECT COUNT(*) FROM users u LEFT JOIN user_profiles p ON p.user_id = u.id WHERE $where", $params);
        $rows = DB::all(
            "SELECT u.id, u.uid, u.email, u.role, u.status, u.google_sub, u.last_login_at, u.created_at, p.full_name, p.full_name_bn, p.photo_path
               FROM users u LEFT JOIN user_profiles p ON p.user_id = u.id WHERE $where ORDER BY u.id DESC LIMIT $per OFFSET " . (($page - 1) * $per),
            $params
        );
        View::render('portal/readers', ['title' => t('review.readers'), 'actor' => $actor, 'rows' => $rows, 'total' => $total, 'per' => $per, 'page' => $page, 'q' => $q]);
    }
}
