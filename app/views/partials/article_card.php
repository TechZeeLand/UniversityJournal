<?php
/** One story. @var array $a  @var string $v lead|second|col|grid|row */
use App\Services\Articles;
$v = $v ?? 'grid';
$href = Articles::url($a);
$withCover = in_array($v, ['lead', 'second', 'grid'], true);
$dek = Articles::excerpt($a, $v === 'lead' ? 260 : ($v === 'row' ? 110 : 150));
$by = display_name($a);
?>
<article class="story story-<?= e($v) ?>">
  <?php if ($withCover): ?><a class="story-cover" href="<?= e($href) ?>" tabindex="-1" aria-hidden="true"><?= cover_html($a) ?></a><?php endif; ?>
  <div class="story-body">
    <p class="kicker"><?php if (!empty($a['is_featured'])): ?><span class="kicker-pin"><?= icon('flame') ?> <?= e(t('home.featured')) ?></span><?php endif; ?><?= e(Articles::typeName($a) ?: t('art.article')) ?></p>
    <h<?= $v === 'lead' ? '2' : '3' ?> class="story-title"<?= art_attrs($a) ?>><a href="<?= e($href) ?>"><?= e($a['title']) ?></a></h<?= $v === 'lead' ? '2' : '3' ?>>
    <?php if ($dek !== '' && $v !== 'row'): ?><p class="story-dek"<?= art_attrs($a) ?>><?= e($dek) ?></p><?php endif; ?>
    <p class="byline">
      <?= avatar($a, 'avatar-sm') ?>
      <span class="by-name"><?= e($by) ?></span>
      <span class="dot" aria-hidden="true">·</span><time datetime="<?= e((string) $a['published_at']) ?>"><?= e(time_ago($a['published_at'])) ?></time>
      <span class="dot" aria-hidden="true">·</span><span><?= e(t('art.min_read', ['n' => num((int) $a['reading_minutes'])])) ?></span>
    </p>
    <p class="stats-line">
      <span title="<?= e(t('art.reads')) ?>"><?= icon('eye') ?> <?= e(num_short((int) $a['view_count'])) ?></span>
      <span title="<?= e(t('art.likes')) ?>"><?= icon('thumbs-up') ?> <?= e(num_short((int) $a['like_count'])) ?></span>
      <span title="<?= e(t('art.comments')) ?>"><?= icon('message-square') ?> <?= e(num_short((int) $a['comment_count'])) ?></span>
    </p>
  </div>
</article>
