<?php
/** Shows whether the person's email is confirmed, with a button to send the link again. Needs $u. Only shown when the site can send email. */
use App\Core\Csrf;
use App\Services\Mailer;
$addr = trim((string) ($u['email'] ?? ''));
if ($addr === '' || !Mailer::configured()) return;
if (($u['email_verified_at'] ?? null) !== null): ?>
  <p class="small muted"><?= icon('circle-check') ?> <?= e(t('email.is_verified', ['email' => $addr])) ?></p>
<?php else: ?>
  <div class="alert alert-info" role="status">
    <?= icon('mail') ?>
    <span><strong><?= e(t('email.not_verified')) ?></strong> <?= e(t('email.not_verified_d', ['email' => $addr])) ?>
      <form method="post" action="/account/verify-email" style="display:inline">
        <?= Csrf::field() ?>
        <button class="btn btn-ghost btn-sm" type="submit"><?= icon('send') ?> <?= e(t('email.send_link')) ?></button>
      </form>
    </span>
  </div>
<?php endif;
