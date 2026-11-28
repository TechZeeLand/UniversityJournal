<?php /** @var array $p; @var array $links; @var string $dept; @var ?string $address; @var array $articles; @var int $total; @var int $per; @var int $page */
$sub = $p['role'] === 'student' ? (string) $p['academic_session'] : (string) $p['designation'];
$interests = array_filter(array_map('trim', explode(',', (string) $p['interests'])));
?>
<section class="section">
  <div class="wrap narrow-md">
    <p><a href="/authors">‹ <?= e(t('pub.authors_title')) ?></a></p>
    <div class="who">
      <?= avatar($p, 'avatar-xl') ?>
      <div>
        <h1 class="h-md"><?= e(display_name($p)) ?></h1>
        <p><span class="badge"><?= e(role_label($p['role'])) ?></span>
          <?php if ($sub !== ''): ?><span class="muted"> <?= e($sub) ?></span><?php endif; ?></p>
        <?php if ($dept !== ''): ?><p class="muted"><?= icon('graduation-cap') ?> <?= e($dept) ?></p><?php endif; ?>
      </div>
    </div>
    <?php if (trim((string) $p['bio']) !== ''): ?>
      <div class="card pad"><h2 class="h-sm first"><?= e(t('pub.about')) ?></h2><p class="prose-text"><?= nl2br(e((string) $p['bio'])) ?></p></div>
    <?php endif; ?>
    <?php if ($interests): ?>
      <h2 class="h-sm"><?= e(t('profile.f.interests')) ?></h2>
      <p class="tags"><?php foreach ($interests as $i): ?><span class="badge"><?= e($i) ?></span> <?php endforeach; ?></p>
    <?php endif; ?>
    <?php if ($links): ?>
      <h2 class="h-sm"><?= e(t('profile.f.links')) ?></h2>
      <ul class="linklist"><?php foreach ($links as $l): ?><li><a href="<?= e($l) ?>" rel="noopener nofollow ugc" target="_blank"><?= icon('external-link') ?> <?= e(preg_replace('#^https?://#i', '', $l)) ?></a></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <h2 class="h-sm"><?= e(t('pub.articles')) ?><?php if ($total > 0): ?> <span class="muted small">(<?= e(num($total)) ?>)</span><?php endif; ?></h2>
  </div>
  <div class="wrap">
    <?php if ($articles): ?>
      <div class="story-grid">
        <?php foreach ($articles as $a) \App\Core\View::partial('partials/article_card', ['a' => $a, 'v' => 'grid']); ?>
      </div>
      <?php \App\Core\View::partial('partials/pager', ['total' => $total, 'per' => $per, 'page' => $page]); ?>
    <?php else: ?>
      <div class="wrap narrow-md"><div class="card pad center"><p class="muted"><?= e(t('pub.no_articles')) ?></p></div></div>
    <?php endif; ?>
  </div>
</section>
