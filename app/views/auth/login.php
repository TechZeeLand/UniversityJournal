<section class="section">
  <div class="wrap auth-wrap">
    <div class="card auth-card">
      <span class="card-icon"><?= icon('log-in') ?></span>
      <h1 class="h-md"><?= e(t('auth.login_title')) ?></h1>
      <p class="muted"><?= e(\App\Core\Identity::t('auth.login_sub')) ?></p>
      <?php if (!empty($error)): ?>
        <div class="alert alert-error" role="alert"><?= icon('circle-alert') ?><span><?= e($error) ?></span></div>
      <?php endif; ?>
      <form method="post" action="/login" class="form">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <div class="field">
          <label for="uid"><?= e(\App\Core\Identity::label()) ?><?= \App\Services\Readers::signupOpen() ? e(' / ' . t('reader.email')) : '' ?></label>
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
      <?php if (\App\Services\Readers::signupOpen()): $nq = $next !== '' ? '?next=' . rawurlencode($next) : ''; ?>
        <div class="divider"><?= e(t('reader.or')) ?></div>
        <?php if (\App\Services\Readers::googleOpen()): ?>
          <a class="btn-google" href="/auth/google<?= e($nq) ?>"><svg viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.8 2.4 30.3 0 24 0 14.6 0 6.5 5.4 2.6 13.2l7.9 6.1C12.4 13.6 17.7 9.5 24 9.5z"/><path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.6 3-2.3 5.5-4.8 7.2l7.6 5.9c4.4-4.1 7-10.1 7-17.6z"/><path fill="#FBBC05" d="M10.5 28.7a14.5 14.5 0 0 1 0-9.4l-7.9-6.1a24 24 0 0 0 0 21.6l7.9-6.1z"/><path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.6-5.9c-2.1 1.4-4.9 2.3-8.3 2.3-6.3 0-11.6-4.1-13.5-9.9l-7.9 6.1C6.5 42.6 14.6 48 24 48z"/></svg> <?= e(t('reader.google')) ?></a>
        <?php endif; ?>
        <p class="small center"><?= e(t('reader.no_account')) ?> <a href="/register<?= e($nq) ?>"><?= e(t('reader.create')) ?></a></p>
      <?php endif; ?>
      <p class="muted small"><?= e(t('auth.accounts_note')) ?></p>
    </div>
  </div>
</section>
