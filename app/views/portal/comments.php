<?php /** @var array $actor @var array $rows */ use App\Core\Csrf; use App\Services\Articles; ?>
<section class="section">
  <div class="wrap">
    <h1 class="h-md"><?= e(t('portal.mod_title')) ?></h1>
    <?php \App\Core\View::partial('portal/_nav', ['active' => 'comments', 'actor' => $actor]); ?>
    <?php if (!$rows): ?><p class="muted"><?= e(t('review.no_comments')) ?></p><?php else: ?>
      <div class="table-wrap card"><table class="tbl">
        <thead><tr><th><?= e(t('review.th_author')) ?></th><th><?= e(t('eng.comments')) ?></th><th><?= e(t('review.th_date')) ?></th><th></th></tr></thead>
        <tbody><?php foreach ($rows as $c): $hidden = $c['status'] === 'hidden'; ?>
          <tr<?= $hidden ? ' class="muted"' : '' ?>>
            <td><?= e(display_name($c)) ?> <span class="badge"><?= e(role_label($c['role'])) ?></span></td>
            <td><a href="<?= e(Articles::url($c)) ?>#c<?= (int) $c['id'] ?>"><?= e($c['title']) ?></a><br><?= e(mb_substr((string) $c['body'], 0, 200)) ?></td>
            <td class="muted small"><?= e(fmt_dt($c['created_at'])) ?></td>
            <td class="actions-cell">
              <form method="post" action="/comment/<?= (int) $c['id'] ?>/<?= $hidden ? 'show' : 'hide' ?>" class="inline"><?= Csrf::field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= e(t($hidden ? 'eng.show' : 'eng.hide')) ?></button></form>
              <form method="post" action="/comment/<?= (int) $c['id'] ?>/delete" class="inline" data-confirm="<?= e(t('eng.confirm_delete')) ?>"><?= Csrf::field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= icon('trash-2') ?></button></form>
            </td></tr>
        <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </div>
</section>
