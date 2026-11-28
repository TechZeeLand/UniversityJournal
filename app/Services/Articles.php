<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{DB, I18n, Settings};

/** Articles: reading lists, search and sorting, the writer's drafts and the review steps. */
final class Articles
{
    public const LANGS = ['en', 'bn', 'ar'];
    public const SORTS = ['newest', 'oldest', 'top_rated', 'most_liked', 'most_read', 'most_discussed'];
    public const PERIODS = ['7d' => 7, '30d' => 30, '1y' => 365];
    public const LABELS = ['none', 'editor_reviewed', 'peer_reviewed'];

    private const SELECT = 'a.id, a.author_id, a.type_id, a.department_id, a.lang, a.title, a.slug, a.summary, a.cover_path, a.status, a.review_label,
        a.is_featured, a.like_count, a.dislike_count, a.comment_count, a.view_count, a.share_count, a.reading_minutes, a.published_at, a.created_at, a.updated_at,
        t.name_en AS type_en, t.name_bn AS type_bn, t.slug AS type_slug, d.name_en AS dept_en, d.name_bn AS dept_bn,
        u.uid, u.role, u.status AS author_status, u.public_id, u.public_no, u.public_slug,
        p.full_name, p.full_name_bn, p.photo_path, p.profile_complete, p.is_public';
    private const FROM = 'FROM articles a JOIN users u ON u.id = a.author_id LEFT JOIN user_profiles p ON p.user_id = u.id
        LEFT JOIN article_types t ON t.id = a.type_id LEFT JOIN departments d ON d.id = a.department_id';

    // ---- small helpers -------------------------------------------------------------------------

    public static function url(array $a): string
    {
        $slug = (string) ($a['slug'] ?? '');
        return '/article/' . (int) $a['id'] . ($slug !== '' ? '/' . rawurlencode($slug) : '');
    }

    /** Address of the author's public page, or null when they have none (or it is hidden). */
    public static function authorPath(array $a): ?string
    {
        $u = $a;
        $u['status'] = $a['author_status'] ?? 'active';
        return PublicUrl::visible($u) ? PublicUrl::path($u) : null;
    }

    public static function slugify(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = (string) preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '-', $s);
        $s = trim(mb_substr(trim($s, '-'), 0, 80), '-');
        return $s !== '' ? $s : 'article';
    }

    /** Minutes to read, at about 200 words a minute. */
    public static function readingMinutes(string $body): int
    {
        $words = preg_split('/\s+/u', trim(strip_tags($body)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return max(1, (int) ceil(count($words) / 200));
    }

    /** The summary, or the start of the text with the Markdown marks removed. */
    public static function excerpt(array $a, int $len = 160): string
    {
        $s = trim((string) ($a['summary'] ?? ''));
        if ($s === '' && isset($a['body'])) {
            $s = trim((string) preg_replace('/[#*>`_\[\]!()-]+|https?:\/\/\S+/u', ' ', (string) $a['body']));
            $s = (string) preg_replace('/\s+/u', ' ', $s);
        }
        return mb_strlen($s) > $len ? rtrim(mb_substr($s, 0, $len - 1)) . '…' : $s;
    }

    public static function typeName(array $a): string
    {
        $bn = trim((string) ($a['type_bn'] ?? ''));
        return (I18n::locale() === 'bn' && $bn !== '') ? $bn : (string) ($a['type_en'] ?? '');
    }

    public static function deptName(array $a): string
    {
        $bn = trim((string) ($a['dept_bn'] ?? ''));
        return (I18n::locale() === 'bn' && $bn !== '') ? $bn : (string) ($a['dept_en'] ?? '');
    }

    /** @return list<array{id:int,slug:string,name_en:string,name_bn:?string}> */
    public static function types(): array
    {
        return DB::all('SELECT id, slug, name_en, name_bn FROM article_types WHERE active = 1 ORDER BY sort_order, id');
    }

    public static function typeLabel(array $t): string
    {
        $bn = trim((string) ($t['name_bn'] ?? ''));
        return (I18n::locale() === 'bn' && $bn !== '') ? $bn : (string) $t['name_en'];
    }

    // ---- reading ------------------------------------------------------------------------------

    public static function find(int $id): ?array
    {
        return DB::one('SELECT ' . self::SELECT . ', a.body, a.review_note, a.submitted_at ' . self::FROM . ' WHERE a.id = ?', [$id]);
    }

    /** A published article by an active author, or null. */
    public static function published(int $id): ?array
    {
        $a = self::find($id);
        return ($a !== null && $a['status'] === 'published' && $a['author_status'] === 'active') ? $a : null;
    }

    public static function latest(int $n, int $offset = 0): array
    {
        $n = max(1, min(60, $n));
        return DB::all(
            'SELECT ' . self::SELECT . ', a.body ' . self::FROM . " WHERE a.status = 'published' AND u.status = 'active'
              ORDER BY a.published_at DESC, a.id DESC LIMIT $n OFFSET " . max(0, $offset)
        );
    }

    /**
     * Published articles with filters.
     * @param array{q?:string,type?:string,dept?:int,lang?:string,author?:int,sort?:string,period?:string,from?:string,to?:string} $f
     * @return array{rows:list<array>, total:int}
     */
    public static function search(array $f, int $page, int $per): array
    {
        $where = ["a.status = 'published'", "u.status = 'active'"];
        $params = [];
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . addcslashes(mb_substr($q, 0, 80), '%_\\') . '%';
            $where[] = '(a.title LIKE ? OR a.summary LIKE ? OR p.full_name LIKE ? OR p.full_name_bn LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }
        if (($f['type'] ?? '') !== '') { $where[] = 't.slug = ?'; $params[] = (string) $f['type']; }
        if (!empty($f['dept'])) { $where[] = 'a.department_id = ?'; $params[] = (int) $f['dept']; }
        if (in_array($f['lang'] ?? '', self::LANGS, true)) { $where[] = 'a.lang = ?'; $params[] = (string) $f['lang']; }
        if (!empty($f['author'])) { $where[] = 'a.author_id = ?'; $params[] = (int) $f['author']; }
        if (isset(self::PERIODS[$f['period'] ?? ''])) {
            $where[] = 'a.published_at >= (NOW() - INTERVAL ' . self::PERIODS[$f['period']] . ' DAY)';
        }
        foreach (['from' => '>=', 'to' => '<'] as $k => $op) {
            $d = (string) ($f[$k] ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1 && strtotime($d) !== false) {
                $where[] = 'a.published_at ' . $op . ' ?';
                $params[] = $k === 'to' ? date('Y-m-d', strtotime($d . ' +1 day')) : $d;
            }
        }
        $order = match ($f['sort'] ?? 'newest') {
            'oldest' => 'a.published_at ASC, a.id ASC',
            'top_rated' => '(CAST(a.like_count AS SIGNED) - CAST(a.dislike_count AS SIGNED)) DESC, a.like_count DESC, a.published_at DESC',
            'most_liked' => 'a.like_count DESC, a.published_at DESC',
            'most_read' => 'a.view_count DESC, a.published_at DESC',
            'most_discussed' => 'a.comment_count DESC, a.published_at DESC',
            default => 'a.published_at DESC, a.id DESC',
        };
        $w = implode(' AND ', $where);
        $total = (int) DB::val('SELECT COUNT(*) ' . self::FROM . " WHERE $w", $params);
        $per = max(1, min(60, $per));
        $off = (max(1, $page) - 1) * $per;
        $rows = DB::all('SELECT ' . self::SELECT . ', a.body ' . self::FROM . " WHERE $w ORDER BY $order LIMIT $per OFFSET $off", $params);
        return ['rows' => $rows, 'total' => $total];
    }

    /** The reading list of one person: published articles they bookmarked, newest bookmark first. @return array{rows:list<array>, total:int} */
    public static function bookmarked(int $userId, int $page, int $per): array
    {
        $per = max(1, min(60, $per));
        $off = (max(1, $page) - 1) * $per;
        $join = 'JOIN article_bookmarks b ON b.article_id = a.id AND b.user_id = ?';
        $total = (int) DB::val('SELECT COUNT(*) ' . self::FROM . " $join WHERE a.status = 'published' AND u.status = 'active'", [$userId]);
        $rows = DB::all(
            'SELECT ' . self::SELECT . ', a.body ' . self::FROM . " $join WHERE a.status = 'published' AND u.status = 'active'
              ORDER BY b.created_at DESC LIMIT $per OFFSET $off",
            [$userId]
        );
        return ['rows' => $rows, 'total' => $total];
    }

    public static function related(array $a, int $n = 3): array
    {
        return DB::all(
            'SELECT ' . self::SELECT . ', a.body ' . self::FROM . " WHERE a.status = 'published' AND u.status = 'active' AND a.id <> ?
              ORDER BY (a.type_id <=> ?) DESC, (a.department_id <=> ?) DESC, a.published_at DESC LIMIT " . max(1, min(12, $n)),
            [(int) $a['id'], $a['type_id'], $a['department_id']]
        );
    }

    /** Count one read per visitor session. */
    public static function countView(int $id): void
    {
        $seen = $_SESSION['seen_articles'] ?? [];
        if (!is_array($seen) || isset($seen[$id])) return;
        $seen[$id] = 1;
        $_SESSION['seen_articles'] = array_slice($seen, -200, null, true);
        DB::exec('UPDATE articles SET view_count = view_count + 1 WHERE id = ?', [$id]);
    }

    // ---- the writer's side ---------------------------------------------------------------------

    /** Articles of one author (all statuses). */
    public static function byAuthor(int $authorId): array
    {
        return DB::all(
            'SELECT ' . self::SELECT . ' ' . self::FROM . ' WHERE a.author_id = ? ORDER BY a.updated_at DESC',
            [$authorId]
        );
    }

    /** @param array{title:string,summary:string,body:string,lang:string,type_id:?int,department_id:?int,cover_path?:?string} $d */
    public static function create(int $authorId, array $d): int
    {
        DB::exec(
            "INSERT INTO articles (author_id, type_id, department_id, lang, title, slug, summary, body, cover_path, reading_minutes, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')",
            [$authorId, $d['type_id'], $d['department_id'], $d['lang'], $d['title'], self::slugify($d['title']), nn($d['summary']), $d['body'],
             $d['cover_path'] ?? null, self::readingMinutes($d['body'])]
        );
        return DB::lastId();
    }

    public static function update(int $id, array $d): void
    {
        DB::exec(
            'UPDATE articles SET type_id = ?, department_id = ?, lang = ?, title = ?, slug = ?, summary = ?, body = ?, cover_path = ?, reading_minutes = ? WHERE id = ?',
            [$d['type_id'], $d['department_id'], $d['lang'], $d['title'], self::slugify($d['title']), nn($d['summary']), $d['body'],
             $d['cover_path'] ?? null, self::readingMinutes($d['body']), $id]
        );
    }

    /** May this person write articles, and is their profile complete enough to submit? */
    public static function canWrite(array $u): bool { return in_array($u['role'], ['student', 'teacher'], true); }

    public static function submit(int $id): string
    {
        $direct = Settings::get('publish_needs_review', '1') !== '1';
        if ($direct) {
            DB::exec("UPDATE articles SET status = 'published', published_at = COALESCE(published_at, NOW()), submitted_at = NOW(), review_note = NULL WHERE id = ?", [$id]);
            return 'published';
        }
        DB::exec("UPDATE articles SET status = 'submitted', submitted_at = NOW(), review_note = NULL WHERE id = ?", [$id]);
        return 'submitted';
    }

    public static function delete(int $id): void { DB::exec('DELETE FROM articles WHERE id = ?', [$id]); }

    // ---- review (moderators) --------------------------------------------------------------------

    public static function queue(string $status = 'submitted'): array
    {
        return DB::all(
            'SELECT ' . self::SELECT . ' ' . self::FROM . ' WHERE a.status = ? ORDER BY a.submitted_at ASC, a.id ASC LIMIT 200',
            [$status]
        );
    }

    public static function counts(): array
    {
        $out = ['draft' => 0, 'submitted' => 0, 'changes_requested' => 0, 'published' => 0, 'rejected' => 0, 'archived' => 0];
        foreach (DB::all('SELECT status, COUNT(*) AS n FROM articles GROUP BY status') as $r) $out[$r['status']] = (int) $r['n'];
        return $out;
    }

    public static function review(int $id, string $action, int $by, string $note = '', string $label = 'none'): bool
    {
        $label = in_array($label, self::LABELS, true) ? $label : 'none';
        $note = mb_substr(trim($note), 0, 1500);
        $sql = match ($action) {
            'publish' => "UPDATE articles SET status = 'published', published_at = COALESCE(published_at, NOW()), review_label = ?, review_note = ?, reviewed_by = ? WHERE id = ? AND status IN ('submitted','archived','changes_requested')",
            'changes' => "UPDATE articles SET status = 'changes_requested', review_label = 'none', review_note = ?, reviewed_by = ? WHERE id = ? AND status = 'submitted'",
            'reject' => "UPDATE articles SET status = 'rejected', review_note = ?, reviewed_by = ? WHERE id = ? AND status IN ('submitted','changes_requested')",
            'archive' => "UPDATE articles SET status = 'archived', reviewed_by = ? WHERE id = ? AND status = 'published'",
            default => null,
        };
        if ($sql === null) return false;
        $params = match ($action) {
            'publish' => [$label, nn($note), $by, $id],
            'changes', 'reject' => [nn($note), $by, $id],
            default => [$by, $id],
        };
        return DB::exec($sql, $params) > 0;
    }

    public static function feature(int $id, bool $on): void
    {
        DB::exec("UPDATE articles SET is_featured = ? WHERE id = ? AND status = 'published'", [$on ? 1 : 0, $id]);
    }

    /** Numbers for the home page: published articles, authors with a published article, total reads. */
    public static function stats(): array
    {
        $r = DB::one("SELECT COUNT(*) AS n, COUNT(DISTINCT author_id) AS authors, COALESCE(SUM(view_count), 0) AS read_total FROM articles WHERE status = 'published'");
        return ['articles' => (int) ($r['n'] ?? 0), 'authors' => (int) ($r['authors'] ?? 0), 'reads' => (int) ($r['read_total'] ?? 0)];
    }
}
