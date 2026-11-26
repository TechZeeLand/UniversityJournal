<?php /** @var array $actor @var array $a @var string $html */ use App\Core\Csrf; use App\Services\Articles; $st = $a['status']; ?>
<section class="section">
  <div class="wrap">
    <h1 class="h-md"><?= e(t('portal.mod_title')) ?></h1>
    <?php \App\Core\View::partial('portal/_nav', ['active' => 'articles', 'actor' => $actor]); ?>
    <p><a href="/moderator/articles?status=<?= e($st) ?>">‹ <?= e(t('review.title')) ?></a></p>
    <div class="card pad">
      <p class="kicker"><?= e(Articles::typeName($a)) ?> · <?= e(t('explore.lang_' . $a['lang'])) ?> · <span class="status-pill"><?= e(t('art.status_' . $st)) ?></span></p>
      <h2 class="h-md"<?= art_attrs($a) ?>><?= e($a['title']) ?></h2>
      <p class="muted"><?= e(display_name($a)) ?> (<?= e(role_label($a['role'])) ?>) · <?= e(t('art.min_read', ['n' => num((int) $a['reading_minutes'])])) ?><?= $a['submitted_at'] ? ' · ' . e(fmt_dt($a['submitted_at'])) : '' ?></p>
      <?php if (trim((string) $a['summary']) !== ''): ?><p class="lead"<?= art_attrs($a) ?>><?= e($a['summary']) ?></p><?php endif; ?>
      <?php if (!empty($a['cover_path'])): ?><div class="asset-preview"><?= cover_html($a) ?></div><?php endif; ?>
      <div class="prose"<?= art_attrs($a) ?>><?= $html ?></div>
    </div>

    <form method="post" action="/moderator/articles/<?= (int) $a['id'] ?>/review" class="form card pad">
      <?= Csrf::field() ?>
      <h2 class="h-sm first"><?= e(t('review.decision')) ?></h2>
      <?php if (in_array($st, ['submitted', 'changes_requested', 'archived'], true)): ?>
        <div class="field"><label for="label"><?= e(t('review.label')) ?></label>
          <select id="label" name="label"><?php foreach (Articles::LABELS as $l): ?><option value="<?= $l ?>"<?= $a['review_label'] === $l ? ' selected' : '' ?>><?= e(t('art.label_' . $l)) ?></option><?php endforeach; ?></select>
          <p class="field-hint"><?= e(t('review.label_hint')) ?></p></div>
      <?php endif; ?>
      <?php if (in_array($st, ['submitted', 'changes_requested'], true)): ?>
        <div class="field"><label for="note"><?= e(t('review.note')) ?></label>
          <textarea id="note" name="note" rows="4" maxlength="1500"><?= e((string) $a['review_note']) ?></textarea>
          <p class="field-hint"><?= e(t('review.note_hint')) ?></p></div>
      <?php endif; ?>
      <div class="actions">
        <?php if (in_array($st, ['submitted', 'changes_requested', 'archived'], true)): ?><button class="btn btn-primary" name="action" value="publish" type="submit"><?= icon('circle-check') ?> <?= e(t('review.publish')) ?></button><?php endif; ?>
        <?php if ($st === 'submitted'): ?><button class="btn btn-ghost" name="action" value="changes" type="submit"><?= icon('pencil') ?> <?= e(t('review.changes')) ?></button><?php endif; ?>
        <?php if (in_array($st, ['submitted', 'changes_requested'], true)): ?><button class="btn btn-danger" name="action" value="reject" type="submit" data-confirm="<?= e(t('review.confirm_reject')) ?>"><?= e(t('review.reject')) ?></button><?php endif; ?>
        <?php if ($st === 'published'): ?><button class="btn btn-ghost" name="action" value="archive" type="submit" data-confirm="<?= e(t('review.confirm_archive')) ?>"><?= icon('archive') ?> <?= e(t('review.archive')) ?></button>
          <a class="btn btn-ghost" href="<?= e(Articles::url($a)) ?>"><?= e(t('review.view_live')) ?></a><?php endif; ?>
      </div>
    </form>

    <?php if ($st === 'published'): ?>
      <form method="post" action="/moderator/articles/<?= (int) $a['id'] ?>/feature" class="form card pad">
        <?= Csrf::field() ?><input type="hidden" name="on" value="<?= $a['is_featured'] ? '0' : '1' ?>">
        <p><?= e(t('review.feature_d')) ?></p>
        <button class="btn btn-ghost" type="submit"><?= icon('flame') ?> <?= e(t($a['is_featured'] ? 'review.unfeature' : 'review.feature')) ?></button>
      </form>
    <?php endif; ?>
  </div>
</section>
