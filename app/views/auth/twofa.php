<?php
/** @var string $method totp|email|backup; @var list<string> $avail; @var ?string $error; @var int $trustDays; @var string $maskedEmail */
use App\Core\Csrf;
$icons = ['totp' => 'smartphone', 'email' => 'mail', 'backup' => 'key-round'];
?>
<section class="section">
  <div class="wrap auth-wrap">
    <div class="card auth-card">
      <span class="card-icon"><?= icon('shield-check') ?></span>
      <h1 class="h-md"><?= e(t('tf.title')) ?></h1>
      <p class="muted"><?= e(t('tf.sub_' . $method, ['email' => $maskedEmail])) ?></p>
      <?php if (!empty($error)): ?>
        <div class="alert alert-error" role="alert"><?= icon('circle-alert') ?><span><?= e($error) ?></span></div>
      <?php endif; ?>

      <?php if ($method === 'email'): ?>
        <form method="post" action="/login/2fa/send" class="inline-form">
          <?= Csrf::field() ?>
          <button class="btn btn-ghost btn-sm" type="submit"><?= icon('send') ?> <?= e(t('tf.send_again')) ?></button>
        </form>
      <?php endif; ?>

      <form method="post" action="/login/2fa" class="form" autocomplete="off">
        <?= Csrf::field() ?>
        <input type="hidden" name="method" value="<?= e($method) ?>">
        <div class="field">
          <label for="code"><?= e(t('tf.label_' . $method)) ?></label>
          <?php if ($method === 'backup'): ?>
            <input id="code" name="code" type="text" class="code-input" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="14" placeholder="XXXXX-XXXXX" required autofocus>
          <?php else: ?>
            <input id="code" name="code" type="text" class="code-input" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]*" maxlength="8" placeholder="123456" required autofocus>
          <?php endif; ?>
        </div>
        <?php if ($trustDays > 0): ?>
          <label class="check"><input type="checkbox" name="trust" value="1"> <?= e(t('tf.trust', ['days' => $trustDays])) ?></label>
        <?php endif; ?>
        <button class="btn btn-primary" type="submit"><?= e(t('tf.verify')) ?></button>
      </form>

      <?php $others = array_values(array_diff($avail, [$method])); if ($others): ?>
        <div class="divider"><?= e(t('tf.or_use')) ?></div>
        <p class="alt-methods">
          <?php foreach ($others as $m): ?>
            <a class="btn btn-ghost btn-sm" href="/login/2fa?m=<?= e($m) ?>"><?= icon($icons[$m]) ?> <?= e(t('tf.use_' . $m)) ?></a>
          <?php endforeach; ?>
        </p>
      <?php endif; ?>
      <?php if ($avail === ['backup'] || !in_array('totp', $avail, true) && !in_array('email', $avail, true)): ?>
        <p class="muted small"><?= e(t('tf.no_method_help')) ?></p>
      <?php endif; ?>

      <form method="post" action="/login/2fa/cancel" class="inline-form">
        <?= Csrf::field() ?>
        <button class="btn btn-link btn-sm" type="submit">‹ <?= e(t('tf.cancel')) ?></button>
      </form>
    </div>
  </div>
</section>
