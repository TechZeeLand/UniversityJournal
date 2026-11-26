<?php /** @var array $rows, $errors */
use App\Core\Csrf;
?>
<section class="section">
  <div class="wrap">
    <h1 class="h-md"><?= e(t('portal.tech_title')) ?></h1>
    <?php \App\Core\View::partial('portal/_nav', ['active' => 'content', 'actor' => $actor]); ?>
    <?php \App\Core\View::partial('tech/_content_nav', ['active' => 'menus']); ?>
    <p class="muted"><?= e(t('menus.intro')) ?></p>
    <form method="post" action="/tech/menus" class="form" novalidate>
      <?= Csrf::field() ?>
      <?php foreach (['header', 'footer'] as $loc): ?>
        <div class="card pad">
          <h2 class="h-sm first"><?= e(t('menus.' . $loc)) ?></h2>
          <?php if (!empty($errors['_' . $loc])): ?><p class="field-error" role="alert"><?= e($errors['_' . $loc]) ?></p><?php endif; ?>
          <div class="menu-grid menu-head" aria-hidden="true"><span><?= e(t('menus.label_en')) ?></span><span><?= e(t('menus.label_bn')) ?></span><span><?= e(t('menus.url')) ?></span><span><?= e(t('menus.order')) ?></span><span><?= e(t('menus.show')) ?></span><span><?= e(t('menus.remove')) ?></span></div>
          <?php foreach ($rows[$loc] ?? [] as $r): $k = $r['key']; $isNew = $k[0] === 'n'; $er = $errors[$k] ?? []; ?>
            <div class="menu-grid<?= $isNew ? ' is-new' : '' ?>">
              <input type="hidden" name="m[<?= e($k) ?>][location]" value="<?= e($loc) ?>">
              <div><input type="text" name="m[<?= e($k) ?>][label_en]" maxlength="80" value="<?= e($r['label_en']) ?>" aria-label="<?= e(t('menus.label_en')) ?>" placeholder="<?= $isNew ? e(t('menus.new_ph')) : '' ?>"><?= !empty($er['label_en']) ? '<p class="field-error">' . e($er['label_en']) . '</p>' : '' ?></div>
              <div><input type="text" name="m[<?= e($k) ?>][label_bn]" maxlength="80" lang="bn" value="<?= e($r['label_bn']) ?>" aria-label="<?= e(t('menus.label_bn')) ?>"></div>
              <div><input type="text" name="m[<?= e($k) ?>][url]" maxlength="255" value="<?= e($r['url']) ?>" aria-label="<?= e(t('menus.url')) ?>" placeholder="/about" autocapitalize="none" spellcheck="false"><?= !empty($er['url']) ? '<p class="field-error">' . e($er['url']) . '</p>' : '' ?></div>
              <div><input type="number" name="m[<?= e($k) ?>][sort]" min="0" max="100000" value="<?= (int) $r['sort_order'] ?>" aria-label="<?= e(t('menus.order')) ?>"></div>
              <div><input type="checkbox" name="m[<?= e($k) ?>][visible]" value="1"<?= (int) $r['visible'] === 1 ? ' checked' : '' ?> aria-label="<?= e(t('menus.show')) ?>"></div>
              <div><?php if (!$isNew): ?><input type="checkbox" name="m[<?= e($k) ?>][delete]" value="1" aria-label="<?= e(t('menus.remove')) ?>"><?php endif; ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
      <div class="actions"><button class="btn btn-primary" type="submit"><?= e(t('ui.save')) ?></button></div>
    </form>
  </div>
</section>
