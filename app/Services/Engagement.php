<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{DB, Settings};

/** Likes, dislikes and comments on articles. Counters on the article row are recomputed from the tables, never incremented blindly. */
final class Engagement
{
    public static function reactionsOn(): bool { return Settings::get('engage_reactions', '1') === '1'; }
    public static function commentsOn(): bool { return Settings::get('engage_comments', '1') === '1'; }
    public static function shareOn(): bool { return Settings::get('engage_share', '1') === '1'; }

    // ---- reactions ------------------------------------------------------------------------------

    /** What this person reacted: 1 like, -1 dislike, 0 nothing. */
    public static function mine(int $articleId, ?int $userId): int
    {
        if ($userId === null) return 0;
        return (int) DB::val('SELECT value FROM article_reactions WHERE article_id = ? AND user_id = ?', [$articleId, $userId]);
    }

    /**
     * Press like (1) or dislike (-1). Pressing the same button again takes the reaction back.
     * @return array{like:int, dislike:int, mine:int}
     */
    public static function react(int $articleId, int $userId, int $value): array
    {
        $value = $value >= 1 ? 1 : -1;
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $cur = DB::val('SELECT value FROM article_reactions WHERE article_id = ? AND user_id = ? FOR UPDATE', [$articleId, $userId]);
            if ($cur !== null && (int) $cur === $value) {
                DB::exec('DELETE FROM article_reactions WHERE article_id = ? AND user_id = ?', [$articleId, $userId]);
                $mine = 0;
            } else {
                DB::exec(
                    'INSERT INTO article_reactions (article_id, user_id, value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
                    [$articleId, $userId, $value]
                );
                $mine = $value;
            }
            DB::exec(
                'UPDATE articles SET
                   like_count = (SELECT COUNT(*) FROM article_reactions WHERE article_id = ? AND value = 1),
                   dislike_count = (SELECT COUNT(*) FROM article_reactions WHERE article_id = ? AND value = -1)
                 WHERE id = ?',
                [$articleId, $articleId, $articleId]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        $row = DB::one('SELECT like_count, dislike_count FROM articles WHERE id = ?', [$articleId]) ?: ['like_count' => 0, 'dislike_count' => 0];
        return ['like' => (int) $row['like_count'], 'dislike' => (int) $row['dislike_count'], 'mine' => $mine];
    }

    public static function countShare(int $articleId): void
    {
        $seen = $_SESSION['shared_articles'] ?? [];
        if (!is_array($seen) || isset($seen[$articleId])) return;
        $seen[$articleId] = 1;
        $_SESSION['shared_articles'] = array_slice($seen, -100, null, true);
        DB::exec("UPDATE articles SET share_count = share_count + 1 WHERE id = ? AND status = 'published'", [$articleId]);
    }

    // ---- comments -------------------------------------------------------------------------------

    /** Top-level comments, oldest first, each with a 'replies' list. Moderators also get hidden ones. */
    public static function comments(int $articleId, bool $includeHidden): array
    {
        $rows = DB::all(
            'SELECT c.id, c.user_id, c.parent_id, c.body, c.status, c.created_at, u.role, u.uid, p.full_name, p.full_name_bn, p.photo_path
               FROM article_comments c JOIN users u ON u.id = c.user_id LEFT JOIN user_profiles p ON p.user_id = u.id
              WHERE c.article_id = ?' . ($includeHidden ? '' : " AND c.status = 'visible'") . ' ORDER BY c.created_at ASC, c.id ASC LIMIT 500',
            [$articleId]
        );
        $top = [];
        foreach ($rows as $r) {
            if ($r['parent_id'] === null) { $r['replies'] = []; $top[(int) $r['id']] = $r; }
        }
        foreach ($rows as $r) {
            if ($r['parent_id'] !== null && isset($top[(int) $r['parent_id']])) $top[(int) $r['parent_id']]['replies'][] = $r;
        }
        return array_values($top);
    }

    /** @return array{ok:bool, error?:string, wait?:int} error is a key under "eng." in the language files */
    public static function addComment(int $articleId, int $userId, string $body, ?int $parentId): array
    {
        $body = trim(str_replace("\r", '', $body));
        $max = max(100, (int) Settings::get('comment_max_len', '1500'));
        if ($body === '') return ['ok' => false, 'error' => 'err_empty'];
        if (mb_strlen($body) > $max) return ['ok' => false, 'error' => 'err_long'];

        $cool = max(0, (int) Settings::get('comment_cooldown_sec', '20'));
        if ($cool > 0) {
            $age = DB::val('SELECT TIMESTAMPDIFF(SECOND, MAX(created_at), NOW()) FROM article_comments WHERE user_id = ?', [$userId]);
            if ($age !== null && (int) $age < $cool) return ['ok' => false, 'error' => 'err_fast', 'wait' => $cool - (int) $age];
        }
        if ($parentId !== null) {
            $p = DB::one('SELECT id, parent_id FROM article_comments WHERE id = ? AND article_id = ? AND status = \'visible\'', [$parentId, $articleId]);
            if ($p === null) return ['ok' => false, 'error' => 'err_parent'];
            $parentId = $p['parent_id'] !== null ? (int) $p['parent_id'] : (int) $p['id'];     // replies stay one level deep
        }
        DB::exec('INSERT INTO article_comments (article_id, user_id, parent_id, body) VALUES (?, ?, ?, ?)', [$articleId, $userId, $parentId, $body]);
        self::recount($articleId);
        return ['ok' => true];
    }

    public static function recount(int $articleId): void
    {
        DB::exec(
            "UPDATE articles SET comment_count = (SELECT COUNT(*) FROM article_comments WHERE article_id = ? AND status = 'visible') WHERE id = ?",
            [$articleId, $articleId]
        );
    }

    public static function comment(int $id): ?array
    {
        return DB::one('SELECT id, article_id, user_id, parent_id, status FROM article_comments WHERE id = ?', [$id]);
    }

    public static function setHidden(int $id, bool $hidden): void
    {
        $c = self::comment($id);
        if ($c === null) return;
        DB::exec('UPDATE article_comments SET status = ? WHERE id = ? OR parent_id = ?', [$hidden ? 'hidden' : 'visible', $id, $id]);
        self::recount((int) $c['article_id']);
    }

    public static function deleteComment(int $id): void
    {
        $c = self::comment($id);
        if ($c === null) return;
        DB::exec('DELETE FROM article_comments WHERE id = ?', [$id]);    // replies go with it (foreign key cascade)
        self::recount((int) $c['article_id']);
    }

    /** Latest comments across the site, for the moderator overview. */
    public static function recent(int $n = 30): array
    {
        $n = max(1, min(100, $n));
        return DB::all(
            "SELECT c.id, c.article_id, c.body, c.status, c.created_at, a.title, a.slug, u.role, u.uid, p.full_name, p.full_name_bn
               FROM article_comments c JOIN articles a ON a.id = c.article_id JOIN users u ON u.id = c.user_id LEFT JOIN user_profiles p ON p.user_id = u.id
              ORDER BY c.id DESC LIMIT $n"
        );
    }
}
