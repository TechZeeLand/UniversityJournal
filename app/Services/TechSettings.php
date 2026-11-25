<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{Audit, Settings};

/**
 * What the Tech portal can edit. One entry per settings tab; each field is validated by its type, and the
 * whole tab is saved or rejected together. New tabs (fonts, email, security, pages ...) are added here.
 * Labels and hints come from the language files: set.<key> and set.<key>.hint, tab titles from settings.tab.<group>.
 * The sign-in ID system (`id_mode`) has its own guarded form and is not listed here.
 */
final class TechSettings
{
    /** @return array<string, array{icon:string, fields:list<array<string,mixed>>}> */
    public static function groups(): array
    {
        return [
            'accounts' => ['icon' => 'users', 'fields' => [
                ['key' => 'password_min_length', 'type' => 'int', 'min' => 6, 'max' => 64, 'default' => '8', 'grp' => 'security'],
                ['key' => 'login_max_attempts', 'type' => 'int', 'min' => 3, 'max' => 20, 'default' => '5', 'grp' => 'security'],
                ['key' => 'login_lockout_minutes', 'type' => 'int', 'min' => 1, 'max' => 1440, 'default' => '15', 'grp' => 'security'],
                ['key' => 'session_idle_minutes', 'type' => 'int', 'min' => 5, 'max' => 1440, 'default' => '120', 'grp' => 'security'],
            ]],
            'uploads' => ['icon' => 'upload', 'fields' => [
                // The server itself accepts up to 25 MB per file (php.ini upload_max_filesize, nginx client_max_body_size 30m).
                ['key' => 'upload_max_image_mb', 'type' => 'int', 'min' => 1, 'max' => 25, 'default' => '5', 'grp' => 'uploads'],
                ['key' => 'upload_max_attach_mb', 'type' => 'int', 'min' => 1, 'max' => 25, 'default' => '20', 'grp' => 'uploads'],
            ]],
            'branding' => ['icon' => 'layout-dashboard', 'fields' => [
                ['key' => 'site_name', 'type' => 'text', 'max_len' => 80, 'required' => true, 'default' => 'University Journal', 'grp' => 'branding'],
                ['key' => 'site_tagline', 'type' => 'text', 'max_len' => 160, 'default' => '', 'grp' => 'branding'],
                ['key' => 'university_name', 'type' => 'text', 'max_len' => 120, 'required' => true, 'default' => 'Our University', 'grp' => 'branding'],
                ['key' => 'source_repo_url', 'type' => 'url', 'max_len' => 255, 'default' => '', 'grp' => 'general'],
            ]],
            'general' => ['icon' => 'info', 'fields' => [
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
            $v = isset($post[$k]) && is_string($post[$k]) ? trim($post[$k]) : '';
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
                default: // text
                    if (!empty($f['required']) && $v === '') $errors[$k] = t('set.err_required');
                    elseif (mb_strlen($v) > $f['max_len']) $errors[$k] = t('set.err_long', ['max' => $f['max_len']]);
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
                $changes[] = $k . ': ' . mb_substr($old, 0, 60) . ' -> ' . mb_substr($vals[$k], 0, 60);
            }
        }
        if ($changes) Audit::log('settings.updated', $group, implode('; ', $changes));
        return count($changes);
    }
}
