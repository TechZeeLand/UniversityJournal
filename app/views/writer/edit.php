<?php
/** @var array $a @var array $errors @var array $types @var array $departments */
use App\Core\Csrf; use App\Services\Articles;
$err = static fn(string $k) => isset($errors[$k]) ? '<p class="field-error" role="alert">' . e($errors[$k]) . '</p>' : '';
$action = $a['id'] ? '/write/' . (int) $a['id'] : '/write';
?>
<section class="section">
  <div class="wrap">
    <p><a href="/write">‹ <?= e(t('write.my')) ?></a></p>
    <h1 class="h-md"><?= e($a['id'] ? t('write.edit') : t('write.new')) ?></h1>
    <?php if (!empty($a['review_note']) && $a['id']): ?><div class="review-note"><strong><?= e(t('write.reviewer_note')) ?>:</strong> <?= nl2br(e((string) $a['review_note'])) ?></div><?php endif; ?>
    <?php if (!empty($errors['profile'])): ?><div class="alert alert-warn" role="alert"><?= icon('triangle-alert') ?><span><?= e($errors['profile']) ?> <a href="/profile"><?= e(t('dash.complete_profile')) ?></a></span></div><?php endif; ?>

    <form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="form editor" data-editor data-article-id="<?= (int) $a['id'] ?>" data-autosave="<?= (int) setting('autosave_seconds', '8') ?>" data-s-saving="<?= e(t('write.autosaving')) ?>" data-s-saved="<?= e(t('write.autosaved')) ?>" data-s-fail="<?= e(t('write.autosave_fail')) ?>" novalidate>
      <?= Csrf::field() ?>
      <div class="field"><label for="title"><?= e(t('write.title')) ?> <span class="req">*</span></label>
        <input id="title" name="title" type="text" maxlength="220" value="<?= e($a['title']) ?>" required<?= art_attrs($a) ?>><?= $err('title') ?></div>
      <div class="field"><label for="summary"><?= e(t('write.summary')) ?></label>
        <textarea id="summary" name="summary" rows="2" maxlength="500"<?= art_attrs($a) ?>><?= e((string) $a['summary']) ?></textarea>
        <p class="field-hint"><?= e(t('write.summary_hint')) ?></p></div>

      <div class="row">
        <div class="field"><label for="type_id"><?= e(t('explore.type')) ?></label>
          <select id="type_id" name="type_id"><option value="0">—</option>
            <?php foreach ($types as $tp): ?><option value="<?= (int) $tp['id'] ?>"<?= (int) $a['type_id'] === (int) $tp['id'] ? ' selected' : '' ?>><?= e(Articles::typeLabel($tp)) ?></option><?php endforeach; ?>
          </select><?= $err('type_id') ?></div>
        <div class="field"><label for="department_id"><?= e(t('profile.f.department_id')) ?></label>
          <select id="department_id" name="department_id"><option value="0">—</option>
            <?php foreach ($departments as $faculty => $list): ?><optgroup label="<?= e($faculty) ?>"><?php foreach ($list as $d): ?><option value="<?= (int) $d['id'] ?>"<?= (int) $a['department_id'] === (int) $d['id'] ? ' selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?>
          </select></div>
        <div class="field"><label for="lang"><?= e(t('explore.lang')) ?></label>
          <select id="lang" name="lang"><?php foreach (Articles::LANGS as $l): ?><option value="<?= $l ?>"<?= $a['lang'] === $l ? ' selected' : '' ?>><?= e(t('explore.lang_' . $l)) ?></option><?php endforeach; ?></select></div>
      </div>

      <div class="field"><span class="label"><?= e(t('write.cover')) ?></span>
        <?php if (!empty($a['cover_path'])): ?><div class="asset-preview"><?= cover_html($a) ?></div>
          <label class="check"><input type="checkbox" name="remove_cover" value="1"> <?= e(t('write.remove_cover')) ?></label><?php endif; ?>
        <input type="file" name="cover" accept="image/jpeg,image/png,image/webp,image/gif"><?= $err('cover') ?>
        <p class="field-hint"><?= e(t('write.cover_hint')) ?></p></div>

      <div class="field">
        <label for="body"><?= e(t('write.body')) ?></label>
        <div class="md-tools" role="toolbar" aria-label="<?= e(t('write.tools')) ?>">
          <button type="button" data-md="h2" title="<?= e(t('write.t_heading')) ?>"><?= icon('heading') ?></button>
          <button type="button" data-md="bold" title="<?= e(t('write.t_bold')) ?>"><?= icon('bold') ?></button>
          <button type="button" data-md="italic" title="<?= e(t('write.t_italic')) ?>"><?= icon('italic') ?></button>
          <button type="button" data-md="quote" title="<?= e(t('write.t_quote')) ?>"><?= icon('quote') ?></button>
          <button type="button" data-md="ul" title="<?= e(t('write.t_list')) ?>"><?= icon('list') ?></button>
          <button type="button" data-md="link" title="<?= e(t('write.t_link')) ?>"><?= icon('link') ?></button>
          <button type="button" data-md="code" title="<?= e(t('write.t_code')) ?>"><?= icon('code') ?></button>
          <label class="icon-btn" title="<?= e(t('write.t_image')) ?>"><?= icon('image') ?><input type="file" accept="image/*" data-md-image hidden></label>
          <button type="button" data-md-split aria-pressed="false"><?= icon('columns') ?> <?= e(t('write.preview')) ?></button>
        </div>
        <div class="editor-split" data-split>
          <textarea id="body" name="body" class="md-body" maxlength="200000"<?= art_attrs($a) ?>><?= e((string) $a['body']) ?></textarea>
          <div class="md-preview prose" data-preview hidden<?= art_attrs($a) ?>></div>
        </div>
        <?= $err('body') ?>
        <p class="field-hint"><?= e(t('write.md_hint')) ?> <span data-save-state></span></p>
      </div>

      <div class="sticky-actions">
        <button class="btn btn-ghost" type="submit" name="action" value="save"><?= icon('save') ?> <?= e(t('write.save_draft')) ?></button>
        <button class="btn btn-primary" type="submit" name="action" value="submit"><?= icon('send') ?> <?= e(t(setting('publish_needs_review', '1') === '1' ? 'write.submit' : 'write.publish')) ?></button>
      </div>
    </form>
  </div>
</section>
