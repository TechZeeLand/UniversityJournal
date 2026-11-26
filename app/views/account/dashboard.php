<?php $who = display_name($u + $p); ?>
<section class="section">
  <div class="wrap">
    <div class="who">
      <?= avatar($u + $p, 'avatar-lg') ?>
      <div>
        <h1 class="h-md"><?= e(t('dash.welcome', ['name' => $who])) ?></h1>
        <p class="muted"><span class="badge"><?= e(role_label($u['role'])) ?></span></p>
      </div>
    </div>

    <?php if ($missing): ?>
      <div class="alert alert-warn">
        <?= icon('triangle-alert') ?>
        <span><strong><?= e(t('dash.profile_incomplete')) ?></strong>
          <?= e(t('dash.profile_missing', ['list' => implode(', ', array_map(fn($f) => t('profile.f.' . $f), $missing))])) ?></span>
      </div>
    <?php else: ?>
      <div class="alert alert-ok"><?= icon('circle-check') ?><span><?= e(t('dash.profile_done')) ?></span></div>
    <?php endif; ?>

    <div class="grid">
      <article class="card">
        <span class="card-icon"><?= icon('user') ?></span>
        <h3><?= e(t('dash.card_profile')) ?></h3>
        <p class="muted"><?= e(t('dash.card_profile_d')) ?></p>
        <p><a class="btn btn-primary btn-sm" href="/profile"><?= e(t('dash.edit_profile')) ?></a></p>
      </article>
      <?php $pub = \App\Services\PublicUrl::path($u); if ($pub !== null): $vis = \App\Services\PublicUrl::visible($u + $p); ?>
      <article class="card">
        <span class="card-icon"><?= icon('link') ?></span>
        <h3><?= e(t('dash.card_public')) ?></h3>
        <p class="muted"><?= e(t($vis ? 'dash.public_on' : 'dash.public_off')) ?></p>
        <p><code><?= e($pub) ?></code></p>
        <?php if ($vis): ?><p><a class="btn btn-ghost btn-sm" href="<?= e($pub) ?>"><?= e(t('dash.public_view')) ?></a></p><?php endif; ?>
      </article>
      <?php endif; ?>
      <article class="card">
        <span class="card-icon"><?= icon('key-round') ?></span>
        <h3><?= e(t('pw.change_title')) ?></h3>
        <p class="muted"><?= e(t('dash.card_pw_d')) ?></p>
        <p><a class="btn btn-ghost btn-sm" href="/account/password"><?= e(t('pw.change_title')) ?></a></p>
      </article>
      <article class="card">
        <span class="card-icon"><?= icon('square-pen') ?></span>
        <h3><?= e(t('dash.card_write')) ?></h3>
        <p class="muted"><?= e(t('dash.write_soon')) ?></p>
      </article>
    </div>
  </div>
</section>
