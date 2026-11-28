<?php
declare(strict_types=1);

use App\Core\{Env, I18n, Settings};
use App\Services\Branding;

function e(mixed $s): string { return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function t(string $key, array $repl = []): string { return I18n::t($key, $repl); }

function setting(string $key, ?string $default = null): ?string { return Settings::get($key, $default); }

function url(string $path = '/'): string { return '/' . ltrim($path, '/'); }

function asset(string $path): string
{
    $p = ltrim($path, '/');
    $file = dirname(__DIR__) . '/public/assets/' . $p;
    // the file's change time in the address, so browsers fetch a new CSS/JS file after an update instead of using a week-old copy
    return '/assets/' . $p . (is_file($file) ? '?v=' . filemtime($file) : '');
}

/** Base path of the cached icon mirror (served by our own nginx). */
function icons_base(): string { return rtrim(Env::get('ICONS_BASE', '/icons-cdn'), '/'); }

/** Icon from the TechZeeLand icon set (CSS mask class: zi zi-NAME). */
function icon(string $name, string $extra = ''): string
{
    $name = preg_replace('/[^a-z0-9-]/', '', strtolower($name));
    $cls = trim($extra);
    return '<i class="zi zi-' . $name . ($cls ? " $cls" : '') . '" aria-hidden="true"></i>';
}

function redirect(string $to, int $code = 302): never
{
    header('Location: ' . $to, true, $code);
    exit;
}

/** A trimmed POST value as a string (arrays and missing keys give $default). */
function post(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

/** Empty string to NULL, for optional database columns. */
function nn(?string $s): ?string { return ($s === null || $s === '') ? null : $s; }

/** Stop with an error page (403, 404, ...). */
function abort(int $code): never
{
    $k = in_array($code, [403, 404], true) ? $code : 500;
    http_response_code($code);
    \App\Core\View::render('pages/error', ['code' => $code, 'title' => t("error.{$k}_title"), 'message' => t("error.{$k}_msg")]);
    exit;
}

function fmt_dt(?string $s): string
{
    $ts = $s ? strtotime($s) : false;
    return $ts ? date('Y-m-d H:i', $ts) : '—';
}

function fmt_bytes(float|int $n): string
{
    $u = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($n >= 1024 && $i < count($u) - 1) { $n /= 1024; $i++; }
    return ($i === 0 ? (string) (int) $n : number_format($n, 1)) . ' ' . $u[$i];
}

/** Name to show for a user row: Bangla name on the Bangla interface when set, else English name, else the ID. */
function display_name(array $row): string
{
    $bn = trim((string) ($row['full_name_bn'] ?? ''));
    $en = trim((string) ($row['full_name'] ?? ''));
    if (I18n::locale() === 'bn' && $bn !== '') return $bn;
    if ($en !== '') return $en;
    return $bn !== '' ? $bn : (string) ($row['uid'] ?? '');
}

function initial(string $s): string
{
    $s = trim($s);
    return $s === '' ? '?' : mb_strtoupper(mb_substr($s, 0, 1));
}

/** Round avatar: the uploaded photo, or the person's initial. */
function avatar(array $row, string $cls = ''): string
{
    $p = (string) ($row['photo_path'] ?? '');
    $c = trim('avatar ' . $cls);
    if ($p !== '' && preg_match('#^avatars/[A-Za-z0-9._-]+$#', $p) === 1) {
        return '<span class="' . e($c) . '"><img src="/uploads/' . e($p) . '" alt="" loading="lazy"></span>';
    }
    return '<span class="' . e($c) . '" aria-hidden="true">' . e(initial(display_name($row))) . '</span>';
}

function role_label(string $role): string { return t('role.' . $role); }

/** A setting that has a Bangla twin (key + "_bn"): the Bangla value on the Bangla interface when filled in, else the main value. */
function localized_setting(string $key, string $default = ''): string
{
    if (I18n::locale() === 'bn') {
        $bn = trim((string) Settings::get($key . '_bn', ''));
        if ($bn !== '') return $bn;
    }
    return trim((string) Settings::get($key, $default));
}

function site_name(): string { return localized_setting('site_name', 'University Journal'); }
function site_tagline(): string { return localized_setting('site_tagline'); }
function university_name(): string { return localized_setting('university_name'); }

/** Web path of an uploaded brand picture (logo, logo_dark, icon, og), or null. */
function brand_url(string $slot): ?string { return Branding::url($slot); }

/** The header logo: uploaded picture(s) or the built-in mark. A separate dark-theme logo is swapped in automatically. */
function brand_logo_html(): string
{
    $light = Branding::url('logo');
    $dark = Branding::url('logo_dark');
    if ($light === null && $dark === null) $light = '/brand-logo.svg?v=' . \App\Core\Theme::version();
    $one = static fn(string $src, string $cls): string => '<img class="brand-logo' . ($cls !== '' ? ' ' . $cls : '') . '" src="' . e($src) . '" alt="" height="32">';
    if ($light !== null && $dark !== null) return $one($light, 'only-light') . $one($dark, 'only-dark');
    return $one((string) ($light ?? $dark), '');
}

/** A link target is safe to print: a path on this site, a web address or a mail link. */
function safe_link(string $u, string $fallback = '/'): string
{
    return \App\Services\Menus::validUrl($u) ? $u : $fallback;
}

/** Full web address for a site path: APP_URL when set, else built from the request (for share links and meta tags). */
function abs_url(string $path = '/'): string
{
    $base = rtrim((string) Env::get('APP_URL', ''), '/');
    if ($base === '') {
        $https = ($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        $host = preg_replace('/[^A-Za-z0-9.:-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $base = ($https ? 'https' : 'http') . '://' . $host;
    }
    return $base . '/' . ltrim($path, '/');
}

/** Relative time in the interface language: "5 minutes ago", else the date. */
function time_ago(?string $s): string
{
    $ts = $s ? strtotime($s) : false;
    if (!$ts) return '';
    $d = time() - $ts;
    if ($d < 60) return t('time.now');
    if ($d < 3600) return t('time.min', ['n' => (int) floor($d / 60)]);
    if ($d < 86400) return t('time.hour', ['n' => (int) floor($d / 3600)]);
    if ($d < 86400 * 7) return t('time.day', ['n' => (int) floor($d / 86400)]);
    return fmt_date($s);
}

/** A readable date like "26 Nov 2026" (month names from the language files). */
function fmt_date(?string $s): string
{
    $ts = $s ? strtotime($s) : false;
    if (!$ts) return '';
    return date('j', $ts) . ' ' . t('month.' . date('n', $ts)) . ' ' . date('Y', $ts);
}

/** Digits in the interface language (Bangla digits on the Bangla interface). */
function num(int|float|string $n): string
{
    $s = number_format((float) $n, 0, '.', '');
    return I18n::locale() === 'bn' ? strtr($s, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']) : $s;
}

/** Same as num(), but 1200 becomes 1.2K. */
function num_short(int $n): string
{
    $fmt = static function (float $v): string {
        $s = rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.');
        return I18n::locale() === 'bn' ? strtr($s, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']) : $s;
    };
    if ($n >= 1000000) return $fmt($n / 1000000) . 'M';
    if ($n >= 1000) return $fmt($n / 1000) . 'K';
    return num($n);
}

/** lang / dir attributes for article text: Arabic reads right to left. */
function art_attrs(array $a): string
{
    $l = (string) ($a['lang'] ?? 'en');
    return $l === 'ar' ? ' lang="ar" dir="rtl"' : ($l === 'bn' ? ' lang="bn"' : ' lang="en"');
}

/** The article's cover picture, or a calm generated cover (colour from the article id) when it has none. */
function cover_html(array $a, string $cls = ''): string
{
    $c = (string) ($a['cover_path'] ?? '');
    $cls = trim('cover ' . $cls);
    if ($c !== '' && preg_match('#^articles/[A-Za-z0-9._-]+$#', $c) === 1) {
        return '<span class="' . e($cls) . '"><img src="/uploads/' . e($c) . '" alt="" loading="lazy"></span>';
    }
    $h = (((int) ($a['id'] ?? 0) * 47) % 71) - 35;       // a hue step around the brand colour (--brand-h), so covers follow the theme
    return '<span class="' . e($cls) . ' cover-ph" style="--h:' . $h . '" aria-hidden="true">' . icon('quote') . '</span>';
}

/** "jane.doe@example.com" -> "j*******@example.com" (for pages that must not show the whole address). */
function mask_email(string $email): string
{
    $at = strrpos($email, '@');
    if ($at === false || $at < 1) return '';
    return mb_substr($email, 0, 1) . str_repeat('*', max(2, min(8, $at - 1))) . substr($email, $at);
}
