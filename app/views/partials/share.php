<?php
/** @var array $a */
use App\Services\{Articles, Engagement};
if (!Engagement::shareOn()) return;
$u = abs_url(Articles::url($a));
$eu = rawurlencode($u);
$et = rawurlencode((string) $a['title']);
$nets = [
  'facebook' => ['Facebook', 'https://www.facebook.com/sharer/sharer.php?u=' . $eu],
  'x' => ['X', 'https://twitter.com/intent/tweet?url=' . $eu . '&text=' . $et],
  'whatsapp' => ['WhatsApp', 'https://wa.me/?text=' . $et . '%20' . $eu],
  'telegram' => ['Telegram', 'https://t.me/share/url?url=' . $eu . '&text=' . $et],
  'linkedin' => ['LinkedIn', 'https://www.linkedin.com/sharing/share-offsite/?url=' . $eu],
  'email' => [t('eng.share_email'), 'mailto:?subject=' . $et . '&body=' . $eu],
];
?>
<div class="share" data-share-url="<?= e($u) ?>" data-share-title="<?= e($a['title']) ?>" data-share-count="/article/<?= (int) $a['id'] ?>/share">
  <span class="share-label"><?= icon('share') ?> <?= e(t('eng.share')) ?></span>
  <button class="share-btn" type="button" data-native-share hidden><?= icon('share') ?> <?= e(t('eng.share_native')) ?></button>
  <button class="share-btn" type="button" data-copy-link data-done="<?= e(t('eng.copied')) ?>"><?= icon('link') ?> <span><?= e(t('eng.copy_link')) ?></span></button>
  <?php foreach ($nets as $k => [$label, $href]): ?>
    <a class="share-btn" href="<?= e($href) ?>" target="_blank" rel="noopener noreferrer" data-share-net="<?= e($k) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
  <button class="share-btn" type="button" onclick="window.print()"><?= icon('printer') ?> <?= e(t('eng.print')) ?></button>
</div>
