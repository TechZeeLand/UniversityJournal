<?php
/** @var array $t  presets, decls (light/dark variable lists), auto, checks */
?>
<div class="alert alert-info" role="note"><?= icon('info') ?><span><?= e(t('theme.intro')) ?></span></div>

<div class="card pad theme-presets">
  <h2 class="h-sm first"><?= e(t('theme.presets')) ?></h2>
  <p class="muted small"><?= e(t('theme.presets_hint')) ?></p>
  <div class="preset-row">
    <?php foreach ($t['presets'] as $name => [$light, $dark]): ?>
      <button type="button" class="preset" data-preset data-light="<?= e($light) ?>" data-dark="<?= e($dark) ?>">
        <span class="swatch" style="background:<?= e($light) ?>"></span><span class="swatch" style="background:<?= e($dark) ?>"></span>
        <span class="preset-name"><?= e(t('theme.p_' . $name)) ?></span>
      </button>
    <?php endforeach; ?>
  </div>
</div>

<div class="card pad">
  <h2 class="h-sm first"><?= e(t('theme.preview')) ?></h2>
  <p class="muted small"><?= e(t('theme.preview_hint')) ?></p>
  <div class="theme-preview-grid">
    <?php foreach (['light', 'dark'] as $m): ?>
      <div class="theme-preview" data-theme="<?= $m ?>" style="<?= e($t['decls'][$m]) ?>">
        <div class="tp-head"><span class="tp-logo"></span><strong>Journal</strong><span class="tp-nav"><a href="#" tabindex="-1">Explore</a><a href="#" tabindex="-1">Authors</a></span><span class="btn btn-primary btn-sm">Sign in</span></div>
        <div class="tp-cover cover-ph" aria-hidden="true"><span class="blob b2"></span><span class="blob b3"></span></div>
        <h3 class="tp-title"><?= e(t('theme.sample_title')) ?></h3>
        <p><?= e(t('theme.sample_text')) ?> <a href="#" tabindex="-1"><?= e(t('theme.sample_link')) ?></a></p>
        <p class="muted small"><?= e(t('theme.sample_muted')) ?></p>
        <p><span class="btn btn-primary btn-sm">Primary</span> <span class="btn btn-ghost btn-sm">Ghost</span> <span class="btn btn-danger btn-sm">Danger</span> <span class="badge">Badge</span></p>
        <div class="alert alert-ok"><?= icon('circle-check') ?><span><?= e(t('theme.sample_ok')) ?></span></div>
        <div class="alert alert-warn"><?= icon('triangle-alert') ?><span><?= e(t('theme.sample_warn')) ?></span></div>
        <div class="alert alert-error"><?= icon('circle-alert') ?><span><?= e(t('theme.sample_err')) ?></span></div>
      </div>
    <?php endforeach; ?>
  </div>
  <h3 class="h-xs"><?= e(t('theme.checks')) ?></h3>
  <p class="muted small"><?= e(t('theme.checks_hint')) ?></p>
  <div id="theme-checks"><?php \App\Core\View::partial('tech/_theme_checks', ['checks' => $t['checks']]); ?></div>
</div>
<script src="<?= asset('js/theme-admin.js') ?>" defer></script>
