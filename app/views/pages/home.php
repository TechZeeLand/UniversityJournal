<?php
$features = [
  ['publish', 'newspaper'], ['profile', 'graduation-cap'], ['review', 'shield-check'],
  ['lang', 'languages'], ['talk', 'message-square'], ['archive', 'archive'],
];
$latest = $latest ?? [];
$popular = $popular ?? [];
$stats = $stats ?? ['articles' => 0, 'authors' => 0, 'reads' => 0];
$lead = $latest[0] ?? null;
$second = array_slice($latest, 1, 2);
$cols = array_slice($latest, 3, 3);
$wire = array_slice($latest, 6);
?>
<section class="hero">
  <div class="hero-bg" aria-hidden="true"><span class="blob b1"></span><span class="blob b2"></span><span class="blob b3"></span></div>
  <div class="wrap hero-inner">
    <?php if (setting('home_show_eyebrow', '1') === '1'): ?><p class="eyebrow"><?= icon('sparkles') ?> <?= e(t('hero.eyebrow')) ?></p><?php endif; ?>
    <h1><?= e(t('hero.title')) ?></h1>
    <p class="lead"><?= e(t('hero.sub')) ?></p>
    <div class="actions">
      <a class="btn btn-primary btn-lg" href="<?= e(safe_link((string) setting('home_cta1_url', '/explore'), '/explore')) ?>"><?= icon('book-open') ?> <?= e(t('hero.cta_read')) ?></a>
      <a class="btn btn-ghost btn-lg" href="<?= e(safe_link((string) setting('home_cta2_url', '/login'), '/login')) ?>"><?= icon('square-pen') ?> <?= e(t('hero.cta_signin')) ?></a>
    </div>
    <?php if ($stats['articles'] > 0): ?>
      <dl class="hero-stats">
        <div><dt><?= e(t('home.stat_articles')) ?></dt><dd><?= e(num_short($stats['articles'])) ?></dd></div>
        <div><dt><?= e(t('home.stat_authors')) ?></dt><dd><?= e(num_short($stats['authors'])) ?></dd></div>
        <div><dt><?= e(t('home.stat_reads')) ?></dt><dd><?= e(num_short($stats['reads'])) ?></dd></div>
      </dl>
    <?php endif; ?>
  </div>
</section>

<?php if (setting('home_show_features', '1') === '1'): ?>
<section class="section">
  <div class="wrap">
    <h2 class="section-title"><?= e(t('feat.title')) ?></h2>
    <div class="grid">
      <?php foreach ($features as [$k, $default]): ?>
        <article class="card feature">
          <span class="card-icon"><?= icon((string) setting('home_icon_' . $k, $default)) ?></span>
          <h3><?= e(t("feat.$k.t")) ?></h3>
          <p class="muted"><?= e(t("feat.$k.d")) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (setting('home_show_latest', '1') === '1'): ?>
<section class="section paper" aria-labelledby="latest-h">
  <div class="wrap">
    <header class="masthead">
      <p class="dateline"><span><?= e(t('weekday.' . date('w'))) ?>, <?= e(fmt_date(date('Y-m-d'))) ?></span><span class="hide-sm"><?= e(site_name()) ?></span><span class="hide-sm"><?= e(t('home.edition')) ?></span></p>
      <h2 id="latest-h" class="masthead-title"><?= e(t('home.latest')) ?></h2>
      <p class="masthead-sub muted"><?= e(t('home.latest_sub')) ?></p>
    </header>

    <?php if (!$lead): ?>
      <div class="empty card pad center">
        <span class="card-icon big"><?= icon('newspaper') ?></span>
        <h3><?= e(t('home.empty_t')) ?></h3>
        <p class="muted"><?= e(t('home.empty_d')) ?></p>
      </div>
    <?php else: ?>
      <div class="paper-grid">
        <div class="paper-main">
          <div class="front">
            <?php \App\Core\View::partial('partials/article_card', ['a' => $lead, 'v' => 'lead']); ?>
            <?php if ($second): ?>
              <div class="front-side">
                <?php foreach ($second as $s) \App\Core\View::partial('partials/article_card', ['a' => $s, 'v' => 'second']); ?>
              </div>
            <?php endif; ?>
          </div>
          <?php if ($cols): ?>
            <div class="cols">
              <?php foreach ($cols as $s) \App\Core\View::partial('partials/article_card', ['a' => $s, 'v' => 'col']); ?>
            </div>
          <?php endif; ?>
          <?php if ($wire): ?>
            <h3 class="wire-title"><span><?= e(t('home.more')) ?></span></h3>
            <div class="wire">
              <?php foreach ($wire as $s) \App\Core\View::partial('partials/article_card', ['a' => $s, 'v' => 'row']); ?>
            </div>
          <?php endif; ?>
        </div>

        <aside class="paper-side" aria-label="<?= e(t('home.most_read')) ?>">
          <?php if ($popular): ?>
            <div class="box">
              <h3 class="box-title"><?= icon('trending-up') ?> <?= e(t('home.most_read')) ?></h3>
              <ol class="ranked">
                <?php foreach ($popular as $i => $s): ?>
                  <li>
                    <span class="rank" aria-hidden="true"><?= e(num($i + 1)) ?></span>
                    <div><a href="<?= e(\App\Services\Articles::url($s)) ?>"<?= art_attrs($s) ?>><?= e($s['title']) ?></a>
                      <span class="muted small"><?= icon('eye') ?> <?= e(num_short((int) $s['view_count'])) ?> · <?= e(display_name($s)) ?></span></div>
                  </li>
                <?php endforeach; ?>
              </ol>
            </div>
          <?php endif; ?>
          <div class="box box-cta">
            <h3 class="box-title"><?= icon('square-pen') ?> <?= e(t('home.write_t')) ?></h3>
            <p class="muted"><?= e(t('home.write_d')) ?></p>
            <a class="btn btn-primary btn-sm" href="<?= e(safe_link((string) setting('home_cta2_url', '/login'), '/login')) ?>"><?= e(t('hero.cta_signin')) ?></a>
          </div>
          <?php if (setting('reader_signup', '1') === '1' && !\App\Core\Auth::user()): ?>
            <div class="box">
              <h3 class="box-title"><?= icon('message-square') ?> <?= e(t('home.join_t')) ?></h3>
              <p class="muted"><?= e(t('home.join_d')) ?></p>
              <a class="btn btn-ghost btn-sm" href="/register"><?= e(t('reader.create')) ?></a>
            </div>
          <?php endif; ?>
        </aside>
      </div>
      <p class="center more-link"><a class="btn btn-ghost" href="/explore"><?= e(t('home.all')) ?> <?= icon('arrow-right') ?></a></p>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>
