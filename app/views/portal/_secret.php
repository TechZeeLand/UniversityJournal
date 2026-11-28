<?php $secret = \App\Core\Flash::pullSecret(); if ($secret): ?>
  <div class="secret" role="alert">
    <p><strong><?= e(t('secret.title', ['who' => trim(($secret['name'] ?? '') . ' (' . $secret['uid'] . ')')])) ?></strong></p>
    <p><code id="secretPw"><?= e($secret['password']) ?></code>
      <button type="button" class="btn btn-ghost btn-sm" data-copy="secretPw" data-done="<?= e(t('ui.copied')) ?>" data-fail="<?= e(t('ui.copy_fail')) ?>"><?= icon('copy') ?> <span><?= e(t('ui.copy')) ?></span></button></p>
    <p class="small muted"><?= e(t('secret.note')) ?></p>
  </div>
<?php endif; ?>
