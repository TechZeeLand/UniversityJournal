<?php
declare(strict_types=1);

use App\Core\{Env, I18n, Settings};

function e(mixed $s): string { return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function t(string $key, array $repl = []): string { return I18n::t($key, $repl); }

function setting(string $key, ?string $default = null): ?string { return Settings::get($key, $default); }

function url(string $path = '/'): string { return '/' . ltrim($path, '/'); }

function asset(string $path): string { return '/assets/' . ltrim($path, '/'); }

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
