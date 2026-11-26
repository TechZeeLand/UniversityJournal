<?php /** @var string $active pages|menus|texts */
$items = ['pages' => ['/tech/pages', 'file-text', t('pages.title')], 'menus' => ['/tech/menus', 'list', t('menus.title')], 'texts' => ['/tech/texts', 'type', t('texts.title')]];
?>
<nav class="portal-nav" aria-label="<?= e(t('tech.content')) ?>">
  <?php foreach ($items as $k => [$href, $ic, $label]): ?>
    <a href="<?= e($href) ?>"<?= $active === $k ? ' aria-current="page"' : '' ?>><?= icon($ic) ?> <?= e($label) ?></a>
  <?php endforeach; ?>
</nav>
