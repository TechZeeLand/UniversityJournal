<?php /** @var array $page; @var string $html */ ?>
<section class="section">
  <div class="wrap narrow-md">
    <h1><?= e(\App\Services\Pages::title($page)) ?></h1>
    <article class="prose"><?= $html ?></article>
    <?php $cu = \App\Core\Auth::user(); if ($cu && $cu['role'] === 'tech_admin'): ?>
      <p class="small"><a class="btn btn-ghost btn-sm" href="/tech/pages/<?= (int) $page['id'] ?>"><?= icon('pencil') ?> <?= e(t('pages.edit_this')) ?></a></p>
    <?php endif; ?>
  </div>
</section>
