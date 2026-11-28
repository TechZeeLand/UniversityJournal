<?php
/** @var string $active */
$pendingN = (int) \App\Core\DB::val("SELECT COUNT(*) FROM password_reset_requests WHERE status = 'pending'");
$reportN = \App\Services\Engagement::reportTotal();
$reviewN = (int) \App\Core\DB::val("SELECT COUNT(*) FROM articles WHERE status = 'submitted'");
$items = [
    'overview' => ['/moderator', t('portal.overview')],
    'users' => ['/moderator/users', t('users.title')],
    'articles' => ['/moderator/articles', t('review.title')],
    'comments' => ['/moderator/comments', t('review.comments')],
    'readers' => ['/moderator/readers', t('review.readers')],
    'resets' => ['/moderator/resets', t('resets.title')],
];
$items['security'] = ['/security', t('sec.title')];
if (($actor['role'] ?? '') === 'tech_admin') {
    $items['tech'] = ['/tech', t('portal.tech_title')];
    $items['settings'] = ['/tech/settings', t('settings.title')];
    $items['content'] = ['/tech/pages', t('tech.content')];
    $items['backups'] = ['/tech/backups', t('backups.title')];
    $items['audit'] = ['/tech/audit', t('audit.title')];
}
?>
<nav class="portal-nav" aria-label="<?= e(t('portal.nav')) ?>">
  <?php foreach ($items as $key => [$href, $label]): ?>
    <a href="<?= e($href) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?><?= ($key === 'resets' && $pendingN > 0) ? ' <span class="badge badge-warn">' . $pendingN . '</span>' : '' ?><?= ($key === 'comments' && $reportN > 0) ? ' <span class="badge badge-warn">' . $reportN . '</span>' : '' ?><?= ($key === 'articles' && $reviewN > 0) ? ' <span class="badge badge-warn">' . $reviewN . '</span>' : '' ?></a>
  <?php endforeach; ?>
</nav>
