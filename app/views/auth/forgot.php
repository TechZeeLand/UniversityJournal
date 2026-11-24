<section class="section">
  <div class="wrap auth-wrap">
    <div class="card auth-card">
      <span class="card-icon"><?= icon('key-round') ?></span>
      <h1 class="h-md"><?= e(t('auth.forgot_title')) ?></h1>
      <?php if ($done): ?>
        <div class="alert alert-ok" role="status"><?= icon('circle-check') ?><span><?= e(t('auth.forgot_done')) ?></span></div>
        <a class="btn btn-ghost" href="/login"><?= e(t('auth.back_login')) ?></a>
      <?php else: ?>
        <p class="muted"><?= e(t('auth.forgot_sub')) ?></p>
        <?php if (!empty($error)): ?>
          <div class="alert alert-error" role="alert"><?= icon('circle-alert') ?><span><?= e($error) ?></span></div>
        <?php endif; ?>
        <form method="post" action="/forgot" class="form">
          <?= \App\Core\Csrf::field() ?>
          <div class="field">
            <label for="uid"><?= e(t('auth.uid')) ?></label>
            <input id="uid" name="uid" type="text" value="<?= e($uid) ?>" autocomplete="username" autocapitalize="none" required autofocus>
          </div>
          <div class="field">
            <label for="contact"><?= e(t('auth.forgot_contact')) ?></label>
            <input id="contact" name="contact" type="text" maxlength="190">
          </div>
          <div class="field">
            <label for="note"><?= e(t('auth.forgot_note')) ?></label>
            <textarea id="note" name="note" maxlength="500" rows="3"></textarea>
          </div>
          <button class="btn btn-primary" type="submit"><?= e(t('auth.forgot_submit')) ?></button>
        </form>
        <p class="small"><a href="/login"><?= e(t('auth.back_login')) ?></a></p>
      <?php endif; ?>
    </div>
  </div>
</section>
