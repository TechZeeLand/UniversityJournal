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
    // ---- two-step sign-in ----------------------------------------------------------------------------

    /** The 6-digit sign-in / set-up code. Needs a confirmed address. @return bool false when nothing could be sent */
    public static function twofaCode(int $userId, string $code, int $minutes): bool
    {
        if (!Mailer::configured()) return false;
        $u = self::target($userId, true, false);
        if ($u === null) return false;
        I18n::using($u['lang'], static function () use ($u, $code, $minutes): void {
            [$text, $html] = Mailer::compose(
                t('email.tf_h'),
                [t('email.tf_p1', ['site' => site_name()]), t('email.tf_code', ['code' => $code]), t('email.tf_p2', ['min' => $minutes])],
                null, null,
                t('email.tf_footer')
            );
            Mailer::later($u['email'], t('email.tf_s', ['code' => $code, 'site' => site_name()]), $text, $html, 'twofa');
        });
        return true;
    }

    /**
     * A security change on the account (second step switched on or off, new backup codes, reset by an admin).
     * @param string $event totp_on | totp_off | email_on | email_off | backup_new | reset | devices_out
     */
    public static function security(int $userId, string $event): void
    {
        if (!Mailer::configured() || Settings::get('security_alert_email', '1') !== '1') return;
        if (!in_array($event, ['totp_on', 'totp_off', 'email_on', 'email_off', 'backup_new', 'reset', 'devices_out'], true)) return;
        $u = self::target($userId, true, false);
        if ($u === null) return;
        $where = [\App\Core\Request::ip(), \App\Core\Sessions::label(), date('Y-m-d H:i')];
        I18n::using($u['lang'], static function () use ($u, $event, $where): void {
            $lines = [t('email.sec_' . $event)];
            if ($event !== 'reset') $lines[] = t('email.sec_where', ['ip' => $where[0], 'device' => $where[1], 'time' => $where[2]]);
            $lines[] = t('email.sec_not_you');
            [$text, $html] = Mailer::compose(t('email.sec_h'), $lines, t('email.sec_btn'), abs_url('/security'), '');
            Mailer::later($u['email'], t('email.sec_s', ['site' => site_name()]), $text, $html, 'security');
        });
    }
}
