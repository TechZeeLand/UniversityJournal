<?php
use App\Services\Users;
?>
<section class="section">
  <div class="wrap">
    <h1 class="h-md"><?= e(t('portal.mod_title')) ?></h1>
    <?php \App\Core\View::partial('portal/_nav', ['active' => 'resets', 'actor' => $actor]); ?>
    <?php \App\Core\View::partial('portal/_secret'); ?>
    <p class="muted"><?= e(t('resets.intro')) ?></p>

    <h2 class="h-sm"><?= e(t('resets.pending')) ?></h2>
    <?php if (!$pendingRows): ?>
      <p class="muted"><?= e(t('resets.none')) ?></p>
    <?php else: ?>
      <div class="table-wrap card">
        <table class="tbl">
          <thead><tr><th><?= e(t('users.th_user')) ?></th><th><?= e(t('resets.th_requested')) ?></th><th><?= e(t('resets.th_message')) ?></th><th></th></tr></thead>
          <tbody>
          <?php foreach ($pendingRows as $r): $can = Users::canManage($actor, $r) && (int) $r['user_id'] !== (int) $actor['id']; ?>
            <tr>
              <td><a href="/moderator/users/<?= (int) $r['user_id'] ?>"><?= e(display_name($r)) ?></a><br><code><?= e(\App\Core\Identity::loginId($r)) ?></code> <span class="badge"><?= e(role_label($r['role'])) ?></span></td>
              <td class="muted"><?= e(fmt_dt($r['created_at'])) ?></td>
              <td><?= e($r['contact']) ?><?= $r['note'] ? '<br><span class="muted small">' . e($r['note']) . '</span>' : '' ?></td>
              <td>
                <?php if ($can): ?>
                  <div class="actions">
                    <form method="post" action="/moderator/resets/<?= (int) $r['id'] ?>/approve" data-confirm="<?= e(t('resets.confirm')) ?>"><?= \App\Core\Csrf::field() ?>
                      <button class="btn btn-primary btn-sm" type="submit"><?= e(t('resets.approve')) ?></button></form>
                    <form method="post" action="/moderator/resets/<?= (int) $r['id'] ?>/reject"><?= \App\Core\Csrf::field() ?>
                      <button class="btn btn-ghost btn-sm" type="submit"><?= e(t('resets.reject')) ?></button></form>
                  </div>
                <?php else: ?><span class="muted small"><?= e(t('resets.needs_tech')) ?></span><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php if ($doneRows): ?>
      <h2 class="h-sm"><?= e(t('resets.recent')) ?></h2>
      <div class="table-wrap card">
        <table class="tbl">
          <tbody>
          <?php foreach ($doneRows as $r): ?>
            <tr>
              <td><code><?= e(\App\Core\Identity::loginId($r)) ?></code> <?= e(display_name($r)) ?></td>
              <td><span class="badge <?= $r['status'] === 'approved' ? 'badge-ok' : '' ?>"><?= e(t('resets.st_' . $r['status'])) ?></span></td>
              <td class="muted small"><?= e(fmt_dt($r['handled_at'])) ?> · <?= e(($r['handled_uid'] ?? '') !== '' ? $r['handled_uid'] : (($r['handled_serial'] ?? null) !== null ? '#' . $r['handled_serial'] : '—')) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>
