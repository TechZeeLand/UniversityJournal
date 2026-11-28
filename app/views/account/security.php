<?php
/**
 * Security page. Needs: $u, $featureOn, $hasTotp, $hasEmail, $totpAllowed, $emailAllowed, $emailVerified, $required, $mustEnroll,
 * $backupLeft, $newBackup (list|null), $setupSecret, $setupUri, $setupPretty, $emailSetup, $maskedEmail, $hasPw, $trustDays, $trusted, $sessions
 */
use App\Core\Csrf;
$pw = static function (string $id) use ($hasPw): void {
    if (!$hasPw) return;
    echo '<div class="field"><label for="' . e($id) . '">' . e(t('sec.current_pw')) . '</label>'
       . '<input id="' . e($id) . '" name="password" type="password" autocomplete="current-password" required></div>';
};
$on = static fn(bool $b): string => '<span class="badge ' . ($b ? 'badge-ok' : '') . '">' . e(t($b ? 'sec.on' : 'sec.off')) . '</span>';
?>
<section class="section">
  <div class="wrap narrow-md">
    <h1 class="h-md"><?= e(t('sec.title')) ?></h1>
    <p class="muted"><?= e(t('sec.intro')) ?></p>

    <?php if ($mustEnroll): ?>
      <div class="alert alert-warn" role="status"><?= icon('triangle-alert') ?><span><strong><?= e(t('sec.must_enroll')) ?></strong></span></div>
    <?php elseif ($required && !$hasTotp && !$hasEmail): ?>
      <div class="alert alert-info" role="note"><?= icon('info') ?><span><?= e(t('sec.required_no_way')) ?></span></div>
    <?php endif; ?>

    <?php if ($newBackup): ?>
      <div class="card pad backup-box" id="backup-codes">
        <h2 class="h-sm first"><?= icon('key-round') ?> <?= e(t('sec.backup_title')) ?></h2>
        <div class="alert alert-warn" role="status"><?= icon('triangle-alert') ?><span><?= e(t('sec.backup_once')) ?></span></div>
        <ul class="backup-grid" id="backup-list">
          <?php foreach ($newBackup as $c): ?><li><code><?= e($c) ?></code></li><?php endforeach; ?>
        </ul>
        <div class="actions">
          <button class="btn btn-ghost btn-sm" type="button" data-copy-list="#backup-list"><?= icon('copy') ?> <?= e(t('sec.copy')) ?></button>
          <button class="btn btn-ghost btn-sm" type="button" data-download-list="#backup-list" data-filename="backup-codes.txt"><?= icon('download') ?> <?= e(t('sec.download')) ?></button>
          <button class="btn btn-ghost btn-sm" type="button" onclick="window.print()"><?= icon('printer') ?> <?= e(t('sec.print')) ?></button>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!$featureOn): ?>
      <div class="alert alert-info" role="note"><?= icon('info') ?><span><?= e(t('sec.feature_off')) ?></span></div>
    <?php endif; ?>

    <!-- Authenticator app -->
    <div class="card pad sec-card" id="totp">
      <h2 class="h-sm first"><?= icon('smartphone') ?> <?= e(t('sec.totp_title')) ?> <?= $on($hasTotp) ?></h2>
      <p class="muted"><?= e(t('sec.totp_d')) ?></p>
      <?php if ($hasTotp): ?>
        <form method="post" action="/security/totp/disable" class="form" data-confirm="<?= e(t('sec.confirm_off')) ?>">
          <?= Csrf::field() ?>
          <?php $pw('pw_totp_off'); ?>
          <div class="actions"><button class="btn btn-danger btn-sm" type="submit"><?= e(t('sec.turn_off')) ?></button></div>
        </form>
      <?php elseif ($setupSecret !== null): ?>
        <ol class="steps">
          <li><?= e(t('sec.totp_step1')) ?></li>
          <li><?= e(t('sec.totp_step2')) ?>
            <div class="qr-box" data-qr="<?= e($setupUri) ?>" aria-label="<?= e(t('sec.qr_alt')) ?>"></div>
            <p class="small muted"><?= e(t('sec.totp_manual')) ?></p>
            <p><code class="secret-key"><?= e($setupPretty) ?></code></p>
            <p class="small"><a href="<?= e($setupUri) ?>"><?= icon('external-link') ?> <?= e(t('sec.totp_open_app')) ?></a></p>
          </li>
          <li><?= e(t('sec.totp_step3')) ?></li>
        </ol>
        <form method="post" action="/security/totp/confirm" class="form" autocomplete="off">
          <?= Csrf::field() ?>
          <div class="field">
            <label for="totp_code"><?= e(t('tf.label_totp')) ?></label>
            <input id="totp_code" name="code" type="text" class="code-input" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]*" maxlength="8" placeholder="123456" required>
          </div>
          <?php $pw('pw_totp_on'); ?>
          <div class="actions">
            <button class="btn btn-primary" type="submit"><?= e(t('sec.turn_on')) ?></button>
          </div>
        </form>
        <form method="post" action="/security/totp/cancel" class="inline-form"><?= Csrf::field() ?><button class="btn btn-link btn-sm" type="submit"><?= e(t('ui.cancel')) ?></button></form>
        <script src="/assets/js/qr.js" defer></script>
      <?php elseif ($totpAllowed): ?>
        <form method="post" action="/security/totp/start"><?= Csrf::field() ?>
          <button class="btn btn-primary btn-sm" type="submit"><?= icon('plus') ?> <?= e(t('sec.set_up')) ?></button></form>
      <?php else: ?>
        <p class="muted small"><?= e(t('sec.not_available')) ?></p>
      <?php endif; ?>
    </div>

    <!-- Email code -->
    <div class="card pad sec-card" id="email">
      <h2 class="h-sm first"><?= icon('mail') ?> <?= e(t('sec.email_title')) ?> <?= $on($hasEmail) ?></h2>
      <p class="muted"><?= e(t('sec.email_d')) ?></p>
      <?php if ($hasEmail): ?>
        <p class="small"><?= e(t('sec.email_sent_to', ['email' => $maskedEmail])) ?></p>
        <form method="post" action="/security/email/disable" class="form" data-confirm="<?= e(t('sec.confirm_off')) ?>">
          <?= Csrf::field() ?>
          <?php $pw('pw_email_off'); ?>
          <div class="actions"><button class="btn btn-danger btn-sm" type="submit"><?= e(t('sec.turn_off')) ?></button></div>
        </form>
      <?php elseif ($emailSetup): ?>
        <p class="small"><?= e(t('sec.email_confirm_p', ['email' => $maskedEmail])) ?></p>
        <form method="post" action="/security/email/confirm" class="form" autocomplete="off">
          <?= Csrf::field() ?>
          <div class="field">
            <label for="email_code"><?= e(t('tf.label_email')) ?></label>
            <input id="email_code" name="code" type="text" class="code-input" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]*" maxlength="8" placeholder="123456" required>
          </div>
          <?php $pw('pw_email_on'); ?>
          <div class="actions"><button class="btn btn-primary" type="submit"><?= e(t('sec.turn_on')) ?></button></div>
        </form>
        <div class="actions">
          <form method="post" action="/security/email/start" class="inline-form"><?= Csrf::field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= icon('send') ?> <?= e(t('tf.send_again')) ?></button></form>
          <form method="post" action="/security/email/cancel" class="inline-form"><?= Csrf::field() ?><button class="btn btn-link btn-sm" type="submit"><?= e(t('ui.cancel')) ?></button></form>
        </div>
      <?php elseif ($emailAllowed && $emailVerified): ?>
        <form method="post" action="/security/email/start"><?= Csrf::field() ?>
          <button class="btn btn-primary btn-sm" type="submit"><?= icon('plus') ?> <?= e(t('sec.set_up')) ?></button></form>
      <?php elseif ($emailAllowed): ?>
        <p class="muted small"><?= e(t('sec.need_verified')) ?></p>
      <?php else: ?>
        <p class="muted small"><?= e(t('sec.not_available')) ?></p>
      <?php endif; ?>
    </div>

    <!-- Backup codes -->
    <?php if ($hasTotp || $hasEmail): ?>
    <div class="card pad sec-card" id="backup">
      <h2 class="h-sm first"><?= icon('key-round') ?> <?= e(t('sec.backup_title')) ?></h2>
      <p class="muted"><?= e(t('sec.backup_d')) ?></p>
      <p class="small"><strong><?= e(t('sec.backup_left', ['n' => $backupLeft])) ?></strong></p>
      <form method="post" action="/security/backup" class="form" data-confirm="<?= e(t('sec.confirm_backup')) ?>">
        <?= Csrf::field() ?>
        <?php $pw('pw_backup'); ?>
        <div class="actions"><button class="btn btn-ghost btn-sm" type="submit"><?= icon('refresh') ?> <?= e(t('sec.backup_new')) ?></button></div>
      </form>
    </div>
    <?php endif; ?>

    <!-- Trusted browsers -->
    <?php if ($trusted): ?>
    <div class="card pad sec-card" id="trusted">
      <h2 class="h-sm first"><?= icon('laptop') ?> <?= e(t('sec.trusted_title')) ?></h2>
      <p class="muted"><?= e(t('sec.trusted_d', ['days' => $trustDays])) ?></p>
      <ul class="dev-list">
        <?php foreach ($trusted as $d): ?>
          <li>
            <div><strong><?= e($d['label'] !== '' ? $d['label'] : '—') ?></strong> <?= !empty($d['current']) ? '<span class="badge badge-ok">' . e(t('sec.this_device')) . '</span>' : '' ?>
              <div class="muted small"><?= e(t('sec.last_used', ['when' => time_ago($d['last_used_at'])])) ?> · <?= e($d['ip']) ?> · <?= e(t('sec.until', ['d' => fmt_date($d['expires_at'])])) ?></div></div>
            <form method="post" action="/security/trusted/<?= (int) $d['id'] ?>/revoke"><?= Csrf::field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= e(t('sec.remove')) ?></button></form>
          </li>
        <?php endforeach; ?>
      </ul>
      <form method="post" action="/security/trusted/all"><?= Csrf::field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= e(t('sec.remove_all')) ?></button></form>
    </div>
    <?php endif; ?>

    <!-- Signed-in devices -->
    <div class="card pad sec-card" id="devices">
      <h2 class="h-sm first"><?= icon('monitor') ?> <?= e(t('sec.sessions_title')) ?></h2>
      <p class="muted"><?= e(t('sec.sessions_d')) ?></p>
      <ul class="dev-list">
        <?php foreach ($sessions as $s): ?>
          <li>
            <div><strong><?= e($s['label'] !== '' ? $s['label'] : '—') ?></strong> <?= !empty($s['current']) ? '<span class="badge badge-ok">' . e(t('sec.this_device')) . '</span>' : '' ?>
              <div class="muted small"><?= e(t('sec.active', ['when' => time_ago($s['last_seen_at'])])) ?> · <?= e($s['ip']) ?> · <?= e(t('sec.signed_in', ['d' => fmt_dt($s['created_at'])])) ?></div></div>
            <?php if (empty($s['current'])): ?>
              <form method="post" action="/security/sessions/<?= (int) $s['id'] ?>/revoke"><?= Csrf::field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= icon('log-out') ?> <?= e(t('sec.sign_out')) ?></button></form>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if (count($sessions) > 1): ?>
        <form method="post" action="/security/sessions/others" data-confirm="<?= e(t('sec.confirm_others')) ?>"><?= Csrf::field() ?>
          <button class="btn btn-danger btn-sm" type="submit"><?= icon('log-out') ?> <?= e(t('sec.sign_out_others')) ?></button></form>
      <?php endif; ?>
    </div>

    <p class="small"><a href="/account/password"><?= icon('key-round') ?> <?= e(t('pw.change_title')) ?></a></p>
  </div>
</section>
