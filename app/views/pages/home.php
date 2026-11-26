<?php
$features = [
  ['publish', 'newspaper'], ['profile', 'graduation-cap'], ['review', 'shield-check'],
  ['lang', 'languages'], ['talk', 'message-square'], ['archive', 'archive'],
];
?>
<section class="hero">
  <div class="wrap hero-inner">
    <?php if (setting('home_show_eyebrow', '1') === '1'): ?><p class="eyebrow"><?= icon('sparkles') ?> <?= e(t('hero.eyebrow')) ?></p><?php endif; ?>
    <h1><?= e(t('hero.title')) ?></h1>
    <p class="lead"><?= e(t('hero.sub')) ?></p>
    <div class="actions">
      <a class="btn btn-primary" href="<?= e(safe_link((string) setting('home_cta1_url', '/explore'), '/explore')) ?>"><?= icon('book-open') ?> <?= e(t('hero.cta_read')) ?></a>
      <a class="btn btn-ghost" href="<?= e(safe_link((string) setting('home_cta2_url', '/login'), '/login')) ?>"><?= icon('square-pen') ?> <?= e(t('hero.cta_signin')) ?></a>
    </div>
  </div>
</section>

<?php if (setting('home_show_features', '1') === '1'): ?>
<section class="section">
  <div class="wrap">
    <h2 class="section-title"><?= e(t('feat.title')) ?></h2>
    <div class="grid">
      <?php foreach ($features as [$k, $default]): ?>
        <article class="card">
          <span class="card-icon"><?= icon((string) setting('home_icon_' . $k, $default)) ?></span>
          <h3><?= e(t("feat.$k.t")) ?></h3>
          <p class="muted"><?= e(t("feat.$k.d")) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
