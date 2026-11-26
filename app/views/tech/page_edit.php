<?php /** @var ?array $page; @var array $old, $errors */
use App\Core\Csrf;
$isNew = $page === null; $system = !$isNew && (int) $page['is_system'] === 1;
$fe = fn(string $k) => empty($errors[$k]) ? '' : '<p class="field-error" role="alert">' . e($errors[$k]) . '</p>';
?>
<section class="section">
  <div class="wrap narrow-md">
    <h1 class="h-md"><?= e(t('portal.tech_title')) ?></h1>
    <?php \App\Core\View::partial('portal/_nav', ['active' => 'content', 'actor' => $actor]); ?>
    <?php \App\Core\View::partial('tech/_content_nav', ['active' => 'pages']); ?>
    <p><a href="/tech/pages">‹ <?= e(t('pages.title')) ?></a></p>
    <form method="post" action="<?= $isNew ? '/tech/pages' : '/tech/pages/' . (int) $page['id'] ?>" class="form card pad" novalidate>
      <?= Csrf::field() ?>
      <h2 class="h-sm first"><?= e(t($isNew ? 'pages.new' : 'pages.edit')) ?></h2>
      <div class="row">
        <div class="field">
          <label for="slug"><?= e(t('pages.th_address')) ?></label>
          <input id="slug" name="slug" type="text" maxlength="60" value="<?= e($old['slug'] ?? '') ?>"<?= $system ? ' disabled' : '' ?> autocapitalize="none" spellcheck="false">
          <p class="field-hint"><?= e(t($system ? 'pages.slug_locked' : 'pages.slug_hint')) ?></p><?= $fe('slug') ?>
        </div>
        <div class="field">
          <label for="status"><?= e(t('users.th_status')) ?></label>
          <select id="status" name="status">
            <?php foreach (['published', 'draft'] as $s): ?><option value="<?= $s ?>"<?= ($old['status'] ?? 'published') === $s ? ' selected' : '' ?>><?= e(t('pages.status_' . $s)) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="row">
        <div class="field"><label for="title_en"><?= e(t('pages.title_en')) ?> <span class="req">*</span></label>
          <input id="title_en" name="title_en" type="text" maxlength="190" value="<?= e($old['title_en'] ?? '') ?>"><?= $fe('title_en') ?></div>
        <div class="field"><label for="title_bn"><?= e(t('pages.title_bn')) ?></label>
          <input id="title_bn" name="title_bn" type="text" maxlength="190" lang="bn" value="<?= e($old['title_bn'] ?? '') ?>"></div>
      </div>
      <div class="field"><label for="body_en"><?= e(t('pages.body_en')) ?></label>
        <textarea id="body_en" name="body_en" rows="16" class="mono"><?= e($old['body_en'] ?? '') ?></textarea></div>
      <div class="field"><label for="body_bn"><?= e(t('pages.body_bn')) ?></label>
        <textarea id="body_bn" name="body_bn" rows="16" lang="bn" class="mono"><?= e($old['body_bn'] ?? '') ?></textarea>
        <p class="field-hint"><?= e(t('pages.body_bn_hint')) ?></p></div>
      <details class="help"><summary><?= e(t('pages.help_title')) ?></summary><p class="small"><?= e(t('pages.help')) ?></p><p class="small"><?= e(t('pages.help_tokens')) ?></p></details>
      <div class="actions">
        <button class="btn btn-primary" type="submit"><?= e(t('ui.save')) ?></button>
        <?php if (!$isNew && $page['status'] === 'published'): ?><a class="btn btn-ghost" href="/<?= e($page['slug']) ?>" target="_blank" rel="noopener"><?= icon('external-link') ?> <?= e(t('pages.view')) ?></a><?php endif; ?>
      </div>
    </form>
    <?php if (!$isNew && !$system): ?>
      <form method="post" action="/tech/pages/<?= (int) $page['id'] ?>/delete" class="card pad danger-zone" data-confirm="<?= e(t('pages.confirm_delete')) ?>">
        <?= Csrf::field() ?><button class="btn btn-danger" type="submit"><?= icon('trash-2') ?> <?= e(t('pages.delete')) ?></button>
      </form>
    <?php endif; ?>
  </div>
</section>
