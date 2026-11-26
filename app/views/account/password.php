<section class="section">
  <div class="wrap auth-wrap">
    <div class="card auth-card">
      <span class="card-icon"><?= icon('key-round') ?></span>
      <h1 class="h-md"><?= e(t('pw.change_title')) ?></h1>
      <?php if ($forced): ?>
        <div class="alert alert-warn" role="status"><?= icon('triangle-alert') ?><span><?= e(t('pw.forced')) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($error)): ?>
        <div class="alert alert-error" role="alert"><?= icon('circle-alert') ?><span><?= e($error) ?></span></div>
      <?php endif; ?>
      <form method="post" action="/account/password" class="form">
        <?= \App\Core\Csrf::field() ?>
        <?php if ($hasPw ?? true): ?>
        <div class="field">
          <label for="current"><?= e(t('pw.current')) ?></label>
          <input id="current" name="current" type="password" autocomplete="current-password" required autofocus>
        </div>
        <?php endif; ?>
        <div class="field">
          <label for="new"><?= e(t('pw.new')) ?></label>
          <div class="pw-wrap">
            <input id="new" name="new" type="password" autocomplete="new-password" required minlength="<?= (int) $min ?>">
            <button class="icon-btn" type="button" data-toggle-pw="new" aria-label="<?= e(t('auth.show')) ?>"><?= icon('eye') ?></button>
          </div>
          <p class="field-hint"><?= e(t('pw.hint', ['n' => $min])) ?></p>
        </div>
        <div class="field">
          <label for="confirm"><?= e(t('pw.confirm')) ?></label>
          <input id="confirm" name="confirm" type="password" autocomplete="new-password" required>
        </div>
        <button class="btn btn-primary" type="submit"><?= e(t('pw.submit')) ?></button>
      </form>
    </div>
  </div>
</section>
