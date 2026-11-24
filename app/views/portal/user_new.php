<section class="section">
  <div class="wrap narrow-md">
    <h1 class="h-md"><?= e(t('users.new')) ?></h1>
    <?php \App\Core\View::partial('portal/_nav', ['active' => 'users', 'actor' => $actor]); ?>
    <?php if (!empty($error)): ?><div class="alert alert-error" role="alert"><?= icon('circle-alert') ?><span><?= e($error) ?></span></div><?php endif; ?>
    <p class="muted"><?= e(t('users.new_note')) ?></p>
    <form method="post" action="/moderator/users" class="form card pad">
      <?= \App\Core\Csrf::field() ?>
      <?php \App\Core\View::partial('portal/_user_fields', ['old' => $old, 'roles' => $roles, 'departments' => $departments]); ?>
      <div class="actions"><button class="btn btn-primary" type="submit"><?= icon('user-plus') ?> <?= e(t('users.create')) ?></button>
        <a class="btn btn-ghost" href="/moderator/users"><?= e(t('ui.cancel')) ?></a></div>
    </form>
  </div>
</section>
