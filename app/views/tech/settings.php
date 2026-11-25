<?php
/** @var array $actor, $groups, $fields, $values, $errors; @var string $group; @var ?array $idInfo */
use App\Core\{Csrf, View};
?>
<section class="section">
  <div class="wrap narrow-md">
    <h1 class="h-md"><?= e(t('portal.tech_title')) ?></h1>
    <?php View::partial('portal/_nav', ['active' => 'settings', 'actor' => $actor]); ?>
    <nav class="portal-nav" aria-label="<?= e(t('settings.tabs')) ?>">
      <?php foreach ($groups as $g => $def): ?>
        <a href="/tech/settings/<?= e($g) ?>"<?= $g === $group ? ' aria-current="page"' : '' ?>><?= icon($def['icon']) ?> <?= e(t('settings.tab.' . $g)) ?></a>
      <?php endforeach; ?>
    </nav>

    <?php if ($idInfo !== null): $seq = $idInfo['mode'] === 'sequential'; ?>
      <form method="post" action="/tech/settings/id-mode" class="form card pad" data-confirm="<?= e(t('id.confirm')) ?>">
        <?= Csrf::field() ?>
        <h2 class="h-sm"><?= e(t('id.title')) ?></h2>
        <p class="muted"><?= e(t('id.intro')) ?></p>
        <label class="choice">
          <input type="radio" name="id_mode" value="university"<?= $seq ? '' : ' checked' ?>>
          <span><strong><?= e(t('id.opt_university')) ?></strong><br><span class="muted small"><?= e(t('id.opt_university_d')) ?></span></span>
        </label>
        <label class="choice">
          <input type="radio" name="id_mode" value="sequential"<?= $seq ? ' checked' : '' ?>>
          <span><strong><?= e(t('id.opt_sequential')) ?></strong><br><span class="muted small"><?= e(t('id.opt_sequential_d')) ?></span></span>
        </label>
        <p class="muted small"><?= e(t('id.stats', ['total' => $idInfo['total'], 'next' => $idInfo['next'], 'nouid' => $idInfo['noUid']])) ?></p>
        <?php if ($idInfo['noUid'] > 0): ?>
          <div class="alert alert-warn" role="status"><?= icon('triangle-alert') ?><span><?= e(t('id.noid_warn', ['n' => $idInfo['noUid']])) ?>
            <a href="/moderator/users?noid=1"><?= e(t('id.noid_link')) ?></a></span></div>
        <?php endif; ?>
        <div class="actions"><button class="btn btn-primary" type="submit"><?= e(t('id.apply')) ?></button></div>
      </form>
    <?php endif; ?>

    <form method="post" action="/tech/settings/<?= e($group) ?>" class="form card pad" novalidate>
      <?= Csrf::field() ?>
      <h2 class="h-sm"><?= e(t('settings.tab.' . $group)) ?></h2>
      <?php foreach ($fields as $f): $k = $f['key']; $err = $errors[$k] ?? null; ?>
        <div class="field">
          <label for="f_<?= e($k) ?>"><?= e(t('set.' . $k)) ?></label>
          <?php if ($f['type'] === 'select'): ?>
            <select id="f_<?= e($k) ?>" name="<?= e($k) ?>">
              <?php foreach ($f['options'] as $o): ?><option value="<?= e($o) ?>"<?= ($values[$k] ?? '') === $o ? ' selected' : '' ?>><?= e(t('set.opt.' . $o)) ?></option><?php endforeach; ?>
            </select>
          <?php elseif ($f['type'] === 'int'): ?>
            <input id="f_<?= e($k) ?>" name="<?= e($k) ?>" type="number" inputmode="numeric" min="<?= (int) $f['min'] ?>" max="<?= (int) $f['max'] ?>" value="<?= e($values[$k] ?? '') ?>">
          <?php else: ?>
            <input id="f_<?= e($k) ?>" name="<?= e($k) ?>" type="<?= $f['type'] === 'url' ? 'url' : 'text' ?>" maxlength="<?= (int) $f['max_len'] ?>" value="<?= e($values[$k] ?? '') ?>">
          <?php endif; ?>
          <?php if ($err): ?><p class="field-error" role="alert"><?= e($err) ?></p><?php endif; ?>
          <p class="field-hint"><?= e(t('set.' . $k . '.hint')) ?></p>
        </div>
      <?php endforeach; ?>
      <div class="actions"><button class="btn btn-primary" type="submit"><?= e(t('ui.save')) ?></button></div>
    </form>
  </div>
</section>
