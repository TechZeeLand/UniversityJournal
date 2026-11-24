<?php
$pages = (int) ceil($total / max(1, $per));
if ($pages > 1):
    $href = fn(int $n) => '?' . http_build_query(array_merge($_GET, ['p' => $n]));
?>
<nav class="pager" aria-label="<?= e(t('ui.pagination')) ?>">
  <?php if ($page > 1): ?><a class="btn btn-ghost btn-sm" href="<?= e($href($page - 1)) ?>">‹ <?= e(t('ui.prev')) ?></a><?php endif; ?>
  <span class="muted"><?= e(t('ui.page_of', ['p' => $page, 'n' => $pages])) ?></span>
  <?php if ($page < $pages): ?><a class="btn btn-ghost btn-sm" href="<?= e($href($page + 1)) ?>"><?= e(t('ui.next')) ?> ›</a><?php endif; ?>
</nav>
<?php endif; ?>
