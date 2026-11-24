<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\{Auth, Env, I18n, Request, Router, Session, View};

Session::start();
I18n::init();

header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob: https:; style-src 'self'; style-src-attr 'unsafe-inline'; script-src 'self'; font-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");

$router = new Router();
require APP_PATH . '/routes.php';

try {
    // Someone who must choose a new password (first login, or after an admin reset) can only reach that page.
    $current = Auth::user();
    if ($current !== null && (int) $current['must_change_password'] === 1) {
        $p = Request::path();
        if (!in_array($p, ['/account/password', '/logout', '/health', '/theme.css'], true) && !str_starts_with($p, '/lang/')) {
            redirect('/account/password');
        }
    }
    $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
} catch (Throwable $e) {
    error_log((string) $e);
    http_response_code(500);
    View::render('pages/error', [
        'code' => 500,
        'title' => t('error.500_title'),
        'message' => Env::bool('APP_DEBUG') ? $e->getMessage() : t('error.500_msg'),
    ]);
}
