<section class="section center">
  <div class="wrap narrow">
    <p class="eyebrow"><?= (int) $code ?></p>
    <h1><?= e($title) ?></h1>
    <p class="lead"><?= e($message) ?></p>
    <a class="btn btn-primary" href="/"><?= icon('arrow-left') ?> <?= e(t('ui.back_home')) ?></a>
  </div>
</section>
