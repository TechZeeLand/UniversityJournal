<?php
/** @var array $a @var int $mine */
use App\Services\Engagement;
if (!Engagement::reactionsOn()) return;
$id = (int) $a['id'];
?>
<div class="reactions" id="reactions" data-react-url="/article/<?= $id ?>/react">
  <form method="post" action="/article/<?= $id ?>/react" class="inline">
    <?= \App\Core\Csrf::field() ?><input type="hidden" name="value" value="like">
    <button class="react-btn<?= $mine === 1 ? ' is-on' : '' ?>" type="submit" data-value="like" aria-pressed="<?= $mine === 1 ? 'true' : 'false' ?>" title="<?= e(t('eng.like')) ?>">
      <?= icon('thumbs-up') ?><span class="react-label"><?= e(t('eng.like')) ?></span><b data-count="like"><?= e(num((int) $a['like_count'])) ?></b>
    </button>
  </form>
  <form method="post" action="/article/<?= $id ?>/react" class="inline">
    <?= \App\Core\Csrf::field() ?><input type="hidden" name="value" value="dislike">
    <button class="react-btn<?= $mine === -1 ? ' is-on' : '' ?>" type="submit" data-value="dislike" aria-pressed="<?= $mine === -1 ? 'true' : 'false' ?>" title="<?= e(t('eng.dislike')) ?>">
      <?= icon('thumbs-down') ?><span class="react-label"><?= e(t('eng.dislike')) ?></span><b data-count="dislike"><?= e(num((int) $a['dislike_count'])) ?></b>
    </button>
  </form>
  <a class="react-btn" href="#comments" title="<?= e(t('art.comments')) ?>"><?= icon('message-square') ?><span class="react-label"><?= e(t('eng.comments')) ?></span><b><?= e(num((int) $a['comment_count'])) ?></b></a>
</div>
