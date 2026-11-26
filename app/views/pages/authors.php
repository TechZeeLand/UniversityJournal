<?php use App\Services\PublicUrl; ?>
<section class="section">
  <div class="wrap">
    <h1 class="h-md"><?= e(t('pub.authors_title')) ?></h1>
    <p class="lead"><?= e(t('pub.authors_sub')) ?></p>
    <form method="get" action="/authors" class="filters toolbar" role="search">
      <div class="filters">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('pub.search')) ?>" aria-label="<?= e(t('pub.search')) ?>">
        <select name="role" aria-label="<?= e(t('users.th_role')) ?>">
          <option value=""><?= e(t('users.all_roles')) ?></option>
          <?php foreach (['student', 'teacher'] as $r): ?><option value="<?= $r ?>"<?= $r === $role ? ' selected' : '' ?>><?= e(role_label($r)) ?></option><?php endforeach; ?>
        </select>
        <select name="dept" aria-label="<?= e(t('profile.f.department_id')) ?>">
          <option value="0"><?= e(t('pub.all_depts')) ?></option>
          <?php foreach ($departments as $faculty => $list): ?>
            <optgroup label="<?= e($faculty) ?>"><?php foreach ($list as $d): ?><option value="<?= (int) $d['id'] ?>"<?= $d['id'] === $dept ? ' selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?></optgroup>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-ghost btn-sm" type="submit"><?= icon('search') ?> <?= e(t('ui.search')) ?></button>
      </div>
    </form>
    <?php if (!$rows): ?>
      <p class="muted"><?= e(t('pub.none')) ?></p>
    <?php else: ?>
      <div class="grid people">
        <?php foreach ($rows as $r): $href = PublicUrl::path($r); ?>
          <a class="card person" href="<?= e((string) $href) ?>">
            <?= avatar($r, 'avatar-lg') ?>
            <h3><?= e(display_name($r)) ?></h3>
            <p class="muted small"><?= e(role_label($r['role'])) ?><?php $dn = \App\Services\Profile::departmentName($r['department_id'] === null ? null : (int) $r['department_id']); if ($dn !== '') echo ' · ' . e($dn); ?></p>
            <?php $sub = $r['role'] === 'student' ? (string) $r['academic_session'] : (string) $r['designation']; if ($sub !== ''): ?><p class="muted small"><?= e($sub) ?></p><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
      <?php \App\Core\View::partial('partials/pager', ['total' => $total, 'per' => $per, 'page' => $page]); ?>
    <?php endif; ?>
  </div>
</section>
