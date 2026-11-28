<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{Audit, Fonts, Settings, Theme};

/**
 * What the Tech portal can edit. One entry per settings tab; each field is validated by its type, and the
 * whole tab is saved or rejected together. New tabs are added here.
 * Labels and hints come from the language files: set.<key> and (optional) set.<key>.hint, tab titles from settings.tab.<group>.
 * A field may carry 'heading' => 'set.h.xxx' to print a small heading above it.
 * Field types: text, textarea, int, select, bool, url, link (site path or web address), email, color, font, weights, icon, checks, secret (write-only).
 * The sign-in ID system (`id_mode`) and the logo/icon uploads have their own guarded forms and are not listed here.
 */
final class TechSettings
{
    public const PROFILE_FIELDS_STUDENT = ['full_name', 'full_name_bn', 'department_id', 'academic_session', 'bio', 'interests', 'links', 'photo_path'];
    public const PROFILE_FIELDS_TEACHER = ['full_name', 'full_name_bn', 'department_id', 'designation', 'bio', 'interests', 'links', 'photo_path'];

    /** @return array<string, array{icon:string, fields:list<array<string,mixed>>}> */
    public static function groups(): array
    {
        $social = [];
        foreach (['facebook', 'x', 'youtube', 'instagram', 'linkedin', 'github', 'telegram'] as $n) {
            $social[] = ['key' => 'social_' . $n, 'type' => 'url', 'max_len' => 255, 'default' => '', 'grp' => 'contact'];
        }
        $social[0]['heading'] = 'set.h.social';

        $homeIcons = [];
        foreach (['publish' => 'newspaper', 'profile' => 'graduation-cap', 'review' => 'shield-check', 'lang' => 'languages', 'talk' => 'message-square', 'archive' => 'archive'] as $k => $ic) {
            $homeIcons[] = ['key' => 'home_icon_' . $k, 'type' => 'icon', 'default' => $ic, 'grp' => 'home'];
        }
        $homeIcons[0]['heading'] = 'set.h.home_icons';

        return [
            'branding' => ['icon' => 'palette', 'fields' => [
                ['key' => 'site_name', 'type' => 'text', 'max_len' => 80, 'required' => true, 'default' => 'University Journal', 'grp' => 'branding'],
                ['key' => 'site_name_bn', 'type' => 'text', 'max_len' => 80, 'default' => '', 'grp' => 'branding'],
                ['key' => 'site_tagline', 'type' => 'text', 'max_len' => 160, 'default' => '', 'grp' => 'branding'],
                ['key' => 'site_tagline_bn', 'type' => 'text', 'max_len' => 160, 'default' => '', 'grp' => 'branding'],
                ['key' => 'university_name', 'type' => 'text', 'max_len' => 120, 'required' => true, 'default' => 'Our University', 'grp' => 'branding'],
                ['key' => 'university_name_bn', 'type' => 'text', 'max_len' => 120, 'default' => '', 'grp' => 'branding'],
                ['key' => 'brand_show_name', 'type' => 'bool', 'default' => '1', 'grp' => 'branding', 'heading' => 'set.h.look'],
                ['key' => 'brand_primary', 'type' => 'color', 'default' => Theme::DEFAULT_PRIMARY, 'grp' => 'branding'],
                ['key' => 'brand_primary_dark', 'type' => 'color', 'default' => Theme::DEFAULT_PRIMARY_DARK, 'grp' => 'branding'],
                ['key' => 'source_repo_url', 'type' => 'url', 'max_len' => 255, 'default' => '', 'grp' => 'general', 'heading' => 'set.h.legal'],
            ]],
            'fonts' => ['icon' => 'type', 'fields' => [
                ['key' => 'font_body', 'type' => 'font', 'default' => 'Open Sans', 'grp' => 'fonts'],
                ['key' => 'font_heading', 'type' => 'font', 'default' => 'Young Serif', 'grp' => 'fonts'],
                ['key' => 'font_bn', 'type' => 'font', 'default' => 'Noto Sans Bengali', 'grp' => 'fonts'],
                ['key' => 'font_ar', 'type' => 'font', 'default' => 'Noto Naskh Arabic', 'grp' => 'fonts'],
                ['key' => 'font_weights_body', 'type' => 'weights', 'default' => '400;700', 'grp' => 'fonts'],
                ['key' => 'font_weights_heading', 'type' => 'weights', 'default' => '400', 'grp' => 'fonts'],
            ]],
            'home' => ['icon' => 'house', 'fields' => array_merge([
                ['key' => 'home_show_eyebrow', 'type' => 'bool', 'default' => '1', 'grp' => 'home'],
                ['key' => 'home_cta1_url', 'type' => 'link', 'max_len' => 255, 'required' => true, 'default' => '/explore', 'grp' => 'home'],
                ['key' => 'home_cta2_url', 'type' => 'link', 'max_len' => 255, 'required' => true, 'default' => '/login', 'grp' => 'home'],
                ['key' => 'home_show_features', 'type' => 'bool', 'default' => '1', 'grp' => 'home'],
                ['key' => 'home_show_latest', 'type' => 'bool', 'default' => '1', 'grp' => 'home', 'heading' => 'set.h.latest'],
                ['key' => 'home_latest_count', 'type' => 'int', 'min' => 4, 'max' => 40, 'default' => '20', 'grp' => 'home'],
                ['key' => 'explore_per_page', 'type' => 'int', 'min' => 6, 'max' => 48, 'default' => '12', 'grp' => 'home'],
            ], $homeIcons)],
            'contact' => ['icon' => 'mail', 'fields' => array_merge([
                ['key' => 'contact_email', 'type' => 'email', 'default' => '', 'grp' => 'contact'],
                ['key' => 'contact_phone', 'type' => 'text', 'max_len' => 40, 'default' => '', 'grp' => 'contact'],
                ['key' => 'contact_address', 'type' => 'textarea', 'max_len' => 300, 'default' => '', 'grp' => 'contact'],
            ], $social)],
            'urls' => ['icon' => 'link', 'fields' => [
                ['key' => 'student_url_mode', 'type' => 'select', 'options' => PublicUrl::STUDENT_MODES, 'default' => 'university_id', 'grp' => 'urls'],
                ['key' => 'teacher_url_mode', 'type' => 'select', 'options' => PublicUrl::TEACHER_MODES, 'default' => 'serial', 'grp' => 'urls'],
                ['key' => 'profile_public_student', 'type' => 'bool', 'default' => '1', 'grp' => 'urls'],
                ['key' => 'profile_public_teacher', 'type' => 'bool', 'default' => '1', 'grp' => 'urls'],
            ]],
            'profiles' => ['icon' => 'id-card', 'fields' => [
                ['key' => 'profile_required_student', 'type' => 'checks', 'options' => self::PROFILE_FIELDS_STUDENT, 'default' => '["full_name","department_id","academic_session","bio","photo_path"]', 'grp' => 'profiles'],
                ['key' => 'profile_required_teacher', 'type' => 'checks', 'options' => self::PROFILE_FIELDS_TEACHER, 'default' => '["full_name","department_id","designation","bio","photo_path"]', 'grp' => 'profiles'],
            ]],
            'readers' => ['icon' => 'user-plus', 'fields' => [
                ['key' => 'reader_signup', 'type' => 'bool', 'default' => '1', 'grp' => 'readers'],
                ['key' => 'reader_email_signup', 'type' => 'bool', 'default' => '1', 'grp' => 'readers'],
                ['key' => 'reader_google', 'type' => 'bool', 'default' => '0', 'grp' => 'readers', 'heading' => 'set.h.google'],
                ['key' => 'google_client_id', 'type' => 'text', 'max_len' => 200, 'default' => '', 'grp' => 'readers'],
                ['key' => 'google_client_secret', 'type' => 'secret', 'max_len' => 200, 'default' => '', 'grp' => 'readers'],
            ]],
            'engagement' => ['icon' => 'message-square', 'fields' => [
                ['key' => 'engage_reactions', 'type' => 'bool', 'default' => '1', 'grp' => 'engagement'],
                ['key' => 'engage_comments', 'type' => 'bool', 'default' => '1', 'grp' => 'engagement'],
                ['key' => 'engage_share', 'type' => 'bool', 'default' => '1', 'grp' => 'engagement'],
                ['key' => 'comment_max_len', 'type' => 'int', 'min' => 100, 'max' => 10000, 'default' => '1500', 'grp' => 'engagement'],
                ['key' => 'comment_cooldown_sec', 'type' => 'int', 'min' => 0, 'max' => 600, 'default' => '20', 'grp' => 'engagement'],
                ['key' => 'publish_needs_review', 'type' => 'bool', 'default' => '1', 'grp' => 'engagement', 'heading' => 'set.h.publishing'],
            ]],
            'email' => ['icon' => 'mail', 'fields' => [
                ['key' => 'smtp_enabled', 'type' => 'bool', 'default' => '0', 'grp' => 'email', 'heading' => 'set.h.smtp'],
                ['key' => 'smtp_host', 'type' => 'text', 'max_len' => 190, 'default' => '', 'grp' => 'email'],
                ['key' => 'smtp_port', 'type' => 'int', 'min' => 1, 'max' => 65535, 'default' => '587', 'grp' => 'email'],
                ['key' => 'smtp_security', 'type' => 'select', 'options' => ['starttls', 'ssl', 'none'], 'default' => 'starttls', 'grp' => 'email'],
                ['key' => 'smtp_user', 'type' => 'text', 'max_len' => 190, 'default' => '', 'grp' => 'email'],
                ['key' => 'smtp_password', 'type' => 'secret', 'max_len' => 200, 'default' => '', 'grp' => 'email'],
                ['key' => 'smtp_verify_cert', 'type' => 'bool', 'default' => '1', 'grp' => 'email'],
                ['key' => 'mail_from_email', 'type' => 'email', 'default' => '', 'grp' => 'email', 'heading' => 'set.h.mail_from'],
                ['key' => 'mail_from_name', 'type' => 'text', 'max_len' => 80, 'default' => '', 'grp' => 'email'],
                ['key' => 'mail_reply_to', 'type' => 'email', 'default' => '', 'grp' => 'email'],
                ['key' => 'reader_verify_email', 'type' => 'bool', 'default' => '1', 'grp' => 'email', 'heading' => 'set.h.mail_uses'],
                ['key' => 'reader_require_verified', 'type' => 'bool', 'default' => '0', 'grp' => 'email'],
                ['key' => 'email_reset_readers', 'type' => 'bool', 'default' => '1', 'grp' => 'email'],
                ['key' => 'email_reset_university', 'type' => 'bool', 'default' => '0', 'grp' => 'email'],
                ['key' => 'notify_author_status', 'type' => 'bool', 'default' => '1', 'grp' => 'email'],
                ['key' => 'notify_comments', 'type' => 'bool', 'default' => '1', 'grp' => 'email'],
            ]],
            'seo' => ['icon' => 'globe', 'fields' => [
                ['key' => 'seo_indexing', 'type' => 'bool', 'default' => '1', 'grp' => 'seo'],
                ['key' => 'sitemap_enabled', 'type' => 'bool', 'default' => '1', 'grp' => 'seo'],
                ['key' => 'feed_enabled', 'type' => 'bool', 'default' => '1', 'grp' => 'seo'],
                ['key' => 'feed_items', 'type' => 'int', 'min' => 5, 'max' => 100, 'default' => '30', 'grp' => 'seo'],
            ]],
            'accounts' => ['icon' => 'users', 'fields' => [
                ['key' => 'password_min_length', 'type' => 'int', 'min' => 6, 'max' => 64, 'default' => '8', 'grp' => 'security'],
                ['key' => 'login_max_attempts', 'type' => 'int', 'min' => 3, 'max' => 20, 'default' => '5', 'grp' => 'security'],
                ['key' => 'login_lockout_minutes', 'type' => 'int', 'min' => 1, 'max' => 1440, 'default' => '15', 'grp' => 'security'],
                ['key' => 'session_idle_minutes', 'type' => 'int', 'min' => 5, 'max' => 1440, 'default' => '120', 'grp' => 'security'],
            ]],
            'twofactor' => ['icon' => 'shield-check', 'fields' => [
                ['key' => 'twofa_enabled', 'type' => 'bool', 'default' => '1', 'grp' => 'twofa', 'heading' => 'set.h.twofa_main'],
                ['key' => 'twofa_allow_totp', 'type' => 'bool', 'default' => '1', 'grp' => 'twofa'],
                ['key' => 'twofa_allow_email', 'type' => 'bool', 'default' => '1', 'grp' => 'twofa'],
                ['key' => 'twofa_require_tech_admin', 'type' => 'bool', 'default' => '0', 'grp' => 'twofa', 'heading' => 'set.h.twofa_require'],
                ['key' => 'twofa_require_moderator', 'type' => 'bool', 'default' => '0', 'grp' => 'twofa'],
                ['key' => 'twofa_require_member', 'type' => 'bool', 'default' => '0', 'grp' => 'twofa'],
                ['key' => 'twofa_trust_days', 'type' => 'int', 'min' => 0, 'max' => 365, 'default' => '30', 'grp' => 'twofa', 'heading' => 'set.h.twofa_codes'],
                ['key' => 'twofa_code_minutes', 'type' => 'int', 'min' => 2, 'max' => 60, 'default' => '10', 'grp' => 'twofa'],
                ['key' => 'twofa_backup_count', 'type' => 'int', 'min' => 4, 'max' => 20, 'default' => '10', 'grp' => 'twofa'],
                ['key' => 'security_alert_email', 'type' => 'bool', 'default' => '1', 'grp' => 'twofa'],
            ]],
            'uploads' => ['icon' => 'upload', 'fields' => [
                // The server itself accepts up to 25 MB per file (php.ini upload_max_filesize, nginx client_max_body_size 30m).
                ['key' => 'upload_max_image_mb', 'type' => 'int', 'min' => 1, 'max' => 25, 'default' => '5', 'grp' => 'uploads'],
                ['key' => 'upload_max_attach_mb', 'type' => 'int', 'min' => 1, 'max' => 25, 'default' => '20', 'grp' => 'uploads'],
            ]],
            'backups' => ['icon' => 'hard-drive', 'fields' => [
                ['key' => 'backup_auto', 'type' => 'bool', 'default' => '1', 'grp' => 'backups'],
                ['key' => 'backup_every_hours', 'type' => 'int', 'min' => 1, 'max' => 168, 'default' => '24', 'grp' => 'backups'],
                ['key' => 'backup_keep', 'type' => 'int', 'min' => 1, 'max' => 365, 'default' => '14', 'grp' => 'backups'],
            ]],
            'general' => ['icon' => 'settings', 'fields' => [
                ['key' => 'default_lang', 'type' => 'select', 'options' => ['en', 'bn'], 'default' => 'en', 'grp' => 'general'],
                ['key' => 'default_theme', 'type' => 'select', 'options' => ['auto', 'light', 'dark'], 'default' => 'auto', 'grp' => 'general'],
            ]],
        ];
    }

    /** Current values of a tab, as strings, keyed by setting key. */
    public static function current(string $group): array
    {
        $out = [];
        foreach (self::groups()[$group]['fields'] ?? [] as $f) {
            $out[$f['key']] = (string) Settings::get($f['key'], (string) $f['default']);
        }
        return $out;
    }

    /**
     * @param array $post raw $_POST
     * @return array{0: array<string,string>, 1: array<string,string>} cleaned values, and error messages by key
     */
    public static function validate(string $group, array $post): array
    {
        $vals = [];
        $errors = [];
        foreach (self::groups()[$group]['fields'] ?? [] as $f) {
            $k = $f['key'];
            if ($f['type'] === 'bool') {
                $vals[$k] = (isset($post[$k]) && $post[$k] === '1') ? '1' : '0';
                continue;
            }
            if ($f['type'] === 'checks') {
                $picked = isset($post[$k]) && is_array($post[$k]) ? array_filter($post[$k], 'is_string') : [];
                $vals[$k] = json_encode(array_values(array_intersect($f['options'], $picked)));
                continue;
            }
            $v = isset($post[$k]) && is_string($post[$k]) ? trim($post[$k]) : '';
            if ($f['type'] === 'secret') {      // left empty = keep the stored value; never shown again
                $vals[$k] = $v !== '' ? $v : (string) Settings::get($k, '');
                if (mb_strlen($vals[$k]) > $f['max_len']) $errors[$k] = t('set.err_long', ['max' => $f['max_len']]);
                continue;
            }
            if ($f['type'] === 'textarea') $v = trim(str_replace("\r", '', (string) ($post[$k] ?? '')));
            $vals[$k] = $v;
            switch ($f['type']) {
                case 'int':
                    if (preg_match('/^[0-9]{1,6}$/', $v) !== 1 || (int) $v < $f['min'] || (int) $v > $f['max']) {
                        $errors[$k] = t('set.err_int', ['min' => $f['min'], 'max' => $f['max']]);
                    } else {
                        $vals[$k] = (string) (int) $v;
                    }
                    break;
                case 'select':
                    if (!in_array($v, $f['options'], true)) $errors[$k] = t('set.err_choice');
                    break;
                case 'url':
                    if ($v !== '' && (mb_strlen($v) > $f['max_len'] || filter_var($v, FILTER_VALIDATE_URL) === false
                            || !in_array(strtolower((string) parse_url($v, PHP_URL_SCHEME)), ['http', 'https'], true))) {
                        $errors[$k] = t('set.err_url');
                    }
                    break;
                case 'link':
                    if ($v === '' ? !empty($f['required']) : (mb_strlen($v) > $f['max_len'] || !Menus::validUrl($v))) {
                        $errors[$k] = t('set.err_link');
                    }
                    break;
                case 'email':
                    if ($v !== '' && (mb_strlen($v) > 190 || filter_var($v, FILTER_VALIDATE_EMAIL) === false)) $errors[$k] = t('set.err_email');
                    break;
                case 'color':
                    if (!Theme::validColor($v)) $errors[$k] = t('set.err_color'); else $vals[$k] = strtolower($v);
                    break;
                case 'font':
                    if ($v === '') $errors[$k] = t('set.err_required');
                    elseif (mb_strlen($v) > 60 || Fonts::clean($v) !== $v) $errors[$k] = t('set.err_font');
                    break;
                case 'weights':
                    $w = $v === '' ? [] : explode(';', $v);
                    $okW = $w && count($w) <= 6;
                    foreach ($w as $n) if (preg_match('/^[1-9]00$/', $n) !== 1) $okW = false;
                    if (!$okW) $errors[$k] = t('set.err_weights');
                    break;
                case 'icon':
                    if (preg_match('/^[a-z0-9][a-z0-9-]{0,39}$/', $v) !== 1) $errors[$k] = t('set.err_icon');
                    break;
                default: // text, textarea
                    if (!empty($f['required']) && $v === '') $errors[$k] = t('set.err_required');
                    elseif (mb_strlen($v) > $f['max_len']) $errors[$k] = t('set.err_long', ['max' => $f['max_len']]);
            }
        }
        if ($group === 'email') {
            $host = (string) ($vals['smtp_host'] ?? '');
            if ($host !== '' && preg_match('/^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?$/', $host) !== 1) $errors['smtp_host'] = t('set.err_host');
            if (($vals['smtp_enabled'] ?? '0') === '1') {
                if ($host === '' && !isset($errors['smtp_host'])) $errors['smtp_host'] = t('set.err_required');
                if (($vals['mail_from_email'] ?? '') === '' && !isset($errors['mail_from_email'])) $errors['mail_from_email'] = t('set.err_required');
            }
        }
        return [$vals, $errors];
    }

    /** Store changed values and write one audit line. @return int number of settings that changed */
    public static function save(string $group, array $vals): int
    {
        $changes = [];
        foreach (self::groups()[$group]['fields'] ?? [] as $f) {
            $k = $f['key'];
            $old = (string) Settings::get($k, (string) $f['default']);
            if ($vals[$k] !== $old) {
                Settings::set($k, $vals[$k], $f['grp']);
                $changes[] = $f['type'] === 'secret' ? $k . ': (changed)' : $k . ': ' . mb_substr($old, 0, 60) . ' -> ' . mb_substr($vals[$k], 0, 60);
            }
        }
        if ($changes) Audit::log('settings.updated', $group, implode('; ', $changes));
        return count($changes);
    }
}
