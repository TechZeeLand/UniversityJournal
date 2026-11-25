<?php
/** @var string $active */
$pendingN = (int) \App\Core\DB::val("SELECT COUNT(*) FROM password_reset_requests WHERE status = 'pending'");
$items = [
    'overview' => ['/moderator', t('portal.overview')],
    'users' => ['/moderator/users', t('users.title')],
    'resets' => ['/moderator/resets', t('resets.title')],
];
if (($actor['role'] ?? '') === 'tech_admin') {
    $items['tech'] = ['/tech', t('portal.tech_title')];
    $items['settings'] = ['/tech/settings', t('settings.title')];
    $items['audit'] = ['/tech/audit', t('audit.title')];
}
?>
<nav class="portal-nav" aria-label="<?= e(t('portal.nav')) ?>">
  <?php foreach ($items as $key => [$href, $label]): ?>
    <a href="<?= e($href) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?><?= ($key === 'resets' && $pendingN > 0) ? ' <span class="badge badge-warn">' . $pendingN . '</span>' : '' ?></a>
  <?php endforeach; ?>
</nav>
