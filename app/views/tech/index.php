<section class="section">
  <div class="wrap">
    <h1 class="h-md"><?= e(t('portal.tech_title')) ?></h1>
    <?php \App\Core\View::partial('portal/_nav', ['active' => 'tech', 'actor' => $actor]); ?>
    <div class="grid">
      <?php
      $cards = [
        ['users', 'users.title', 'tech.users_d', '/moderator/users'],
        ['settings', 'tech.settings', 'tech.settings_d', '/tech/settings'],
        ['file-text', 'tech.content', 'tech.content_d', '/tech/pages'],
        ['hard-drive', 'backups.title', 'tech.backups_d', '/tech/backups'],
        ['scroll-text', 'audit.title', 'tech.audit_d', '/tech/audit'],
      ];
      foreach ($cards as [$ic, $title, $desc, $href]): ?>
        <article class="card"><span class="card-icon"><?= icon($ic) ?></span><h3><?= e(t($title)) ?></h3>
          <p class="muted"><?= e(t($desc)) ?></p><p><a class="btn btn-primary btn-sm" href="<?= e($href) ?>"><?= e(t('tech.open')) ?></a></p></article>
      <?php endforeach; ?>
    </div>
    <h2 class="h-sm"><?= e(t('tech.system')) ?></h2>
    <div class="card pad"><dl class="kv">
      <?php foreach ($info as $k => $v): ?><dt><?= e($k) ?></dt><dd><?= e($v) ?></dd><?php endforeach; ?>
      <dt><?= e(t('tech.source')) ?></dt><dd><?= $source !== '' ? e($source) : '<span class="muted">' . e(t('tech.source_unset')) . '</span>' ?></dd>
    </dl></div>
  </div>
</section>
