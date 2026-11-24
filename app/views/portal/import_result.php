<?php
$n = ['created' => 0, 'skipped' => 0, 'error' => 0];
foreach ($rows as $r) $n[$r['status']] = ($n[$r['status']] ?? 0) + 1;
?>
<section class="section">
  <div class="wrap">
    <h1 class="h-md"><?= e(t('import.result_title')) ?></h1>
    <?php \App\Core\View::partial('portal/_nav', ['active' => 'users', 'actor' => $actor]); ?>
    <div class="alert alert-warn"><?= icon('triangle-alert') ?><span><?= e(t('import.result_note')) ?></span></div>
    <p><?= e(t('import.summary', ['c' => $n['created'], 's' => $n['skipped'], 'e' => $n['error']])) ?>
       <?= $truncated ? e(t('import.truncated', ['n' => $limit])) : '' ?></p>
    <p class="actions"><a class="btn btn-primary" href="/moderator/users/import/report.csv"><?= icon('download') ?> <?= e(t('import.download')) ?></a>
       <a class="btn btn-ghost" href="/moderator/users"><?= e(t('users.title')) ?></a></p>
    <div class="table-wrap card">
      <table class="tbl">
        <thead><tr><th>#</th><th><?= e(t('users.th_uid')) ?></th><th><?= e(t('profile.f.full_name')) ?></th><th><?= e(t('users.th_role')) ?></th><th><?= e(t('import.th_result')) ?></th><th><?= e(t('import.th_password')) ?></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="muted"><?= (int) $r['line'] ?></td>
            <td><code><?= e($r['uid']) ?></code></td>
            <td><?= e($r['name']) ?></td>
            <td><?= e($r['role']) ?></td>
            <td><span class="badge <?= $r['status'] === 'created' ? 'badge-ok' : ($r['status'] === 'skipped' ? 'badge-warn' : 'badge-danger') ?>"><?= e(t('import.st_' . $r['status'])) ?></span>
                <?= $r['msg'] !== '' ? '<span class="muted small">' . e($r['msg']) . '</span>' : '' ?></td>
            <td><?= $r['password'] !== '' ? '<code>' . e($r['password']) . '</code>' : '' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
