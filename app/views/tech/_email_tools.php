<?php
/** @var array $mailLog @var string $mailTo */
use App\Core\Csrf;
use App\Services\Mailer;
$on = Mailer::configured();
?>
<div class="card pad form">
  <h2 class="h-sm first"><?= icon('send') ?> <?= e(t('email.tools')) ?></h2>
  <?php if ($on): ?>
    <div class="alert alert-ok" role="status"><?= icon('circle-check') ?><span><?= e(t('email.state_on')) ?></span></div>
  <?php else: ?>
    <div class="alert alert-warn" role="status"><?= icon('triangle-alert') ?><span><?= e(t('email.state_off')) ?></span></div>
  <?php endif; ?>
  <p class="muted small"><?= e(t('email.tools_hint')) ?></p>
  <form method="post" action="/tech/email/test" class="form">
    <?= Csrf::field() ?>
    <div class="field"><label for="mail_to"><?= e(t('email.test_to')) ?></label>
      <input id="mail_to" name="to" type="email" maxlength="190" value="<?= e($mailTo) ?>" required></div>
    <div class="actions"><button class="btn btn-primary" type="submit"<?= $on ? '' : ' disabled' ?>><?= icon('send') ?> <?= e(t('email.test_btn')) ?></button></div>
  </form>
  <?php if ($mailLog): ?>
    <h3 class="h-xs"><?= e(t('email.recent')) ?></h3>
    <div class="table-wrap"><table class="tbl">
      <thead><tr><th><?= e(t('email.col_when')) ?></th><th><?= e(t('email.col_to')) ?></th><th><?= e(t('email.col_what')) ?></th><th><?= e(t('email.col_result')) ?></th></tr></thead>
      <tbody>
      <?php foreach ($mailLog as $m): ?>
        <tr><td><?= e(fmt_dt((string) $m['created_at'])) ?></td><td><?= e((string) $m['to_email']) ?></td><td><?= e((string) $m['kind']) ?></td>
          <td><?= $m['status'] === 'sent' ? '<span class="badge badge-ok">' . e(t('email.sent')) . '</span>' : '<span class="badge badge-warn">' . e(t('email.failed')) . '</span> <span class="muted small">' . e((string) $m['error']) . '</span>' ?></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
  <?php endif; ?>
</div>
