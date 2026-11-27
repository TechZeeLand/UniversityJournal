<?php /** @var bool $valid @var string $token @var array $errors */
$err = static fn(string $k) => isset($errors[$k]) ? '<p class="field-error" role="alert">' . e($errors[$k]) . '</p>' : '';
?>
<section class="section">
  <div class="wrap auth-wrap">
    <div class="card auth-card">
      <span class="card-icon"><?= icon('key-round') ?></span>
      <h1 class="h-md"><?= e(t('email.reset_title')) ?></h1>
      <?php if (!$valid): ?>
        <div class="alert alert-error" role="alert"><?= icon('circle-alert') ?><span><?= e(t('email.reset_bad')) ?></span></div>
        <p><a class="btn btn-primary" href="/forgot"><?= e(t('email.reset_again')) ?></a></p>
      <?php else: ?>
        <p class="muted"><?= e(t('email.reset_sub')) ?></p>
        <?php if (!empty($errors['form'])): ?><div class="alert alert-error" role="alert"><?= icon('circle-alert') ?><span><?= e($errors['form']) ?></span></div><?php endif; ?>
        <form method="post" action="/reset/<?= e($token) ?>" class="form" novalidate>
          <?= \App\Core\Csrf::field() ?>
          <div class="field"><label for="password"><?= e(t('pw.new')) ?></label>
            <div class="pw-wrap"><input id="password" name="password" type="password" autocomplete="new-password" required autofocus>
              <button class="icon-btn" type="button" data-toggle-pw="password" aria-label="<?= e(t('auth.show')) ?>"><?= icon('eye') ?></button></div><?= $err('password') ?>
            <p class="field-hint"><?= e(t('pw.hint', ['n' => \App\Core\Password::minLength()])) ?></p></div>
          <div class="field"><label for="confirm"><?= e(t('pw.confirm')) ?></label><input id="confirm" name="confirm" type="password" autocomplete="new-password" required><?= $err('confirm') ?></div>
          <button class="btn btn-primary" type="submit"><?= e(t('email.reset_submit')) ?></button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</section>
