<?php /** @var array $actor @var string $status @var array $rows @var array $counts */ use App\Services\Articles; ?>
<section class="section">
  <div class="wrap">
    <h1 class="h-md"><?= e(t('portal.mod_title')) ?></h1>
    <?php \App\Core\View::partial('portal/_nav', ['active' => 'articles', 'actor' => $actor]); ?>
    <nav class="portal-nav sub" aria-label="<?= e(t('review.title')) ?>">
      <?php foreach (['submitted', 'changes_requested', 'published', 'rejected', 'archived'] as $s): ?>
        <a href="/moderator/articles?status=<?= $s ?>"<?= $status === $s ? ' aria-current="page"' : '' ?>><?= e(t('art.status_' . $s)) ?> (<?= e(num($counts[$s])) ?>)</a>
      <?php endforeach; ?>
    </nav>
    <?php if (!$rows): ?><p class="muted"><?= e(t('review.none')) ?></p><?php else: ?>
      <div class="table-wrap card"><table class="tbl">
        <thead><tr><th><?= e(t('write.th_title')) ?></th><th><?= e(t('review.th_author')) ?></th><th><?= e(t('explore.type')) ?></th><th><?= e(t('review.th_date')) ?></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $a): ?>
          <tr>
            <td><a href="/moderator/articles/<?= (int) $a['id'] ?>"<?= art_attrs($a) ?>><?= e($a['title']) ?></a><?= $a['is_featured'] ? ' ' . icon('flame') : '' ?></td>
            <td><?= e(display_name($a)) ?> <span class="badge"><?= e(role_label($a['role'])) ?></span></td>
            <td class="muted"><?= e(Articles::typeName($a)) ?></td>
            <td class="muted small"><?= e(fmt_dt($a['published_at'] ?: $a['updated_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
  </div>
</section>
