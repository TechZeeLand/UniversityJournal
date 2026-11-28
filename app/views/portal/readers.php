<?php /** @var array $actor @var array $rows @var int $total @var int $per @var int $page @var string $q */ use App\Core\Csrf; ?>
<section class="section">
  <div class="wrap">
    <h1 class="h-md"><?= e(t('portal.mod_title')) ?></h1>
    <?php \App\Core\View::partial('portal/_nav', ['active' => 'readers', 'actor' => $actor]); ?>
    <?php \App\Core\View::partial('portal/_secret'); ?>
    <p class="muted"><?= e(t('review.readers_intro')) ?></p>
    <form method="get" action="/moderator/readers" class="filters toolbar" role="search"><div class="filters">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('pub.search')) ?>" aria-label="<?= e(t('pub.search')) ?>">
      <button class="btn btn-ghost btn-sm" type="submit"><?= icon('search') ?> <?= e(t('ui.search')) ?></button></div></form>
    <?php if (!$rows): ?><p class="muted"><?= e(t('pub.none')) ?></p><?php else: ?>
      <div class="table-wrap card"><table class="tbl">
        <thead><tr><th><?= e(t('users.th_user')) ?></th><th><?= e(t('reader.signin_with')) ?></th><th><?= e(t('review.th_date')) ?></th><th><?= e(t('users.th_status')) ?></th><th></th></tr></thead>
        <tbody><?php foreach ($rows as $r): $sus = $r['status'] === 'suspended'; ?>
          <tr>
            <td><?= avatar($r, 'avatar-sm') ?> <strong><?= e(display_name($r)) ?></strong><br><span class="muted small"><?= e((string) $r['email']) ?></span></td>
            <td><?= $r['google_sub'] ? 'Google' : e(t('reader.method_email')) ?></td>
            <td class="muted small"><?= e(fmt_dt($r['created_at'])) ?></td>
            <td><span class="badge <?= $sus ? 'badge-danger' : 'badge-ok' ?>"><?= e(t($sus ? 'status.suspended' : 'status.active')) ?></span></td>
            <td class="actions-cell">
              <form method="post" action="/moderator/users/<?= (int) $r['id'] ?>/status" class="inline"><?= Csrf::field() ?><input type="hidden" name="status" value="<?= $sus ? 'active' : 'suspended' ?>">
                <button class="btn btn-ghost btn-sm" type="submit"><?= e(t($sus ? 'users.activate' : 'users.suspend')) ?></button></form>
              <form method="post" action="/moderator/users/<?= (int) $r['id'] ?>/2fa-reset" class="inline" data-confirm="<?= e(t('sec.admin_confirm')) ?>"><?= Csrf::field() ?><button class="btn btn-ghost btn-sm" type="submit" title="<?= e(t('sec.admin_reset')) ?>"><?= icon('shield-x') ?></button></form>
              <?php if (!$r['google_sub']): ?><form method="post" action="/moderator/users/<?= (int) $r['id'] ?>/password" class="inline" data-confirm="<?= e(t('users.confirm_reset')) ?>"><?= Csrf::field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= icon('key-round') ?></button></form><?php endif; ?>
            </td></tr>
        <?php endforeach; ?></tbody></table></div>
      <?php \App\Core\View::partial('partials/pager', ['total' => $total, 'per' => $per, 'page' => $page]); ?>
    <?php endif; ?>
  </div>
</section>
