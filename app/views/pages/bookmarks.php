<?php /** @var array $rows @var int $total @var int $per @var int $page */ ?>
<section class="section">
  <div class="wrap">
    <h1 class="h-md"><?= icon('bookmark') ?> <?= e(t('bm.title')) ?><?php if ($total > 0): ?> <span class="muted small">(<?= e(num($total)) ?>)</span><?php endif; ?></h1>
    <p class="lead"><?= e(t('bm.sub')) ?></p>
    <?php if (!$rows): ?>
      <div class="card pad center"><p class="muted"><?= e(t('bm.empty')) ?></p><p><a class="btn btn-primary btn-sm" href="/explore"><?= icon('search') ?> <?= e(t('nav.explore')) ?></a></p></div>
    <?php else: ?>
      <div class="story-grid">
        <?php foreach ($rows as $a) \App\Core\View::partial('partials/article_card', ['a' => $a, 'v' => 'grid']); ?>
      </div>
      <?php \App\Core\View::partial('partials/pager', ['total' => $total, 'per' => $per, 'page' => $page]); ?>
    <?php endif; ?>
  </div>
</section>
