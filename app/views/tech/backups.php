<?php /** @var array $list; @var bool $auto; @var int $every, $keep; @var string $dir */
use App\Core\Csrf;
$ex = $list[0]['name'] ?? 'db-YYYYmmdd-HHMMSS.sql.gz';
?>
<section class="section">
  <div class="wrap narrow-md">
    <h1 class="h-md"><?= e(t('portal.tech_title')) ?></h1>
    <?php \App\Core\View::partial('portal/_nav', ['active' => 'backups', 'actor' => $actor]); ?>
    <div class="card pad">
      <h2 class="h-sm first"><?= e(t('backups.data_title')) ?></h2>
      <p><?= e(t('backups.data_intro')) ?></p>
      <p><code><?= e($dir) ?></code></p>
      <ul class="small muted">
        <li><code>db/</code> <?= e(t('backups.f_db')) ?></li>
        <li><code>uploads/</code> <?= e(t('backups.f_uploads')) ?></li>
        <li><code>backups/</code> <?= e(t('backups.f_backups')) ?></li>
        <li><code>runtime/</code> <?= e(t('backups.f_runtime')) ?></li>
      </ul>
    </div>
    <div class="toolbar">
      <p class="muted small"><?= e(t($auto ? 'backups.auto_on' : 'backups.auto_off', ['h' => $every, 'n' => $keep])) ?> <a href="/tech/settings/backups"><?= e(t('backups.change')) ?></a></p>
      <form method="post" action="/tech/backups"><?= Csrf::field() ?><button class="btn btn-primary btn-sm" type="submit"><?= icon('hard-drive') ?> <?= e(t('backups.create')) ?></button></form>
    </div>
    <div class="table-wrap card">
      <table class="tbl">
        <thead><tr><th><?= e(t('backups.th_file')) ?></th><th><?= e(t('backups.th_size')) ?></th><th><?= e(t('audit.th_time')) ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach ($list as $b): ?>
          <tr><td><code><?= e($b['name']) ?></code></td><td><?= e(fmt_bytes($b['size'])) ?></td><td class="muted"><?= e(fmt_dt(date('Y-m-d H:i:s', $b['time']))) ?></td>
            <td class="actions-cell">
              <a class="btn btn-ghost btn-sm" href="/tech/backups/<?= e(rawurlencode($b['name'])) ?>"><?= icon('download') ?> <?= e(t('backups.download')) ?></a>
              <form method="post" action="/tech/backups/<?= e(rawurlencode($b['name'])) ?>/delete" class="inline" data-confirm="<?= e(t('backups.confirm_delete')) ?>"><?= Csrf::field() ?><button class="btn btn-ghost btn-sm" type="submit" aria-label="<?= e(t('pages.delete')) ?>"><?= icon('trash-2') ?></button></form>
            </td></tr>
        <?php endforeach; ?>
        <?php if (!$list): ?><tr><td colspan="4" class="muted"><?= e(t('backups.none')) ?></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="card pad">
      <h2 class="h-sm first"><?= e(t('backups.restore_title')) ?></h2>
      <p class="small"><?= e(t('backups.restore_intro')) ?></p>
      <pre class="codeblock"><code>docker exec -i university-journal sh -c 'gunzip -c /data/backups/<?= e($ex) ?> | mariadb --socket=/run/mysqld/mysqld.sock -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME"'</code></pre>
    </div>
  </div>
</section>
