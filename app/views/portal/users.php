<section class="section">
  <div class="wrap">
    <h1 class="h-md"><?= e(t('portal.mod_title')) ?></h1>
    <?php \App\Core\View::partial('portal/_nav', ['active' => 'users', 'actor' => $actor]); ?>
    <div class="toolbar">
      <form method="get" action="/moderator/users" class="filters" role="search">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('users.search')) ?>" aria-label="<?= e(t('users.search')) ?>">
        <select name="role" aria-label="<?= e(t('users.th_role')) ?>">
          <option value=""><?= e(t('users.all_roles')) ?></option>
          <?php foreach ($roles as $r): ?><option value="<?= e($r) ?>"<?= $r === $roleFilter ? ' selected' : '' ?>><?= e(role_label($r)) ?></option><?php endforeach; ?>
        </select>
        <select name="status" aria-label="<?= e(t('users.th_status')) ?>">
          <option value=""><?= e(t('users.all_status')) ?></option>
          <?php foreach (['active', 'suspended'] as $s): ?><option value="<?= $s ?>"<?= $s === $statusFilter ? ' selected' : '' ?>><?= e(t('status.' . $s)) ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-ghost btn-sm" type="submit"><?= icon('search') ?> <?= e(t('ui.search')) ?></button>
      </form>
      <div class="actions">
        <a class="btn btn-primary btn-sm" href="/moderator/users/new"><?= icon('user-plus') ?> <?= e(t('users.new')) ?></a>
        <a class="btn btn-ghost btn-sm" href="/moderator/users/import"><?= icon('upload') ?> <?= e(t('users.import')) ?></a>
      </div>
    </div>

    <?php if (!$rows): ?>
      <p class="muted"><?= e(t('users.none')) ?></p>
    <?php else: ?>
      <p class="muted small"><?= e(t('users.count', ['n' => $total])) ?></p>
      <div class="table-wrap card">
        <table class="tbl">
          <thead><tr>
            <th><?= e(t('users.th_user')) ?></th><th><?= e(t('users.th_uid')) ?></th><th><?= e(t('users.th_role')) ?></th>
            <th><?= e(t('users.th_status')) ?></th><th><?= e(t('users.th_profile')) ?></th><th><?= e(t('users.th_last_login')) ?></th>
          </tr></thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><a class="usercell" href="/moderator/users/<?= (int) $r['id'] ?>"><?= avatar($r, 'avatar-sm') ?><span><?= e(display_name($r)) ?></span></a></td>
              <td><code><?= e($r['uid']) ?></code></td>
              <td><?= e(role_label($r['role'])) ?></td>
              <td><span class="badge <?= $r['status'] === 'active' ? 'badge-ok' : 'badge-danger' ?>"><?= e(t('status.' . $r['status'])) ?></span></td>
              <td><?= in_array($r['role'], ['student', 'teacher'], true) ? ((int) $r['profile_complete'] === 1 ? icon('circle-check') . ' ' . e(t('users.complete')) : '<span class="muted">' . e(t('users.incomplete')) . '</span>') : '—' ?></td>
              <td class="muted"><?= e(fmt_dt($r['last_login_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php \App\Core\View::partial('partials/pager', ['total' => $total, 'per' => $per, 'page' => $page]); ?>
    <?php endif; ?>
  </div>
</section>
