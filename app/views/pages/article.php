<?php
use App\Services\Articles;
/** @var array $a @var string $html @var bool $preview @var ?string $authorPath @var int $mine @var array $comments @var array $related @var bool $canModerate */
$cu = \App\Core\Auth::user();
$by = display_name($a);
$label = (string) $a['review_label'];
?>
<div class="read-progress" aria-hidden="true"><span></span></div>
<article class="article-page" id="top">
  <?php if ($preview): ?>
    <div class="wrap"><div class="alert alert-warn" role="status"><?= icon('eye') ?><span><?= e(t('art.preview', ['status' => t('art.status_' . $a['status'])])) ?>
      <?php if ($cu && (int) $cu['id'] === (int) $a['author_id']): ?> <a href="/write/<?= (int) $a['id'] ?>"><?= e(t('write.edit')) ?></a><?php endif; ?>
      <?php if ($canModerate && $a['status'] === 'submitted'): ?> <a href="/moderator/articles/<?= (int) $a['id'] ?>"><?= e(t('review.open')) ?></a><?php endif; ?></span></div></div>
  <?php endif; ?>

  <header class="article-head wrap">
    <p class="kicker">
      <a href="/explore?type=<?= e((string) $a['type_slug']) ?>"><?= e(Articles::typeName($a) ?: t('art.article')) ?></a>
      <?php if (Articles::deptName($a) !== ''): ?><span class="dot" aria-hidden="true">·</span><a href="/explore?dept=<?= (int) $a['department_id'] ?>"><?= e(Articles::deptName($a)) ?></a><?php endif; ?>
    </p>
    <h1 class="article-title"<?= art_attrs($a) ?>><?= e($a['title']) ?></h1>
    <?php if (trim((string) $a['summary']) !== ''): ?><p class="article-dek"<?= art_attrs($a) ?>><?= e($a['summary']) ?></p><?php endif; ?>
    <div class="article-meta">
      <?= avatar($a, 'avatar-lg') ?>
      <div>
        <p class="by-line"><?php if ($authorPath): ?><a href="<?= e($authorPath) ?>"><?= e($by) ?></a><?php else: ?><?= e($by) ?><?php endif; ?>
          <span class="badge"><?= e(role_label($a['role'])) ?></span>
          <?php if ($label !== 'none'): ?><span class="badge badge-ok" title="<?= e(t('art.label_hint')) ?>"><?= icon('shield-check') ?> <?= e(t('art.label_' . $label)) ?></span><?php endif; ?></p>
        <p class="muted small">
          <?php if ($a['published_at']): ?><time datetime="<?= e((string) $a['published_at']) ?>"><?= e(fmt_date($a['published_at'])) ?></time> · <?php endif; ?>
          <?= e(t('art.min_read', ['n' => num((int) $a['reading_minutes'])])) ?> ·
          <?= icon('eye') ?> <?= e(num_short((int) $a['view_count'])) ?>
        </p>
      </div>
    </div>
  </header>

  <?php if (!empty($a['cover_path'])): ?><figure class="article-cover wrap"><?= cover_html($a, 'cover-wide') ?></figure><?php endif; ?>

  <div class="wrap article-wrap">
    <div class="article-body prose"<?= art_attrs($a) ?>><?= $html ?></div>
  </div>

  <?php if ($a['status'] === 'published'): ?>
  <div class="wrap article-wrap engage">
    <?php \App\Core\View::partial('partials/reactions', ['a' => $a, 'mine' => $mine, 'saved' => $saved ?? false]); ?>
    <?php \App\Core\View::partial('partials/share', ['a' => $a]); ?>
  </div>

  <div class="wrap article-wrap">
    <aside class="author-card card pad">
      <?= avatar($a, 'avatar-lg') ?>
      <div>
        <p class="muted small"><?= e(t('art.written_by')) ?></p>
        <h3><?php if ($authorPath): ?><a href="<?= e($authorPath) ?>"><?= e($by) ?></a><?php else: ?><?= e($by) ?><?php endif; ?></h3>
        <p class="muted small"><?= e(role_label($a['role'])) ?><?= Articles::deptName($a) !== '' ? ' · ' . e(Articles::deptName($a)) : '' ?></p>
        <p class="actions"><a class="btn btn-ghost btn-sm" href="/explore?author=<?= (int) $a['author_id'] ?>"><?= e(t('art.more_by')) ?></a></p>
      </div>
    </aside>
    <?php \App\Core\View::partial('partials/comments', ['a' => $a, 'comments' => $comments, 'canModerate' => $canModerate]); ?>
  </div>

  <?php if ($related): ?>
  <section class="section related">
    <div class="wrap">
      <h2 class="section-title"><?= e(t('art.related')) ?></h2>
      <div class="story-grid">
        <?php foreach ($related as $r) \App\Core\View::partial('partials/article_card', ['a' => $r, 'v' => 'grid']); ?>
      </div>
    </div>
  </section>
  <?php endif; ?>
  <?php endif; ?>
</article>
