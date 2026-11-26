<?php
use App\Services\Articles;
/** @var array $f @var array $rows @var int $total @var int $page @var int $per @var array $types @var array $departments */
$sortIcons = ['newest' => 'sort-desc', 'oldest' => 'sort-asc', 'top_rated' => 'star-filled', 'most_liked' => 'thumbs-up', 'most_read' => 'eye', 'most_discussed' => 'message-square'];
$qs = static function (array $over) use ($f): string {
    $q = array_filter(array_merge($f, $over), static fn($v) => $v !== '' && $v !== 0 && $v !== null);
    if (($q['sort'] ?? '') === 'newest') unset($q['sort']);
    return '/explore' . ($q ? '?' . http_build_query($q) : '');
};
$active = [];
if ($f['q'] !== '') $active[] = [t('explore.q') . ': ' . $f['q'], $qs(['q' => ''])];
foreach ($types as $tp) if ($tp['slug'] === $f['type']) $active[] = [Articles::typeLabel($tp), $qs(['type' => ''])];
if ($f['dept'] > 0) foreach ($departments as $list) foreach ($list as $d) if ((int) $d['id'] === $f['dept']) $active[] = [$d['name'], $qs(['dept' => 0])];
if (!empty($f['author'])) $active[] = [t('explore.by_author'), $qs(['author' => 0])];
if ($f['lang'] !== '') $active[] = [t('explore.lang_' . $f['lang']), $qs(['lang' => ''])];
if ($f['period'] !== '') $active[] = [t('explore.period_' . $f['period']), $qs(['period' => ''])];
if ($f['from'] !== '') $active[] = [t('explore.from') . ' ' . $f['from'], $qs(['from' => ''])];
if ($f['to'] !== '') $active[] = [t('explore.to') . ' ' . $f['to'], $qs(['to' => ''])];
?>
<section class="page-hero">
  <div class="wrap">
    <h1 class="h-md"><?= e(t('explore.title')) ?></h1>
    <p class="lead"><?= e(t('explore.sub')) ?></p>
    <form method="get" action="/explore" class="explore-form" role="search" data-autosubmit>
      <div class="search-big">
        <?= icon('search') ?>
        <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="<?= e(t('explore.search_ph')) ?>" aria-label="<?= e(t('explore.search_ph')) ?>">
        <button class="btn btn-primary btn-sm" type="submit"><?= e(t('ui.search')) ?></button>
      </div>

      <div class="chips" role="radiogroup" aria-label="<?= e(t('explore.sort')) ?>">
        <?php foreach (Articles::SORTS as $s): ?>
          <label class="chip"><input type="radio" name="sort" value="<?= e($s) ?>"<?= $f['sort'] === $s ? ' checked' : '' ?>><span><?= icon($sortIcons[$s]) ?> <?= e(t('explore.sort_' . $s)) ?></span></label>
        <?php endforeach; ?>
      </div>

      <details class="more-filters"<?= ($f['type'] !== '' || $f['dept'] || $f['lang'] !== '' || $f['period'] !== '' || $f['from'] !== '' || $f['to'] !== '') ? ' open' : '' ?>>
        <summary><?= icon('filter') ?> <?= e(t('explore.filters')) ?></summary>
        <div class="filter-grid">
          <label class="field"><span class="label"><?= e(t('explore.type')) ?></span>
            <select name="type"><option value=""><?= e(t('explore.any')) ?></option>
              <?php foreach ($types as $tp): ?><option value="<?= e($tp['slug']) ?>"<?= $tp['slug'] === $f['type'] ? ' selected' : '' ?>><?= e(Articles::typeLabel($tp)) ?></option><?php endforeach; ?>
            </select></label>
          <label class="field"><span class="label"><?= e(t('profile.f.department_id')) ?></span>
            <select name="dept"><option value="0"><?= e(t('pub.all_depts')) ?></option>
              <?php foreach ($departments as $faculty => $list): ?><optgroup label="<?= e($faculty) ?>"><?php foreach ($list as $d): ?><option value="<?= (int) $d['id'] ?>"<?= (int) $d['id'] === $f['dept'] ? ' selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?>
            </select></label>
          <label class="field"><span class="label"><?= e(t('explore.lang')) ?></span>
            <select name="lang"><option value=""><?= e(t('explore.any')) ?></option>
              <?php foreach (Articles::LANGS as $l): ?><option value="<?= $l ?>"<?= $l === $f['lang'] ? ' selected' : '' ?>><?= e(t('explore.lang_' . $l)) ?></option><?php endforeach; ?>
            </select></label>
          <label class="field"><span class="label"><?= e(t('explore.period')) ?></span>
            <select name="period"><option value=""><?= e(t('explore.any_time')) ?></option>
              <?php foreach (array_keys(Articles::PERIODS) as $p): ?><option value="<?= $p ?>"<?= $p === $f['period'] ? ' selected' : '' ?>><?= e(t('explore.period_' . $p)) ?></option><?php endforeach; ?>
            </select></label>
          <label class="field"><span class="label"><?= e(t('explore.from')) ?></span><input type="date" name="from" value="<?= e($f['from']) ?>"></label>
          <label class="field"><span class="label"><?= e(t('explore.to')) ?></span><input type="date" name="to" value="<?= e($f['to']) ?>"></label>
        </div>
        <div class="actions"><button class="btn btn-primary btn-sm" type="submit"><?= e(t('explore.apply')) ?></button>
          <a class="btn btn-ghost btn-sm" href="/explore"><?= e(t('explore.reset')) ?></a></div>
      </details>
    </form>
  </div>
</section>

<section class="section tight">
  <div class="wrap">
    <div class="result-bar">
      <p class="muted" role="status"><?= e(t('explore.count', ['n' => num($total)])) ?></p>
      <?php if ($active): ?>
        <p class="tags"><?php foreach ($active as [$label, $href]): ?><a class="tag tag-x" href="<?= e($href) ?>" title="<?= e(t('explore.remove')) ?>"><?= e($label) ?> <?= icon('close') ?></a><?php endforeach; ?></p>
      <?php endif; ?>
    </div>

    <?php if (!$rows): ?>
      <div class="empty card pad center">
        <span class="card-icon big"><?= icon('search') ?></span>
        <h3><?= e(t('explore.none_t')) ?></h3>
        <p class="muted"><?= e(t('explore.none_d')) ?></p>
        <?php if ($active): ?><a class="btn btn-ghost" href="/explore"><?= e(t('explore.reset')) ?></a><?php endif; ?>
      </div>
    <?php else: ?>
      <div class="story-grid">
        <?php foreach ($rows as $a) \App\Core\View::partial('partials/article_card', ['a' => $a, 'v' => 'grid']); ?>
      </div>
      <?php \App\Core\View::partial('partials/pager', ['total' => $total, 'per' => $per, 'page' => $page]); ?>
    <?php endif; ?>
  </div>
</section>
