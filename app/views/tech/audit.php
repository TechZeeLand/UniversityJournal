<section class="section">
  <div class="wrap">
    <h1 class="h-md"><?= e(t('portal.tech_title')) ?></h1>
    <?php \App\Core\View::partial('portal/_nav', ['active' => 'audit', 'actor' => $actor]); ?>
    <form method="get" action="/tech/audit" class="filters toolbar" role="search">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('audit.search')) ?>" aria-label="<?= e(t('audit.search')) ?>">
      <button class="btn btn-ghost btn-sm" type="submit"><?= icon('search') ?> <?= e(t('ui.search')) ?></button>
    </form>
    <div class="table-wrap card">
      <table class="tbl">
        <thead><tr><th><?= e(t('audit.th_time')) ?></th><th><?= e(t('audit.th_actor')) ?></th><th><?= e(t('audit.th_action')) ?></th><th><?= e(t('audit.th_target')) ?></th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr><td class="muted"><?= e(fmt_dt($r['created_at'])) ?></td><td><?= e($r['actor'] ?? '—') ?></td>
            <td><code><?= e($r['action']) ?></code></td><td><?= e($r['target']) ?><?= $r['detail'] ? '<br><span class="muted small">' . e($r['detail']) . '</span>' : '' ?></td>
            <td class="muted"><?= e($r['ip']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="muted"><?= e(t('audit.none')) ?></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php \App\Core\View::partial('partials/pager', ['total' => $total, 'per' => $per, 'page' => $page]); ?>
  </div>
</section>
