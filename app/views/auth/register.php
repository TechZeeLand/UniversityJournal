<?php
/** @var array $errors @var array $old @var string $next */
use App\Services\{GoogleAuth, Readers};
$err = static fn(string $k) => isset($errors[$k]) ? '<p class="field-error" role="alert">' . e($errors[$k]) . '</p>' : '';
$nq = $next !== '' ? '?next=' . rawurlencode($next) : '';
?>
<section class="section">
  <div class="wrap auth-wrap">
    <div class="card auth-card">
      <span class="card-icon"><?= icon('user-plus') ?></span>
      <h1 class="h-md"><?= e(t('reader.create')) ?></h1>
      <p class="muted"><?= e(t('reader.create_sub')) ?></p>
      <?php if (!empty($errors['form'])): ?><div class="alert alert-error" role="alert"><?= icon('circle-alert') ?><span><?= e($errors['form']) ?></span></div><?php endif; ?>

      <?php if (Readers::googleOpen()): ?>
        <a class="btn-google" href="/auth/google<?= e($nq) ?>">
          <svg viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.8 2.4 30.3 0 24 0 14.6 0 6.5 5.4 2.6 13.2l7.9 6.1C12.4 13.6 17.7 9.5 24 9.5z"/><path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.6 3-2.3 5.5-4.8 7.2l7.6 5.9c4.4-4.1 7-10.1 7-17.6z"/><path fill="#FBBC05" d="M10.5 28.7a14.5 14.5 0 0 1 0-9.4l-7.9-6.1a24 24 0 0 0 0 21.6l7.9-6.1z"/><path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.6-5.9c-2.1 1.4-4.9 2.3-8.3 2.3-6.3 0-11.6-4.1-13.5-9.9l-7.9 6.1C6.5 42.6 14.6 48 24 48z"/></svg>
          <?= e(t('reader.google')) ?></a>
        <?php if (Readers::emailOpen()): ?><div class="divider"><?= e(t('reader.or')) ?></div><?php endif; ?>
      <?php endif; ?>

      <?php if (Readers::emailOpen()): ?>
      <form method="post" action="/register" class="form" novalidate>
        <?= \App\Core\Csrf::field() ?><input type="hidden" name="next" value="<?= e($next) ?>">
        <div class="field" style="position:absolute;left:-9999px" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="field"><label for="name"><?= e(t('reader.name')) ?></label><input id="name" name="name" type="text" maxlength="80" value="<?= e($old['name']) ?>" autocomplete="name" required><?= $err('name') ?></div>
        <div class="field"><label for="email"><?= e(t('reader.email')) ?></label><input id="email" name="email" type="email" maxlength="190" value="<?= e($old['email']) ?>" autocomplete="email" required><?= $err('email') ?></div>
        <div class="field"><label for="password"><?= e(t('auth.password')) ?></label>
          <div class="pw-wrap"><input id="password" name="password" type="password" autocomplete="new-password" required>
            <button class="icon-btn" type="button" data-toggle-pw="password" aria-label="<?= e(t('auth.show')) ?>"><?= icon('eye') ?></button></div><?= $err('password') ?></div>
        <div class="field"><label for="confirm"><?= e(t('pw.confirm')) ?></label><input id="confirm" name="confirm" type="password" autocomplete="new-password" required><?= $err('confirm') ?></div>
        <button class="btn btn-primary" type="submit"><?= e(t('reader.create')) ?></button>
      </form>
      <?php endif; ?>
      <p class="muted small"><?= e(t('reader.terms_note')) ?> <a href="/terms"><?= e(t('nav.terms')) ?></a> · <a href="/privacy"><?= e(t('nav.privacy')) ?></a></p>
      <p class="small"><?= e(t('reader.have_account')) ?> <a href="/login<?= e($nq) ?>"><?= e(t('nav.login')) ?></a></p>
    </div>
  </div>
</section>
