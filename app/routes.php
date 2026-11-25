<?php
declare(strict_types=1);

use App\Controllers\{AccountController, AuthController, ModeratorController, TechController};
use App\Core\{DB, Fonts, I18n, View};

/** @var App\Core\Router $router */

$router->get('/', fn() => View::render('pages/home', ['title' => null]));

$router->get('/theme.css', function () {
    $css = Fonts::themeCss();
    $etag = '"' . md5($css) . '"';
    header('Content-Type: text/css; charset=utf-8');
    header('Cache-Control: public, max-age=' . (isset($_GET['v']) ? '31536000, immutable' : '300'));
    header('ETag: ' . $etag);
    if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) { http_response_code(304); return; }
    echo $css;
});

$router->get('/health', function () {
    header('Content-Type: text/plain');
    try { DB::val('SELECT 1'); echo 'ok'; } catch (Throwable $e) { http_response_code(503); echo 'db down'; }
});

$router->get('/lang/{code}', function (string $code) {
    I18n::set($code);
    $back = $_SERVER['HTTP_REFERER'] ?? '/';
    $host = parse_url($back, PHP_URL_HOST);
    redirect(($host && $host !== ($_SERVER['HTTP_HOST'] ?? '')) ? '/' : $back);
});

// ---- accounts (Phase 1B) ----
$router->get('/login', [AuthController::class, 'loginForm']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/forgot', [AuthController::class, 'forgotForm']);
$router->post('/forgot', [AuthController::class, 'forgot']);

$router->get('/dashboard', [AccountController::class, 'dashboard']);
$router->get('/account/password', [AccountController::class, 'passwordForm']);
$router->post('/account/password', [AccountController::class, 'passwordSave']);
$router->get('/profile', [AccountController::class, 'profileForm']);
$router->post('/profile', [AccountController::class, 'profileSave']);

// ---- moderator portal (specific paths first, {id} last) ----
$router->get('/moderator', [ModeratorController::class, 'index']);
$router->get('/moderator/users', [ModeratorController::class, 'users']);
$router->get('/moderator/users/new', [ModeratorController::class, 'userNewForm']);
$router->post('/moderator/users', [ModeratorController::class, 'userCreate']);
$router->get('/moderator/users/import', [ModeratorController::class, 'importForm']);
$router->post('/moderator/users/import', [ModeratorController::class, 'import']);
$router->get('/moderator/users/import/result', [ModeratorController::class, 'importResult']);
$router->get('/moderator/users/import/report.csv', [ModeratorController::class, 'importReport']);
$router->get('/moderator/users/import/template.csv', [ModeratorController::class, 'importTemplate']);
$router->get('/moderator/users/{id}', [ModeratorController::class, 'userShow']);
$router->post('/moderator/users/{id}', [ModeratorController::class, 'userUpdate']);
$router->post('/moderator/users/{id}/status', [ModeratorController::class, 'userStatus']);
$router->post('/moderator/users/{id}/password', [ModeratorController::class, 'userPassword']);
$router->get('/moderator/resets', [ModeratorController::class, 'resets']);
$router->post('/moderator/resets/{id}/approve', [ModeratorController::class, 'resetApprove']);
$router->post('/moderator/resets/{id}/reject', [ModeratorController::class, 'resetReject']);

// ---- tech portal ----
$router->get('/tech', [TechController::class, 'index']);
$router->get('/tech/audit', [TechController::class, 'audit']);
$router->get('/tech/settings', [TechController::class, 'settingsIndex']);
$router->post('/tech/settings/id-mode', [TechController::class, 'idModeSave']);   // before {group}
$router->get('/tech/settings/{group}', [TechController::class, 'settings']);
$router->post('/tech/settings/{group}', [TechController::class, 'settingsSave']);

// Pages planned for later phases show a friendly placeholder instead of a 404.
foreach (['explore', 'authors', 'departments', 'collections', 'about', 'contact', 'privacy', 'terms', 'advertise', 'community'] as $slug) {
    $router->get('/' . $slug, fn() => View::render('pages/soon', ['title' => t('nav.' . $slug)]));
}
