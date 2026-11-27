<?php
use App\Core\{Auth, Csrf, Flash, I18n, Theme};
use App\Services\{Branding, Menus};
$cu = Auth::user();
$loc = I18n::locale();
$site = site_name();
$tagline = site_tagline();
$title = ($pageTitle ?? null) ? $pageTitle . ' · ' . $site : ($tagline !== '' ? $site . ' · ' . $tagline : $site);
$icon = Branding::url('icon');
$iconType = $icon !== null ? Branding::mime('icon') : 'image/svg+xml';
$og = Branding::url('og');
$desc = trim((string) ($meta['description'] ?? '')) !== '' ? (string) $meta['description'] : $tagline;
$ogImage = !empty($meta['image']) ? (string) $meta['image'] : $og;
$ogType = (string) ($meta['type'] ?? 'website');
$origin = rtrim((string) \App\Core\Env::get('APP_URL', ''), '/');
$social = ['facebook' => 'Facebook', 'x' => 'X', 'youtube' => 'YouTube', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'github' => 'GitHub', 'telegram' => 'Telegram'];
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$otherLang = $loc === 'en' ? 'bn' : 'en';
?>
<!doctype html>
<html lang="<?= e($loc) ?>" dir="ltr" data-theme="<?= e(setting('default_theme', 'auto')) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?></title>
  <meta name="description" content="<?= e($desc) ?>">
  <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
  <meta name="theme-color" content="<?= e(Theme::primary()) ?>">
  <meta property="og:type" content="<?= e($ogType) ?>">
  <meta property="og:site_name" content="<?= e($site) ?>">
  <meta property="og:title" content="<?= e($title) ?>">
  <meta property="og:description" content="<?= e($desc) ?>">
  <meta property="og:url" content="<?= e(abs_url($path)) ?>">
<?php if ($ogImage !== null): ?>  <meta property="og:image" content="<?= e(abs_url($ogImage)) ?>">
  <meta name="twitter:card" content="summary_large_image">
<?php endif; ?>
<?php if (setting('feed_enabled', '1') === '1' && setting('seo_indexing', '1') === '1'): ?>  <link rel="alternate" type="application/rss+xml" title="<?= e($site) ?>" href="/feed.xml">
<?php endif; ?>
<?php if (setting('seo_indexing', '1') !== '1'): ?>  <meta name="robots" content="noindex,nofollow">
<?php endif; ?>
  <link rel="icon" href="<?= e($icon ?? asset('logo.svg')) ?>" type="<?= e($iconType) ?>">
<?php if ($icon !== null && $iconType === 'image/png'): ?>  <link rel="apple-touch-icon" href="<?= e($icon) ?>">
<?php endif; ?>
  <script src="<?= asset('js/theme-init.js') ?>"></script>
  <link rel="stylesheet" href="<?= e(icons_base()) ?>/zi.css">
  <link rel="stylesheet" href="<?= e(\App\Core\Fonts::cssUrl()) ?>">
  <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
  <link rel="stylesheet" href="/theme.css?v=<?= e(Theme::version()) ?>">
  <script src="<?= asset('js/app.js') ?>" defer></script>
</head>
<body>
  <a class="skip" href="#main"><?= e(t('ui.skip')) ?></a>
  <header class="site-header">
    <div class="wrap bar">
      <a class="brand" href="/"><?= brand_logo_html() ?><span<?= setting('brand_show_name', '1') === '1' ? '' : ' class="sr-only"' ?>><?= e($site) ?></span></a>
      <button class="icon-btn nav-toggle" type="button" aria-expanded="false" aria-controls="nav" aria-label="<?= e(t('ui.menu')) ?>"><?= icon('menu') ?></button>
      <nav id="nav" class="nav" aria-label="Main">
        <?php foreach (Menus::items('header') as $m): $ext = Menus::external($m['url']); ?>
          <a href="<?= e($m['url']) ?>"<?= $path === $m['url'] ? ' aria-current="page"' : '' ?><?= $ext ? ' rel="noopener"' : '' ?>><?= e($m['label']) ?></a>
        <?php endforeach; ?>
      </nav>
      <div class="tools">
        <a class="icon-btn" href="/lang/<?= $otherLang ?>" hreflang="<?= $otherLang ?>" aria-label="<?= e(t('ui.language')) ?>: <?= e(I18n::LOCALES[$otherLang]) ?>"><?= icon('languages') ?><span class="lang-label"><?= e(I18n::LOCALES[$otherLang]) ?></span></a>
        <button class="icon-btn theme-toggle" type="button" aria-label="<?= e(t('ui.theme')) ?>"><?= icon('moon', 'only-light') ?><?= icon('sun', 'only-dark') ?></button>
        <?php if ($cu): ?>
          <a class="icon-btn" href="<?= $cu['role'] === 'reader' ? '/account' : '/profile' ?>" aria-label="<?= e(t('nav.profile')) ?>"><?= avatar($cu) ?></a>
          <a class="btn btn-ghost btn-sm" href="<?= e(Auth::homeFor($cu['role'])) ?>"><?= icon('layout-dashboard') ?><span class="hide-sm"><?= e(t('home.' . $cu['role'])) ?></span></a>
          <form method="post" action="/logout" class="inline"><?= Csrf::field() ?><button class="icon-btn" type="submit" aria-label="<?= e(t('nav.logout')) ?>" title="<?= e(t('nav.logout')) ?>"><?= icon('log-out') ?></button></form>
        <?php else: ?>
          <a class="btn btn-primary btn-sm" href="/login"><?= e(t('nav.login')) ?></a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <main id="main">
    <?php $flashes = Flash::pull(); if ($flashes): ?>
      <div class="wrap flashes">
        <?php foreach ($flashes as $f): ?>
          <div class="alert alert-<?= e($f['t']) ?>" role="status"><?= icon($f['t'] === 'ok' ? 'circle-check' : ($f['t'] === 'error' ? 'circle-alert' : 'info')) ?><span><?= e($f['m']) ?></span></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?= $content ?>
  </main>

  <footer class="site-footer">
    <div class="wrap foot">
      <div>
        <strong><?= e($site) ?></strong>
        <p class="muted"><?= e(t('footer.note')) ?></p>
        <?php $soc = array_filter($social, fn($k) => preg_match('#^https?://#i', (string) setting('social_' . $k, '')) === 1, ARRAY_FILTER_USE_KEY); if ($soc): ?>
          <p class="social"><?php foreach ($soc as $k => $label): ?><a href="<?= e(setting('social_' . $k)) ?>" rel="noopener me" target="_blank"><?= e($label) ?></a><?php endforeach; ?></p>
        <?php endif; ?>
      </div>
      <nav aria-label="Footer">
        <?php foreach (Menus::items('footer') as $m): ?>
          <a href="<?= e($m['url']) ?>"<?= Menus::external($m['url']) ? ' rel="noopener"' : '' ?>><?= e($m['label']) ?></a>
        <?php endforeach; ?>
        <?php if ($src = setting('source_repo_url')): ?><a href="<?= e($src) ?>" rel="noopener"><?= e(t('footer.source')) ?></a><?php endif; ?>
      </nav>
    </div>
    <div class="wrap copy muted">© <?= date('Y') ?> <?= e(university_name()) ?>. <?= e(t('footer.rights')) ?></div>
  </footer>
</body>
</html>