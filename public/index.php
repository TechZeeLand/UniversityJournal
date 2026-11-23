<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\{Env, I18n, Router, Session, View, DB};

Session::start();
I18n::init();

header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self'; style-src-attr 'unsafe-inline'; script-src 'self'; font-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");

$router = new Router();

$router->get('/', fn() => View::render('pages/home', ['title' => null]));

$router->get('/theme.css', function () {
    $css = App\Core\Fonts::themeCss();
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

// Pages planned for later phases show a friendly placeholder instead of a 404.
foreach (['explore', 'authors', 'departments', 'collections', 'about', 'contact', 'login', 'privacy', 'terms', 'advertise', 'community'] as $slug) {
    $router->get('/' . $slug, fn() => View::render('pages/soon', ['title' => t('nav.' . $slug)]));
}

try {
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
