<?php
declare(strict_types=1);

use App\Controllers\{AccountController, ArticleController, AuthController, EmailController, SecurityController, SeoController, ModeratorController, ModerationController, PublicController, ReaderController, SiteAdminController, TechController, WriterController};
use App\Core\{DB, I18n, Theme, View};

/** @var App\Core\Router $router */

$router->get('/', [ArticleController::class, 'home']);

$router->get('/theme.css', function () {
    $css = Theme::css();
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

$router->get('/login/2fa', [AuthController::class, 'twofaForm']);
$router->post('/login/2fa', [AuthController::class, 'twofaVerify']);
$router->post('/login/2fa/send', [AuthController::class, 'twofaSend']);
$router->post('/login/2fa/cancel', [AuthController::class, 'twofaCancel']);

// ---- security page: second step, backup codes, trusted browsers, signed-in devices ----
$router->get('/security', [SecurityController::class, 'index']);
$router->post('/security/totp/start', [SecurityController::class, 'totpStart']);
$router->post('/security/totp/cancel', [SecurityController::class, 'totpCancel']);
$router->post('/security/totp/confirm', [SecurityController::class, 'totpConfirm']);
$router->post('/security/totp/disable', [SecurityController::class, 'totpDisable']);
$router->post('/security/email/start', [SecurityController::class, 'emailStart']);
$router->post('/security/email/cancel', [SecurityController::class, 'emailCancel']);
$router->post('/security/email/confirm', [SecurityController::class, 'emailConfirm']);
$router->post('/security/email/disable', [SecurityController::class, 'emailDisable']);
$router->post('/security/backup', [SecurityController::class, 'backupNew']);
$router->post('/security/trusted/all', [SecurityController::class, 'trustedRevokeAll']);
$router->post('/security/trusted/{id}/revoke', [SecurityController::class, 'trustedRevoke']);
$router->post('/security/sessions/others', [SecurityController::class, 'sessionsOthers']);
$router->post('/security/sessions/{id}/revoke', [SecurityController::class, 'sessionRevoke']);

$router->get('/dashboard', [AccountController::class, 'dashboard']);
$router->get('/account/password', [AccountController::class, 'passwordForm']);
$router->post('/account/password', [AccountController::class, 'passwordSave']);
$router->get('/profile', [AccountController::class, 'profileForm']);
$router->post('/profile', [AccountController::class, 'profileSave']);

// ---- email links: verify an address, reset a password by email ----
$router->get('/verify/{token}', [EmailController::class, 'verify']);
$router->post('/account/verify-email', [EmailController::class, 'resend']);
$router->get('/reset/{token}', [EmailController::class, 'resetForm']);
$router->post('/reset/{token}', [EmailController::class, 'resetSave']);

// ---- robots.txt, sitemap, RSS feed ----
$router->get('/robots.txt', [SeoController::class, 'robots']);
$router->get('/sitemap.xml', [SeoController::class, 'sitemap']);
$router->get('/feed.xml', [SeoController::class, 'feed']);

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
$router->post('/moderator/users/{id}/2fa-reset', [ModeratorController::class, 'user2faReset']);
$router->get('/moderator/resets', [ModeratorController::class, 'resets']);
$router->post('/moderator/resets/{id}/approve', [ModeratorController::class, 'resetApprove']);
$router->post('/moderator/resets/{id}/reject', [ModeratorController::class, 'resetReject']);

// ---- tech portal ----
$router->get('/tech', [TechController::class, 'index']);
$router->get('/tech/audit', [TechController::class, 'audit']);
$router->get('/tech/settings', [TechController::class, 'settingsIndex']);
$router->post('/tech/settings/id-mode', [TechController::class, 'idModeSave']);   // before {group}
$router->post('/tech/email/test', [TechController::class, 'emailTest']);
$router->get('/tech/settings/{group}', [TechController::class, 'settings']);
$router->post('/tech/settings/{group}', [TechController::class, 'settingsSave']);
$router->post('/tech/branding/{slot}', [TechController::class, 'brandingSave']);

// site content: pages, menus, texts, backups (Tech portal)
$router->get('/tech/pages', [SiteAdminController::class, 'pagesIndex']);
$router->get('/tech/pages/new', [SiteAdminController::class, 'pageNew']);
$router->post('/tech/pages', [SiteAdminController::class, 'pageCreate']);
$router->get('/tech/pages/{id}', [SiteAdminController::class, 'pageEdit']);
$router->post('/tech/pages/{id}', [SiteAdminController::class, 'pageUpdate']);
$router->post('/tech/pages/{id}/delete', [SiteAdminController::class, 'pageDelete']);
$router->get('/tech/menus', [SiteAdminController::class, 'menus']);
$router->post('/tech/menus', [SiteAdminController::class, 'menusSave']);
$router->get('/tech/texts', [SiteAdminController::class, 'textsIndex']);
$router->get('/tech/texts/{group}', [SiteAdminController::class, 'texts']);
$router->post('/tech/texts/{group}', [SiteAdminController::class, 'textsSave']);
$router->get('/tech/backups', [SiteAdminController::class, 'backups']);
$router->post('/tech/backups', [SiteAdminController::class, 'backupCreate']);
$router->get('/tech/backups/{name}', [SiteAdminController::class, 'backupDownload']);
$router->post('/tech/backups/{name}/delete', [SiteAdminController::class, 'backupDelete']);

// ---- readers (people outside the university) ----
$router->get('/register', [ReaderController::class, 'registerForm']);
$router->post('/register', [ReaderController::class, 'register']);
$router->get('/auth/google', [ReaderController::class, 'googleStart']);
$router->get('/auth/google/callback', [ReaderController::class, 'googleCallback']);
$router->get('/account', [ReaderController::class, 'account']);
$router->post('/account', [ReaderController::class, 'accountSave']);

// ---- writing (students and teachers; specific paths before {id}) ----
$router->get('/write', [WriterController::class, 'index']);
$router->get('/write/new', [WriterController::class, 'newForm']);
$router->post('/write/preview', [WriterController::class, 'preview']);
$router->post('/write/image', [WriterController::class, 'image']);
$router->post('/write', fn() => WriterController::save());
$router->get('/write/{id}', [WriterController::class, 'editForm']);
$router->post('/write/{id}', fn(string $id) => WriterController::save($id));
$router->post('/write/{id}/withdraw', [WriterController::class, 'withdraw']);
$router->post('/write/{id}/delete', [WriterController::class, 'delete']);

// ---- reading, explore, reacting ----
$router->get('/explore', [ArticleController::class, 'explore']);
$router->get('/article/{id}', fn(string $id) => ArticleController::show($id));
$router->get('/article/{id}/{slug}', fn(string $id, string $slug) => ArticleController::show($id, $slug));
$router->post('/article/{id}/react', [ArticleController::class, 'react']);
$router->post('/article/{id}/comment', [ArticleController::class, 'commentAdd']);
$router->post('/article/{id}/share', [ArticleController::class, 'shared']);
$router->post('/comment/{id}/delete', fn(string $id) => ArticleController::commentAction($id, 'delete'));
$router->post('/comment/{id}/hide', fn(string $id) => ArticleController::commentAction($id, 'hide'));
$router->post('/comment/{id}/show', fn(string $id) => ArticleController::commentAction($id, 'show'));

// ---- moderation: review queue, comments, readers ----
$router->get('/moderator/articles', [ModerationController::class, 'articles']);
$router->get('/moderator/articles/{id}', [ModerationController::class, 'articleShow']);
$router->post('/moderator/articles/{id}/review', [ModerationController::class, 'articleReview']);
$router->post('/moderator/articles/{id}/feature', [ModerationController::class, 'articleFeature']);
$router->get('/moderator/comments', [ModerationController::class, 'comments']);
$router->get('/moderator/readers', [ModerationController::class, 'readers']);

// ---- public site ----
$router->get('/authors', [PublicController::class, 'authors']);
$router->get('/students/{key}', fn(string $key) => PublicController::profile('students', $key));
$router->get('/teachers/{key}', fn(string $key) => PublicController::profile('teachers', $key));

// Pages planned for later phases show a friendly placeholder instead of a 404.
foreach (['departments', 'collections', 'advertise', 'community'] as $slug) {
    $router->get('/' . $slug, fn() => View::render('pages/soon', ['title' => t('nav.' . $slug)]));
}

// Editable pages (about, contact, privacy, terms and your own). Must stay LAST: it matches any single-segment address.
$router->get('/{slug}', [PublicController::class, 'page']);
