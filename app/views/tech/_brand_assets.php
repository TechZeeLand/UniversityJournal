<?php /** Logo and icon uploads (Tech portal > Settings > Branding). @var int $maxMb */
use App\Core\Csrf;
use App\Services\Branding;
$accept = ['logo' => 'image/svg+xml,image/png,image/jpeg,image/webp', 'logo_dark' => 'image/svg+xml,image/png,image/jpeg,image/webp',
           'icon' => 'image/svg+xml,image/png,image/jpeg,image/webp', 'og' => 'image/png,image/jpeg,image/webp'];
?>
<div class="card pad">
  <h2 class="h-sm first"><?= e(t('brand.title')) ?></h2>
  <p class="muted"><?= e(t('brand.intro', ['mb' => $maxMb])) ?></p>
  <?php foreach (Branding::SLOTS as $slot): $url = Branding::url($slot); ?>
    <form method="post" action="/tech/branding/<?= e($slot) ?>" enctype="multipart/form-data" class="asset-row">
      <?= Csrf::field() ?>
      <div class="asset-preview asset-<?= e($slot) ?>">
        <?php if ($url !== null): ?><img src="<?= e($url) ?>" alt=""><?php else: ?><span class="muted small"><?= e(t('brand.default')) ?></span><?php endif; ?>
      </div>
      <div class="asset-body">
        <strong><?= e(t('brand.' . $slot)) ?></strong>
        <p class="field-hint"><?= e(t('brand.' . $slot . '_hint')) ?></p>
        <div class="actions">
          <input type="file" name="file" accept="<?= e($accept[$slot]) ?>" aria-label="<?= e(t('brand.' . $slot)) ?>">
          <button class="btn btn-primary btn-sm" type="submit" name="do" value="upload"><?= icon('upload') ?> <?= e(t('brand.upload')) ?></button>
          <?php if ($url !== null): ?><button class="btn btn-ghost btn-sm" type="submit" name="do" value="remove" formnovalidate><?= icon('trash-2') ?> <?= e(t('brand.remove')) ?></button><?php endif; ?>
        </div>
      </div>
    </form>
  <?php endforeach; ?>
</div>
