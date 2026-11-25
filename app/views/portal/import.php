<section class="section">
  <div class="wrap narrow-md">
    <h1 class="h-md"><?= e(t('import.title')) ?></h1>
    <?php \App\Core\View::partial('portal/_nav', ['active' => 'users', 'actor' => $actor]); ?>
    <p class="muted"><?= e(t('import.intro', ['n' => $limit])) ?></p>
    <ul class="muted small">
      <li><?= e(t('import.cols_required')) ?> <?= \App\Core\Identity::sequential() ? '' : '<code>uid</code>, ' ?><code>full_name</code></li>
      <li><?= e(t('import.cols_optional')) ?> <?= \App\Core\Identity::sequential() ? '<code>uid</code>, ' : '' ?><code>full_name_bn</code>, <code>role</code>, <code>department</code>, <code>academic_session</code>, <code>designation</code>, <code>email</code></li>
      <li><?= e(t('import.dept_note')) ?></li>
      <li><?= e(t('import.pw_note')) ?></li>
      <?php if (\App\Core\Identity::sequential()): ?><li><?= e(t('import.seq_note')) ?></li><?php endif; ?>
    </ul>
    <p><a class="btn btn-ghost btn-sm" href="/moderator/users/import/template.csv"><?= icon('download') ?> <?= e(t('import.template')) ?></a></p>
    <form method="post" action="/moderator/users/import" enctype="multipart/form-data" class="form card pad">
      <?= \App\Core\Csrf::field() ?>
      <div class="field">
        <label for="csv"><?= e(t('import.file')) ?></label>
        <input id="csv" name="csv" type="file" accept=".csv,text/csv,text/plain" required>
      </div>
      <div class="field">
        <label for="default_role"><?= e(t('import.default_role')) ?></label>
        <select id="default_role" name="default_role">
          <?php foreach ($roles as $r): ?><option value="<?= e($r) ?>"><?= e(role_label($r)) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="actions"><button class="btn btn-primary" type="submit"><?= icon('upload') ?> <?= e(t('users.import')) ?></button></div>
    </form>
  </div>
</section>
