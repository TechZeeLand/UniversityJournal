<?php
use App\Core\I18n;
$loc = I18n::locale();
$site = setting('site_name', 'University Journal');
$title = ($pageTitle ?? null) ? $pageTitle . ' · ' . $site : $site . ' · ' . setting('site_tagline', '');
$nav = ['home' => '/', 'explore' => '/explore', 'authors' => '/authors', 'departments' => '/departments', 'collections' => '/collections', 'about' => '/about'];
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$otherLang = $loc === 'en' ? 'bn' : 'en';
?>
<!doctype html>
<html lang="<?= e($loc) ?>" dir="ltr" data-theme="<?= e(setting('default_theme', 'auto')) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?></title>
  <meta name="description" content="<?= e(setting('site_tagline', '')) ?>">
  <script src="<?= asset('js/theme-init.js') ?>"></script>
  <link rel="stylesheet" href="<?= e(icons_base()) ?>/zi.css">
  <link rel="stylesheet" href="<?= e(\App\Core\Fonts::cssUrl()) ?>">
  <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
  <link rel="stylesheet" href="/theme.css?v=<?= e(\App\Core\Fonts::version()) ?>">
  <script src="<?= asset('js/app.js') ?>" defer></script>
</head>
<body>
  <a class="skip" href="#main"><?= e(t('ui.skip')) ?></a>
  <header class="site-header">
    <div class="wrap bar">
      <a class="brand" href="/"><img src="<?= asset('logo.svg') ?>" alt="" width="32" height="32"><span><?= e($site) ?></span></a>
      <button class="icon-btn nav-toggle" type="button" aria-expanded="false" aria-controls="nav" aria-label="<?= e(t('ui.menu')) ?>"><?= icon('menu') ?></button>
      <nav id="nav" class="nav" aria-label="Main">
        <?php foreach ($nav as $key => $href): ?>
          <a href="<?= $href ?>"<?= $path === $href ? ' aria-current="page"' : '' ?>><?= e(t('nav.' . $key)) ?></a>
        <?php endforeach; ?>
      </nav>
      <div class="tools">
        <a class="icon-btn" href="/lang/<?= $otherLang ?>" hreflang="<?= $otherLang ?>" aria-label="<?= e(t('ui.language')) ?>: <?= e(I18n::LOCALES[$otherLang]) ?>"><?= icon('languages') ?><span class="lang-label"><?= e(I18n::LOCALES[$otherLang]) ?></span></a>
        <button class="icon-btn theme-toggle" type="button" aria-label="<?= e(t('ui.theme')) ?>"><?= icon('moon', 'only-light') ?><?= icon('sun', 'only-dark') ?></button>
        <a class="btn btn-primary btn-sm" href="/login"><?= e(t('nav.login')) ?></a>
      </div>
    </div>
  </header>

  <main id="main"><?= $content ?></main>

  <footer class="site-footer">
    <div class="wrap foot">
      <div>
        <strong><?= e($site) ?></strong>
        <p class="muted"><?= e(t('footer.note')) ?></p>
      </div>
      <nav aria-label="Footer">
        <a href="/about"><?= e(t('nav.about')) ?></a>
        <a href="/contact"><?= e(t('nav.contact')) ?></a>
        <a href="/advertise"><?= e(t('nav.advertise')) ?></a>
        <a href="/privacy"><?= e(t('nav.privacy')) ?></a>
        <a href="/terms"><?= e(t('nav.terms')) ?></a>
        <?php if ($src = setting('source_repo_url')): ?><a href="<?= e($src) ?>" rel="noopener"><?= e(t('footer.source')) ?></a><?php endif; ?>
      </nav>
    </div>
    <div class="wrap copy muted">© <?= date('Y') ?> <?= e(setting('university_name', '')) ?>. <?= e(t('footer.rights')) ?></div>
  </footer>
</body>
</html>
