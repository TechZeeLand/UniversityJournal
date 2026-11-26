<?php /** @var array $rows @var bool $complete */ use App\Core\{Csrf, DB}; use App\Services\Articles; ?>
<section class="section">
  <div class="wrap">
    <div class="toolbar">
      <h1 class="h-md"><?= e(t('write.my')) ?></h1>
      <div class="actions"><a class="btn btn-primary" href="/write/new"><?= icon('square-pen') ?> <?= e(t('write.new')) ?></a></div>
    </div>
    <?php if (!$complete): ?>
      <div class="alert alert-warn" role="status"><?= icon('triangle-alert') ?><span><?= e(t('write.profile_incomplete')) ?> <a href="/profile"><?= e(t('dash.complete_profile')) ?></a></span></div>
    <?php endif; ?>
    <?php if (!$rows): ?>
      <div class="empty card pad center"><span class="card-icon big"><?= icon('pencil') ?></span><h3><?= e(t('write.empty_t')) ?></h3><p class="muted"><?= e(t('write.empty_d')) ?></p></div>
    <?php else: ?>
      <div class="table-wrap"><table class="tbl">
        <thead><tr><th><?= e(t('write.th_title')) ?></th><th><?= e(t('write.th_status')) ?></th><th><?= e(t('write.th_stats')) ?></th><th><?= e(t('write.th_updated')) ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $a): $st = $a['status']; $editable = in_array($st, ['draft', 'changes_requested', 'rejected'], true); ?>
          <tr>
            <td><a href="<?= e(Articles::url($a)) ?>"<?= art_attrs($a) ?>><?= e($a['title']) ?></a></td>
            <td><span class="badge <?= $st === 'published' ? 'badge-ok' : ($st === 'rejected' ? 'badge-danger' : ($st === 'draft' ? '' : 'badge-warn')) ?>"><?= e(t('art.status_' . $st)) ?></span></td>
            <td class="muted small"><?= icon('eye') ?> <?= e(num((int) $a['view_count'])) ?> · <?= icon('thumbs-up') ?> <?= e(num((int) $a['like_count'])) ?> · <?= icon('message-square') ?> <?= e(num((int) $a['comment_count'])) ?></td>
            <td class="muted small"><?= e(fmt_dt($a['updated_at'])) ?></td>
            <td class="actions-cell">
              <?php if ($editable): ?><a class="btn btn-ghost btn-sm" href="/write/<?= (int) $a['id'] ?>"><?= e(t('write.edit')) ?></a>
                <form method="post" action="/write/<?= (int) $a['id'] ?>/delete" class="inline" data-confirm="<?= e(t('write.confirm_delete')) ?>"><?= Csrf::field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= icon('trash-2') ?></button></form>
              <?php elseif ($st === 'submitted'): ?>
                <form method="post" action="/write/<?= (int) $a['id'] ?>/withdraw" class="inline"><?= Csrf::field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= e(t('write.withdraw')) ?></button></form>
              <?php endif; ?>
            </td>
          </tr>
          <?php if ($a['status'] === 'changes_requested' || $a['status'] === 'rejected'): $n = DB::val('SELECT review_note FROM articles WHERE id = ?', [(int) $a['id']]); if ($n): ?>
            <tr><td colspan="5"><div class="review-note"><strong><?= e(t('write.reviewer_note')) ?>:</strong> <?= nl2br(e((string) $n)) ?></div></td></tr>
          <?php endif; endif; ?>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
  </div>
</section>
