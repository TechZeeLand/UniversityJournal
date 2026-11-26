<section class="section">
  <div class="wrap">
    <h1 class="h-md"><?= e(t('portal.tech_title')) ?></h1>
    <?php \App\Core\View::partial('portal/_nav', ['active' => 'content', 'actor' => $actor]); ?>
    <?php \App\Core\View::partial('tech/_content_nav', ['active' => 'pages']); ?>
    <div class="toolbar">
      <p class="muted"><?= e(t('pages.intro')) ?></p>
      <div class="actions"><a class="btn btn-primary btn-sm" href="/tech/pages/new"><?= icon('plus') ?> <?= e(t('pages.new')) ?></a></div>
    </div>
    <div class="table-wrap card">
      <table class="tbl">
        <thead><tr><th><?= e(t('pages.th_title')) ?></th><th><?= e(t('pages.th_address')) ?></th><th><?= e(t('users.th_status')) ?></th><th><?= e(t('audit.th_time')) ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach ($pages as $p): ?>
          <tr>
            <td><a class="usercell" href="/tech/pages/<?= (int) $p['id'] ?>"><?= e($p['title_en']) ?></a><?= (int) $p['is_system'] === 1 ? ' <span class="badge">' . e(t('pages.builtin')) . '</span>' : '' ?></td>
            <td><code>/<?= e($p['slug']) ?></code></td>
            <td><span class="badge <?= $p['status'] === 'published' ? 'badge-ok' : 'badge-warn' ?>"><?= e(t('pages.status_' . $p['status'])) ?></span></td>
            <td class="muted"><?= e(fmt_dt($p['updated_at'])) ?></td>
            <td><a class="btn btn-ghost btn-sm" href="/tech/pages/<?= (int) $p['id'] ?>"><?= icon('pencil') ?> <?= e(t('pages.edit')) ?></a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
