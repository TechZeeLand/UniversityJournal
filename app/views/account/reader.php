<?php /** @var array $u @var array $p @var ?string $error @var int $nComments @var int $nLikes */ use App\Core\Csrf; ?>
<section class="section">
  <div class="wrap narrow">
    <div class="who"><?= avatar(array_merge($u, $p), 'avatar-xl') ?><div><h1 class="h-md"><?= e(t('reader.my_account')) ?></h1><p class="muted"><?= e((string) $u['email']) ?> · <?= e(role_label('reader')) ?></p></div></div>
    <p class="stats-line"><span><?= icon('message-square') ?> <?= e(t('reader.n_comments', ['n' => num($nComments)])) ?></span><span><?= icon('thumbs-up') ?> <?= e(t('reader.n_likes', ['n' => num($nLikes)])) ?></span></p>
    <?php \App\Core\View::partial('account/_email_status', ['u' => $u]); ?>
    <?php if ($error): ?><div class="alert alert-error" role="alert"><?= icon('circle-alert') ?><span><?= e($error) ?></span></div><?php endif; ?>
    <form method="post" action="/account" enctype="multipart/form-data" class="form card pad">
      <?= Csrf::field() ?>
      <div class="field"><label for="full_name"><?= e(t('reader.name')) ?></label><input id="full_name" name="full_name" type="text" maxlength="80" value="<?= e((string) ($p['full_name'] ?? '')) ?>" required></div>
      <div class="field"><span class="label"><?= e(t('reader.photo')) ?></span><input type="file" name="photo" accept="image/jpeg,image/png,image/webp,image/gif">
        <?php if (!empty($p['photo_path'])): ?><label class="check"><input type="checkbox" name="remove_photo" value="1"> <?= e(t('reader.remove_photo')) ?></label><?php endif; ?></div>
      <div class="field"><label class="check"><input type="checkbox" name="email_notify" value="1"<?= (int) ($u['email_notify'] ?? 1) === 1 ? ' checked' : '' ?>> <?= e(t('email.notify_opt')) ?></label>
        <p class="field-hint"><?= e(t('email.notify_opt_d')) ?></p></div>
      <div class="actions"><button class="btn btn-primary" type="submit"><?= e(t('ui.save')) ?></button>
        <a class="btn btn-ghost" href="/account/password"><?= icon('key-round') ?> <?= e(empty($u['has_password']) && $u['has_password'] !== null ? t('reader.set_password') : t('pw.change_title')) ?></a></div>
    </form>
  </div>
</section>
