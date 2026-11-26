<?php /** @var string $group; @var array $keys, $values, $errors, $defaults */
use App\Core\Csrf;
use App\Services\Texts;
?>
<section class="section">
  <div class="wrap narrow-md">
    <h1 class="h-md"><?= e(t('portal.tech_title')) ?></h1>
    <?php \App\Core\View::partial('portal/_nav', ['active' => 'content', 'actor' => $actor]); ?>
    <?php \App\Core\View::partial('tech/_content_nav', ['active' => 'texts']); ?>
    <nav class="portal-nav sub" aria-label="<?= e(t('texts.title')) ?>">
      <?php foreach (array_keys(Texts::GROUPS) as $g): ?><a href="/tech/texts/<?= e($g) ?>"<?= $g === $group ? ' aria-current="page"' : '' ?>><?= e(t('texts.g.' . $g)) ?></a><?php endforeach; ?>
    </nav>
    <p class="muted"><?= e(t('texts.intro')) ?></p>
    <form method="post" action="/tech/texts/<?= e($group) ?>" class="form card pad" novalidate>
      <?= Csrf::field() ?>
      <?php foreach ($keys as $k):
        $long = mb_strlen((string) ($defaults['en'][$k] ?? '')) > 80 || mb_strlen((string) ($defaults['bn'][$k] ?? '')) > 80; ?>
        <div class="text-item">
          <code class="small muted"><?= e($k) ?></code>
          <div class="row">
            <?php foreach (Texts::LOCALES as $loc): $v = (string) ($values[$loc][$k] ?? ''); $ph = (string) ($defaults[$loc][$k] ?? ($defaults['en'][$k] ?? '')); $err = $errors[$loc . ':' . $k] ?? null; ?>
              <div class="field">
                <label for="t_<?= e($loc . '_' . $k) ?>"><?= $loc === 'en' ? 'English' : 'বাংলা' ?></label>
                <?php if ($long): ?>
                  <textarea id="t_<?= e($loc . '_' . $k) ?>" name="t[<?= $loc ?>][<?= e($k) ?>]" rows="3" maxlength="<?= Texts::MAX_LEN ?>" placeholder="<?= e($ph) ?>"<?= $loc === 'bn' ? ' lang="bn"' : '' ?>><?= e($v) ?></textarea>
                <?php else: ?>
                  <input id="t_<?= e($loc . '_' . $k) ?>" name="t[<?= $loc ?>][<?= e($k) ?>]" type="text" maxlength="<?= Texts::MAX_LEN ?>" value="<?= e($v) ?>" placeholder="<?= e($ph) ?>"<?= $loc === 'bn' ? ' lang="bn"' : '' ?>>
                <?php endif; ?>
                <?= $err ? '<p class="field-error">' . e($err) . '</p>' : '' ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
      <div class="actions sticky-actions"><button class="btn btn-primary" type="submit"><?= e(t('ui.save')) ?></button></div>
    </form>
  </div>
</section>
