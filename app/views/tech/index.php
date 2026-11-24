<section class="section">
  <div class="wrap">
    <h1 class="h-md"><?= e(t('portal.tech_title')) ?></h1>
    <?php \App\Core\View::partial('portal/_nav', ['active' => 'tech', 'actor' => $actor]); ?>
    <div class="grid">
      <article class="card"><span class="card-icon"><?= icon('users') ?></span><h3><?= e(t('users.title')) ?></h3>
        <p class="muted"><?= e(t('tech.users_d')) ?></p><p><a class="btn btn-primary btn-sm" href="/moderator/users"><?= e(t('tech.open')) ?></a></p></article>
      <article class="card"><span class="card-icon"><?= icon('scroll-text') ?></span><h3><?= e(t('audit.title')) ?></h3>
        <p class="muted"><?= e(t('tech.audit_d')) ?></p><p><a class="btn btn-primary btn-sm" href="/tech/audit"><?= e(t('tech.open')) ?></a></p></article>
      <article class="card"><span class="card-icon"><?= icon('shield-check') ?></span><h3><?= e(t('tech.settings')) ?></h3>
        <p class="muted"><?= e(t('tech.settings_d')) ?></p></article>
    </div>
    <h2 class="h-sm"><?= e(t('tech.system')) ?></h2>
    <div class="card pad"><dl class="kv">
      <?php foreach ($info as $k => $v): ?><dt><?= e($k) ?></dt><dd><?= e($v) ?></dd><?php endforeach; ?>
      <dt><?= e(t('tech.source')) ?></dt><dd><?= $source !== '' ? e($source) : '<span class="muted">' . e(t('tech.source_unset')) . '</span>' ?></dd>
    </dl></div>
  </div>
</section>
