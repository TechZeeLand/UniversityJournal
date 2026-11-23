<?php
declare(strict_types=1);

use App\Core\{Env, I18n, Settings};

function e(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

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
