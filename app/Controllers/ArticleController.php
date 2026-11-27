<?php
namespace App\Controllers;

use App\Core\{Audit, Auth, Flash, Markdown, Request, View};
use App\Services\{Articles, Engagement, Notify, Profile, Readers};

/** What visitors and signed-in readers do with published articles: read, explore, react, comment, share. */
final class ArticleController
{
    private static function wantsJson(): bool
    {
        return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
    }

    private static function json(array $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ---- home and explore -----------------------------------------------------------------------

    public static function home(): void
    {
        $n = max(4, min(40, (int) setting('home_latest_count', '20')));
        $latest = setting('home_show_latest', '1') === '1' ? Articles::latest($n) : [];
        $popular = $latest ? Articles::search(['sort' => 'most_read', 'period' => '30d'], 1, 5)['rows'] : [];
        View::render('pages/home', ['title' => null, 'latest' => $latest, 'popular' => $popular, 'stats' => Articles::stats()]);
    }

    public static function explore(): void
    {
        $sorts = Articles::SORTS;
        $f = [
            'q' => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80),
            'type' => preg_match('/^[a-z0-9-]{1,60}$/', (string) ($_GET['type'] ?? '')) === 1 ? (string) $_GET['type'] : '',
            'dept' => ctype_digit((string) ($_GET['dept'] ?? '')) ? (int) $_GET['dept'] : 0,
            'author' => ctype_digit((string) ($_GET['author'] ?? '')) ? (int) $_GET['author'] : 0,
            'lang' => in_array($_GET['lang'] ?? '', Articles::LANGS, true) ? (string) $_GET['lang'] : '',
            'sort' => in_array($_GET['sort'] ?? '', $sorts, true) ? (string) $_GET['sort'] : 'newest',
            'period' => isset(Articles::PERIODS[$_GET['period'] ?? '']) ? (string) $_GET['period'] : '',
            'from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['from'] ?? '')) === 1 ? (string) $_GET['from'] : '',
            'to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['to'] ?? '')) === 1 ? (string) $_GET['to'] : '',
        ];
        $page = max(1, (int) ($_GET['p'] ?? 1));
        $per = max(6, min(48, (int) setting('explore_per_page', '12')));
        $res = Articles::search($f, $page, $per);
        View::render('pages/explore', [
            'title' => t('nav.explore'), 'f' => $f, 'rows' => $res['rows'], 'total' => $res['total'], 'page' => $page, 'per' => $per,
            'types' => Articles::types(), 'departments' => Profile::departments(),
        ]);
    }

    // ---- one article ------------------------------------------------------------------------------

    public static function show(string $id, string $slug = ''): void
    {
        $a = ctype_digit($id) ? Articles::find((int) $id) : null;
        if ($a === null) abort(404);
        $u = Auth::user();
        $published = $a['status'] === 'published' && $a['author_status'] === 'active';
        $preview = false;
        if (!$published) {
            // drafts and queued articles are visible to their author and to moderators only
            if ($u === null || ((int) $u['id'] !== (int) $a['author_id'] && !Auth::isAdmin($u))) abort(404);
            $preview = true;
        } elseif ($slug !== (string) $a['slug']) {
            redirect(Articles::url($a), 301);
        }
        if ($published) Articles::countView((int) $a['id']);

        $isAdmin = Auth::isAdmin($u);
        View::render('pages/article', [
            'title' => (string) $a['title'],
            'a' => $a,
            'html' => Markdown::render((string) $a['body']),
            'preview' => $preview,
            'authorPath' => Articles::authorPath($a),
            'mine' => Engagement::mine((int) $a['id'], $u ? (int) $u['id'] : null),
            'comments' => ($published && Engagement::commentsOn()) ? Engagement::comments((int) $a['id'], $isAdmin) : [],
            'related' => $published ? Articles::related($a, 3) : [],
            'canModerate' => $isAdmin,
            'meta' => ['description' => Articles::excerpt($a, 200), 'image' => $a['cover_path'] ? '/uploads/' . $a['cover_path'] : null, 'type' => 'article'],
        ]);
    }

    // ---- reactions, comments, share ---------------------------------------------------------------

    private static function publishedOr404(string $id): array
    {
        $a = ctype_digit($id) ? Articles::published((int) $id) : null;
        if ($a === null) abort(404);
        return $a;
    }

    public static function react(string $id): void
    {
        $a = self::publishedOr404($id);
        $u = Auth::user();
        $back = Articles::url($a) . '#reactions';
        if ($u === null) {
            if (self::wantsJson()) self::json(['ok' => false, 'login' => '/login?next=' . rawurlencode(Articles::url($a))], 401);
            Flash::add('info', t('auth.need_login'));
            redirect('/login?next=' . rawurlencode(Articles::url($a)));
        }
        if (!Engagement::reactionsOn()) abort(403);
        if (!Readers::mayInteract($u)) {
            Flash::add('error', t('email.need_verified'));
            if (self::wantsJson()) self::json(['ok' => false, 'redirect' => '/account'], 403);
            redirect('/account');
        }
        $v = post('value') === 'dislike' ? -1 : 1;
        $r = Engagement::react((int) $a['id'], (int) $u['id'], $v);
        if (self::wantsJson()) self::json(['ok' => true] + $r);
        redirect($back);
    }

    public static function commentAdd(string $id): void
    {
        $a = self::publishedOr404($id);
        $u = Auth::user();
        $back = Articles::url($a);
        if ($u === null) {
            Flash::add('info', t('auth.need_login'));
            redirect('/login?next=' . rawurlencode($back));
        }
        if (!Engagement::commentsOn()) abort(403);
        if (!Readers::mayInteract($u)) {
            Flash::add('error', t('email.need_verified'));
            redirect('/account');
        }
        $parent = ctype_digit(post('parent')) && post('parent') !== '0' ? (int) post('parent') : null;
        $res = Engagement::addComment((int) $a['id'], (int) $u['id'], (string) ($_POST['body'] ?? ''), $parent);
        if ($res['ok']) {
            Notify::commentPosted($a, (int) $u['id'], $parent);
            Flash::add('ok', t('eng.posted'));
        } else {
            Flash::add('error', t('eng.' . $res['error'], ['n' => $res['wait'] ?? 0]));
        }
        redirect($back . '#comments');
    }

    /** Hide, show or delete a comment: moderators any comment, people their own (delete only). */
    public static function commentAction(string $id, string $action): void
    {
        $u = Auth::need();
        $c = ctype_digit($id) ? Engagement::comment((int) $id) : null;
        if ($c === null) abort(404);
        $art = Articles::find((int) $c['article_id']);
        if ($art === null) abort(404);
        $own = (int) $c['user_id'] === (int) $u['id'];
        if ($action === 'delete') {
            if (!$own && !Auth::isAdmin($u)) abort(403);
            Engagement::deleteComment((int) $c['id']);
            if (!$own) Audit::log('comment.delete', (string) $c['id'], 'article ' . $c['article_id'], (int) $u['id']);
        } else {
            if (!Auth::isAdmin($u)) abort(403);
            Engagement::setHidden((int) $c['id'], $action === 'hide');
            Audit::log('comment.' . $action, (string) $c['id'], 'article ' . $c['article_id'], (int) $u['id']);
        }
        redirect(Articles::url($art) . '#comments');
    }

    /** Called by the page when someone uses a share button (counted once per visit). */
    public static function shared(string $id): void
    {
        $a = self::publishedOr404($id);
        if (Engagement::shareOn()) Engagement::countShare((int) $a['id']);
        self::json(['ok' => true]);
    }
}
