<section class="section">
  <div class="wrap auth-wrap">
    <div class="card auth-card">
      <span class="card-icon"><?= icon('log-in') ?></span>
      <h1 class="h-md"><?= e(t('auth.login_title')) ?></h1>
      <p class="muted"><?= e(t('auth.login_sub')) ?></p>
      <?php if (!empty($error)): ?>
        <div class="alert alert-error" role="alert"><?= icon('circle-alert') ?><span><?= e($error) ?></span></div>
      <?php endif; ?>
      <form method="post" action="/login" class="form">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <div class="field">
          <label for="uid"><?= e(t('auth.uid')) ?></label>
          <input id="uid" name="uid" type="text" value="<?= e($uid) ?>" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus>
        </div>
        <div class="field">
          <label for="password"><?= e(t('auth.password')) ?></label>
          <div class="pw-wrap">
            <input id="password" name="password" type="password" autocomplete="current-password" required>
            <button class="icon-btn" type="button" data-toggle-pw="password" aria-label="<?= e(t('auth.show')) ?>"><?= icon('eye') ?></button>
          </div>
        </div>
        <button class="btn btn-primary" type="submit"><?= e(t('auth.submit')) ?></button>
      </form>
      <p class="small"><a href="/forgot"><?= e(t('auth.forgot')) ?></a></p>
      <p class="muted small"><?= e(t('auth.accounts_note')) ?></p>
    </div>
  </div>
</section>
