<section class="section">
  <div class="wrap narrow-md">
    <p><a href="/moderator/users">‹ <?= e(t('users.title')) ?></a></p>
    <?php \App\Core\View::partial('portal/_secret'); ?>
    <div class="who">
      <?= avatar($target, 'avatar-lg') ?>
      <div>
        <h1 class="h-md"><?= e(display_name($target)) ?></h1>
        <p><code><?= e(\App\Core\Identity::loginId($target)) ?></code>
          <span class="badge"><?= e(role_label($target['role'])) ?></span>
          <span class="badge <?= $target['status'] === 'active' ? 'badge-ok' : 'badge-danger' ?>"><?= e(t('status.' . $target['status'])) ?></span></p>
        <p class="muted small"><?= e(t('id.both', ['uid' => ((string) $target['uid'] !== '' ? $target['uid'] : '—'), 'no' => (string) $target['serial_no']])) ?></p>
        <p class="muted small"><?= e(t('users.created_at', ['d' => fmt_dt($target['created_at'])])) ?> · <?= e(t('users.th_last_login')) ?>: <?= e(fmt_dt($target['last_login_at'])) ?>
          <?= (int) $target['must_change_password'] === 1 ? ' · ' . e(t('users.must_change')) : '' ?></p>
        <?php if ($pubPath = \App\Services\PublicUrl::path($target)): $pubOn = \App\Services\PublicUrl::visible($target + (\App\Core\DB::one('SELECT profile_complete, is_public FROM user_profiles WHERE user_id = ?', [(int) $target['id']]) ?: [])); ?>
          <p class="small"><?= icon('link') ?> <?= e(t('users.public_addr')) ?>: <code id="pubaddr"><?= e($pubPath) ?></code>
            <span class="badge <?= $pubOn ? 'badge-ok' : '' ?>"><?= e(t($pubOn ? 'users.public_live' : 'users.public_hidden')) ?></span>
            <?php if (!empty($target['public_no'])): ?><span class="muted"> · <?= e(t('users.public_no', ['n' => $target['public_no']])) ?></span><?php endif; ?></p>
        <?php endif; ?>
        <p class="small muted"><?= icon('shield-check') ?> <?= e(t(\App\Services\TwoFactor::enabled((int) $target['id']) ? 'sec.admin_has' : 'sec.admin_hasnt')) ?></p>
        <?php if (in_array($target['role'], ['student', 'teacher'], true)): ?>
          <p class="small"><?= $missing ? '<span class="muted">' . e(t('dash.profile_missing', ['list' => implode(', ', array_map(fn($f) => t('profile.f.' . $f), $missing))])) . '</span>' : icon('circle-check') . ' ' . e(t('dash.profile_done')) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!empty($error)): ?><div class="alert alert-error" role="alert"><?= icon('circle-alert') ?><span><?= e($error) ?></span></div><?php endif; ?>
    <form method="post" action="/moderator/users/<?= (int) $target['id'] ?>" class="form card pad">
      <?= \App\Core\Csrf::field() ?>
      <h2 class="h-sm"><?= e(t('users.edit')) ?></h2>
      <?php \App\Core\View::partial('portal/_user_fields', ['old' => $old, 'roles' => $roles, 'departments' => $departments, 'lockRole' => $self, 'canSlug' => !empty($canSlug)]); ?>
      <div class="actions"><button class="btn btn-primary" type="submit"><?= e(t('ui.save')) ?></button></div>
    </form>

    <?php if (!$self): ?>
      <div class="card pad danger-zone">
        <h2 class="h-sm"><?= e(t('users.actions')) ?></h2>
        <div class="actions">
          <form method="post" action="/moderator/users/<?= (int) $target['id'] ?>/password" data-confirm="<?= e(t('users.confirm_reset')) ?>">
            <?= \App\Core\Csrf::field() ?>
            <button class="btn btn-ghost" type="submit"><?= icon('key-round') ?> <?= e(t('users.reset_pw')) ?></button>
          </form>
          <?php if (\App\Services\TwoFactor::enabled((int) $target['id'])): ?>
          <form method="post" action="/moderator/users/<?= (int) $target['id'] ?>/2fa-reset" data-confirm="<?= e(t('sec.admin_confirm')) ?>">
            <?= \App\Core\Csrf::field() ?>
            <button class="btn btn-ghost" type="submit"><?= icon('shield-x') ?> <?= e(t('sec.admin_reset')) ?></button>
          </form>
          <?php endif; ?>
          <form method="post" action="/moderator/users/<?= (int) $target['id'] ?>/status" data-confirm="<?= e(t($target['status'] === 'active' ? 'users.confirm_suspend' : 'users.confirm_activate')) ?>">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="status" value="<?= $target['status'] === 'active' ? 'suspended' : 'active' ?>">
            <button class="btn <?= $target['status'] === 'active' ? 'btn-danger' : 'btn-primary' ?>" type="submit"><?= e(t($target['status'] === 'active' ? 'users.suspend' : 'users.activate')) ?></button>
          </form>
        </div>
        <p class="muted small"><?= e(t('users.actions_note')) ?></p>
      </div>
    <?php endif; ?>
  </div>
</section>
