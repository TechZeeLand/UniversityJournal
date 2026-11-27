<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{DB, I18n, Settings};

/** Emails the site sends on its own: verify address, reset link, article decisions, comments. All go out after the page is delivered. */
final class Notify
{
    /** The address and language of a person, only when they may be written to. */
    private static function target(int $userId, bool $needVerified, bool $respectOptOut): ?array
    {
        $u = DB::one('SELECT id, email, email_verified_at, email_notify, pref_lang, status FROM users WHERE id = ?', [$userId]);
        if (!$u || $u['status'] !== 'active' || trim((string) $u['email']) === '' || filter_var($u['email'], FILTER_VALIDATE_EMAIL) === false) return null;
        if ($needVerified && $u['email_verified_at'] === null) return null;
        if ($respectOptOut && (int) $u['email_notify'] !== 1) return null;
        $lang = (string) ($u['pref_lang'] ?? '');
        if (!isset(I18n::LOCALES[$lang])) $lang = (string) Settings::get('default_lang', 'en');
        return ['email' => (string) $u['email'], 'lang' => $lang];
    }

    private static function accountPath(int $userId): string
    {
        $role = (string) DB::val('SELECT role FROM users WHERE id = ?', [$userId]);
        return $role === 'reader' ? '/account' : '/profile';
    }

    private static function footer(int $userId): string
    {
        return t('email.footer_opt', ['url' => abs_url(self::accountPath($userId))]);
    }

    // ---- address verification --------------------------------------------------------------------

    /** Send a verification link. @return bool false when nothing could be sent (mail off, no address, already verified) */
    public static function verification(int $userId): bool
    {
        if (!Mailer::configured()) return false;
        $u = DB::one('SELECT email, email_verified_at, pref_lang FROM users WHERE id = ?', [$userId]);
        if (!$u || trim((string) $u['email']) === '' || $u['email_verified_at'] !== null) return false;
        $token = EmailTokens::issue($userId, EmailTokens::VERIFY, (string) $u['email']);
        $lang = isset(I18n::LOCALES[(string) $u['pref_lang']]) ? (string) $u['pref_lang'] : I18n::locale();
        I18n::using($lang, static function () use ($u, $token): void {
            [$text, $html] = Mailer::compose(
                t('email.verify_h'),
                [t('email.verify_p1', ['site' => site_name()]), t('email.verify_p2', ['days' => (int) (EmailTokens::ttlMinutes(EmailTokens::VERIFY) / 1440)])],
                t('email.verify_btn'),
                EmailTokens::link(EmailTokens::VERIFY, $token),
                t('email.footer_ignore')
            );
            Mailer::later((string) $u['email'], t('email.verify_s', ['site' => site_name()]), $text, $html, 'verify');
        });
        return true;
    }

    // ---- password reset ----------------------------------------------------------------------------

    public static function passwordReset(int $userId): bool
    {
        if (!Mailer::configured()) return false;
        $u = self::target($userId, false, false);
        if ($u === null) return false;
        $token = EmailTokens::issue($userId, EmailTokens::RESET, $u['email']);
        I18n::using($u['lang'], static function () use ($u, $token): void {
            [$text, $html] = Mailer::compose(
                t('email.reset_h'),
                [t('email.reset_p1', ['site' => site_name()]), t('email.reset_p2', ['min' => EmailTokens::ttlMinutes(EmailTokens::RESET)])],
                t('email.reset_btn'),
                EmailTokens::link(EmailTokens::RESET, $token),
                t('email.footer_ignore')
            );
            Mailer::later($u['email'], t('email.reset_s', ['site' => site_name()]), $text, $html, 'reset');
        });
        return true;
    }

    // ---- article decisions --------------------------------------------------------------------------

    /** @param string $action publish | changes | reject */
    public static function articleDecision(array $a, string $action, string $note): void
    {
        if (!Mailer::configured() || Settings::get('notify_author_status', '1') !== '1') return;
        if (!in_array($action, ['publish', 'changes', 'reject'], true)) return;
        $uid = (int) $a['author_id'];
        $u = self::target($uid, true, true);
        if ($u === null) return;
        $url = $action === 'publish' ? abs_url(Articles::url($a)) : abs_url('/write/' . (int) $a['id']);
        I18n::using($u['lang'], function () use ($u, $a, $action, $note, $url, $uid): void {
            $lines = [t('email.art_' . $action, ['title' => (string) $a['title']])];
            if ($note !== '' && $action !== 'publish') $lines[] = t('email.art_note') . "\n" . $note;
            [$text, $html] = Mailer::compose(t('email.art_h_' . $action), $lines, t('email.art_btn_' . $action), $url, self::footer($uid));
            Mailer::later($u['email'], t('email.art_s_' . $action, ['title' => mb_substr((string) $a['title'], 0, 80)]), $text, $html, 'article');
        });
    }

    // ---- comments -----------------------------------------------------------------------------------

    /** Tell the article's author, and the author of the comment being answered, about a new comment. */
    public static function commentPosted(array $a, int $commenterId, ?int $parentId): void
    {
        if (!Mailer::configured() || Settings::get('notify_comments', '1') !== '1') return;
        $who = [];                                        // user id => 'reply' | 'comment'
        if ($parentId !== null) {
            $pu = (int) DB::val('SELECT user_id FROM article_comments WHERE id = ?', [$parentId]);
            if ($pu > 0) $who[$pu] = 'reply';
        }
        $au = (int) $a['author_id'];
        if (!isset($who[$au])) $who[$au] = 'comment';
        $name = (string) (DB::val('SELECT COALESCE(NULLIF(full_name, \'\'), full_name_bn) FROM user_profiles WHERE user_id = ?', [$commenterId]) ?? '');
        if ($name === '') $name = '?';
        $url = abs_url(Articles::url($a)) . '#comments';
        foreach ($who as $uid => $kind) {
            if ($uid === $commenterId) continue;
            $u = self::target($uid, true, true);
            if ($u === null) continue;
            I18n::using($u['lang'], function () use ($u, $a, $kind, $name, $url, $uid): void {
                [$text, $html] = Mailer::compose(
                    t('email.cm_h_' . $kind),
                    [t('email.cm_p_' . $kind, ['name' => $name, 'title' => (string) $a['title']])],
                    t('email.cm_btn'), $url, self::footer($uid)
                );
                Mailer::later($u['email'], t('email.cm_s_' . $kind, ['title' => mb_substr((string) $a['title'], 0, 80)]), $text, $html, 'comment');
            });
        }
    }
}
