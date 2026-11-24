<section class="section">
  <div class="wrap">
    <h1 class="h-md"><?= e(t('portal.mod_title')) ?></h1>
    <?php \App\Core\View::partial('portal/_nav', ['active' => 'overview', 'actor' => $actor]); ?>
    <div class="stats">
      <div class="card stat"><b><?= (int) ($counts['student'] ?? 0) ?></b><span class="muted"><?= e(t('portal.stat_students')) ?></span></div>
      <div class="card stat"><b><?= (int) ($counts['teacher'] ?? 0) ?></b><span class="muted"><?= e(t('portal.stat_teachers')) ?></span></div>
      <div class="card stat"><b><?= (int) $incomplete ?></b><span class="muted"><?= e(t('portal.stat_incomplete')) ?></span></div>
      <div class="card stat"><b><?= (int) $pending ?></b><span class="muted"><?= e(t('portal.stat_resets')) ?></span></div>
      <div class="card stat"><b><?= (int) $suspended ?></b><span class="muted"><?= e(t('portal.stat_suspended')) ?></span></div>
    </div>
    <div class="actions">
      <a class="btn btn-primary" href="/moderator/users/new"><?= icon('user-plus') ?> <?= e(t('users.new')) ?></a>
      <a class="btn btn-ghost" href="/moderator/users/import"><?= icon('upload') ?> <?= e(t('users.import')) ?></a>
      <a class="btn btn-ghost" href="/moderator/resets"><?= icon('key-round') ?> <?= e(t('resets.title')) ?></a>
    </div>
    <p class="muted"><?= e(t('portal.review_soon')) ?></p>
  </div>
</section>
