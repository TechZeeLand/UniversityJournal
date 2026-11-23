<?php
$features = [
  ['newspaper', 'publish'], ['graduation-cap', 'profile'], ['shield-check', 'review'],
  ['languages', 'lang'], ['message-square', 'talk'], ['archive', 'archive'],
];
?>
<section class="hero">
  <div class="wrap hero-inner">
    <p class="eyebrow"><?= icon('sparkles') ?> <?= e(t('hero.eyebrow')) ?></p>
    <h1><?= e(t('hero.title')) ?></h1>
    <p class="lead"><?= e(t('hero.sub')) ?></p>
    <div class="actions">
      <a class="btn btn-primary" href="/explore"><?= icon('book-open') ?> <?= e(t('hero.cta_read')) ?></a>
      <a class="btn btn-ghost" href="/login"><?= icon('square-pen') ?> <?= e(t('hero.cta_signin')) ?></a>
    </div>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <h2 class="section-title"><?= e(t('feat.title')) ?></h2>
    <div class="grid">
      <?php foreach ($features as [$ic, $k]): ?>
        <article class="card">
          <span class="card-icon"><?= icon($ic) ?></span>
          <h3><?= e(t("feat.$k.t")) ?></h3>
          <p class="muted"><?= e(t("feat.$k.d")) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
