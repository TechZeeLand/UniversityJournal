<?php /** @var array $actor @var array $rows @var bool $reportedOnly */ use App\Core\Csrf; use App\Services\Articles; ?>
<section class="section">
  <div class="wrap">
    <h1 class="h-md"><?= e(t('portal.mod_title')) ?></h1>
    <?php \App\Core\View::partial('portal/_nav', ['active' => 'comments', 'actor' => $actor]); ?>
    <p class="filter-links"><a class="btn btn-sm <?= $reportedOnly ? 'btn-ghost' : 'btn-primary' ?>" href="/moderator/comments"><?= e(t('bm.all_comments')) ?></a>
      <a class="btn btn-sm <?= $reportedOnly ? 'btn-primary' : 'btn-ghost' ?>" href="/moderator/comments?reported=1"><?= icon('flag') ?> <?= e(t('bm.reported_only')) ?></a></p>
    <?php if (!$rows): ?><p class="muted"><?= e(t($reportedOnly ? 'bm.no_reports' : 'review.no_comments')) ?></p><?php else: ?>
      <div class="table-wrap card"><table class="tbl">
        <thead><tr><th><?= e(t('review.th_author')) ?></th><th><?= e(t('eng.comments')) ?></th><th><?= e(t('review.th_date')) ?></th><th></th></tr></thead>
        <tbody><?php foreach ($rows as $c): $hidden = $c['status'] === 'hidden'; ?>
          <tr<?= $hidden ? ' class="muted"' : '' ?>>
            <td><?= e(display_name($c)) ?> <span class="badge"><?= e(role_label($c['role'])) ?></span></td>
            <td><a href="<?= e(Articles::url($c)) ?>#c<?= (int) $c['id'] ?>"><?= e($c['title']) ?></a><br><?= e(mb_substr((string) $c['body'], 0, 200)) ?>
              <?php if ((int) $c['report_count'] > 0): ?><br><span class="badge badge-warn"><?= icon('flag') ?> <?= e(t('bm.n_reports', ['n' => num((int) $c['report_count'])])) ?>: <?= e(implode(', ', array_map(fn($r) => t('bm.r_' . $r), array_filter(explode(',', (string) $c['report_reasons']))))) ?></span><?php endif; ?></td>
            <td class="muted small"><?= e(fmt_dt($c['created_at'])) ?></td>
            <td class="actions-cell">
              <?php if ((int) $c['report_count'] > 0): ?><form method="post" action="/comment/<?= (int) $c['id'] ?>/dismiss" class="inline"><?= Csrf::field() ?><input type="hidden" name="back" value="portal"><button class="btn btn-ghost btn-sm" type="submit"><?= icon('check') ?> <?= e(t('bm.dismiss')) ?></button></form><?php endif; ?>
              <form method="post" action="/comment/<?= (int) $c['id'] ?>/<?= $hidden ? 'show' : 'hide' ?>" class="inline"><?= Csrf::field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= e(t($hidden ? 'eng.show' : 'eng.hide')) ?></button></form>
              <form method="post" action="/comment/<?= (int) $c['id'] ?>/delete" class="inline" data-confirm="<?= e(t('eng.confirm_delete')) ?>"><?= Csrf::field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= icon('trash-2') ?></button></form>
            </td></tr>
        <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </div>
</section>
