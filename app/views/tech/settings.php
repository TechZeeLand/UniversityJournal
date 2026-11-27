<?php
/** @var array $actor, $groups, $fields, $values, $errors, $fontNames; @var string $group; @var ?array $idInfo; @var int $maxMb; @var array $mailLog; @var string $mailTo */
use App\Core\{Csrf, View};
?>
<section class="section">
  <div class="wrap narrow-md">
    <h1 class="h-md"><?= e(t('portal.tech_title')) ?></h1>
    <?php View::partial('portal/_nav', ['active' => 'settings', 'actor' => $actor]); ?>
    <nav class="portal-nav" aria-label="<?= e(t('settings.tabs')) ?>">
      <?php foreach ($groups as $g => $def): ?>
        <a href="/tech/settings/<?= e($g) ?>"<?= $g === $group ? ' aria-current="page"' : '' ?>><?= icon($def['icon']) ?> <?= e(t('settings.tab.' . $g)) ?></a>
      <?php endforeach; ?>
    </nav>

    <?php if ($idInfo !== null): $seq = $idInfo['mode'] === 'sequential'; ?>
      <form method="post" action="/tech/settings/id-mode" class="form card pad" data-confirm="<?= e(t('id.confirm')) ?>">
        <?= Csrf::field() ?>
        <h2 class="h-sm first"><?= e(t('id.title')) ?></h2>
        <p class="muted"><?= e(t('id.intro')) ?></p>
        <label class="choice">
          <input type="radio" name="id_mode" value="university"<?= $seq ? '' : ' checked' ?>>
          <span><strong><?= e(t('id.opt_university')) ?></strong><br><span class="muted small"><?= e(t('id.opt_university_d')) ?></span></span>
        </label>
        <label class="choice">
          <input type="radio" name="id_mode" value="sequential"<?= $seq ? ' checked' : '' ?>>
          <span><strong><?= e(t('id.opt_sequential')) ?></strong><br><span class="muted small"><?= e(t('id.opt_sequential_d')) ?></span></span>
        </label>
        <p class="muted small"><?= e(t('id.stats', ['total' => $idInfo['total'], 'next' => $idInfo['next'], 'nouid' => $idInfo['noUid']])) ?></p>
        <?php if ($idInfo['noUid'] > 0): ?>
          <div class="alert alert-warn" role="status"><?= icon('triangle-alert') ?><span><?= e(t('id.noid_warn', ['n' => $idInfo['noUid']])) ?>
            <a href="/moderator/users?noid=1"><?= e(t('id.noid_link')) ?></a></span></div>
        <?php endif; ?>
        <div class="actions"><button class="btn btn-primary" type="submit"><?= e(t('id.apply')) ?></button></div>
      </form>
    <?php endif; ?>

    <?php if ($group === 'branding') View::partial('tech/_brand_assets', ['maxMb' => $maxMb]); ?>
    <?php if ($group === 'urls'): ?>
      <div class="alert alert-info" role="note"><?= icon('info') ?><span><?= e(t('urls.intro')) ?></span></div>
    <?php endif; ?>
    <?php if ($group === 'profiles'): ?>
      <div class="alert alert-info" role="note"><?= icon('info') ?><span><?= e(t('profiles.intro')) ?></span></div>
    <?php endif; ?>
    <?php if ($group === 'readers'): ?>
      <div class="alert alert-info" role="note"><?= icon('info') ?><span><?= e(t('readers.intro')) ?><br>
        <?= e(t('readers.redirect')) ?> <code><?= e(\App\Services\GoogleAuth::redirectUri()) ?></code></span></div>
    <?php endif; ?>
    <?php if ($group === 'email') View::partial('tech/_email_tools', ['mailLog' => $mailLog, 'mailTo' => $mailTo]); ?>
    <?php if ($group === 'seo'): ?>
      <div class="alert alert-info" role="note"><?= icon('info') ?><span><?= e(t('seo.intro')) ?>
        <a href="/sitemap.xml">/sitemap.xml</a> · <a href="/feed.xml">/feed.xml</a> · <a href="/robots.txt">/robots.txt</a></span></div>
    <?php endif; ?>
    <?php if ($group === 'backups'): ?>
      <p><a class="btn btn-ghost btn-sm" href="/tech/backups"><?= icon('hard-drive') ?> <?= e(t('backups.title')) ?></a></p>
    <?php endif; ?>

    <form method="post" action="/tech/settings/<?= e($group) ?>" class="form card pad" novalidate>
      <?= Csrf::field() ?>
      <h2 class="h-sm first"><?= e(t('settings.tab.' . $group)) ?></h2>
      <?php foreach ($fields as $f): $k = $f['key']; $type = $f['type']; $err = $errors[$k] ?? null; $val = (string) ($values[$k] ?? '');
        $hint = t('set.' . $k . '.hint'); $hasHint = $hint !== 'set.' . $k . '.hint'; ?>
        <?php if (!empty($f['heading'])): ?><h3 class="h-xs"><?= e(t($f['heading'])) ?></h3><?php endif; ?>
        <div class="field">
          <?php if ($type === 'bool'): ?>
            <label class="check"><input type="checkbox" id="f_<?= e($k) ?>" name="<?= e($k) ?>" value="1"<?= $val === '1' ? ' checked' : '' ?>> <strong><?= e(t('set.' . $k)) ?></strong></label>
          <?php elseif ($type === 'checks'): $on = json_decode($val, true); $on = is_array($on) ? $on : []; ?>
            <span class="label"><?= e(t('set.' . $k)) ?></span>
            <div class="checks">
              <?php foreach ($f['options'] as $o): ?>
                <label class="check"><input type="checkbox" name="<?= e($k) ?>[]" value="<?= e($o) ?>"<?= in_array($o, $on, true) ? ' checked' : '' ?>> <?= e(t('profile.f.' . $o)) ?></label>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <label for="f_<?= e($k) ?>"><?= e(t('set.' . $k)) ?></label>
            <?php if ($type === 'select'): ?>
              <select id="f_<?= e($k) ?>" name="<?= e($k) ?>">
                <?php foreach ($f['options'] as $o): ?><option value="<?= e($o) ?>"<?= $val === $o ? ' selected' : '' ?>><?= e(t('set.opt.' . $o)) ?></option><?php endforeach; ?>
              </select>
            <?php elseif ($type === 'int'): ?>
              <input id="f_<?= e($k) ?>" name="<?= e($k) ?>" type="number" inputmode="numeric" min="<?= (int) $f['min'] ?>" max="<?= (int) $f['max'] ?>" value="<?= e($val) ?>">
            <?php elseif ($type === 'textarea'): ?>
              <textarea id="f_<?= e($k) ?>" name="<?= e($k) ?>" rows="3" maxlength="<?= (int) $f['max_len'] ?>"><?= e($val) ?></textarea>
            <?php elseif ($type === 'color'): ?>
              <div class="color-row"><input id="f_<?= e($k) ?>" name="<?= e($k) ?>" type="color" value="<?= e($val) ?>"><code><?= e($val) ?></code>
                <span class="muted small"><?= e(t('set.default')) ?>: <code><?= e($f['default']) ?></code></span></div>
            <?php elseif ($type === 'secret'): ?>
              <input id="f_<?= e($k) ?>" name="<?= e($k) ?>" type="password" maxlength="<?= (int) $f['max_len'] ?>" value="" autocomplete="new-password" placeholder="<?= e($val !== '' ? t('set.secret_set') : t('set.secret_unset')) ?>">
            <?php elseif ($type === 'font'): ?>
              <input id="f_<?= e($k) ?>" name="<?= e($k) ?>" type="text" maxlength="60" list="font_names" value="<?= e($val) ?>" autocomplete="off" spellcheck="false">
            <?php else:
              $inType = $type === 'url' ? 'url' : ($type === 'email' ? 'email' : 'text'); $ml = (int) ($f['max_len'] ?? 190); ?>
              <input id="f_<?= e($k) ?>" name="<?= e($k) ?>" type="<?= $inType ?>" maxlength="<?= $ml ?>" value="<?= e($val) ?>"<?= in_array($k, ['site_name_bn', 'site_tagline_bn', 'university_name_bn'], true) ? ' lang="bn"' : '' ?>>
            <?php endif; ?>
          <?php endif; ?>
          <?php if ($err): ?><p class="field-error" role="alert"><?= e($err) ?></p><?php endif; ?>
          <?php if ($hasHint): ?><p class="field-hint"><?= e($hint) ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if ($fontNames): ?><datalist id="font_names"><?php foreach ($fontNames as $n): ?><option value="<?= e($n) ?>"><?php endforeach; ?></datalist><?php endif; ?>
      <div class="actions"><button class="btn btn-primary" type="submit"><?= e(t('ui.save')) ?></button></div>
    </form>
  </div>
</section>
