<?php
/** @var array $a @var array $comments @var bool $canModerate */
use App\Core\{Auth, Csrf};
use App\Services\Engagement;
if (!Engagement::commentsOn()) return;
$cu = Auth::user();
$id = (int) $a['id'];
$max = max(100, (int) setting('comment_max_len', '1500'));
$next = rawurlencode(\App\Services\Articles::url($a) . '#comments');

$one = function (array $c, bool $isReply) use ($cu, $canModerate, $id): void {
    $hidden = $c['status'] === 'hidden';
    $own = $cu && (int) $cu['id'] === (int) $c['user_id'];
?>
  <li class="comment<?= $isReply ? ' is-reply' : '' ?><?= $hidden ? ' is-hidden' : '' ?>" id="c<?= (int) $c['id'] ?>">
    <?= avatar($c, 'avatar-sm') ?>
    <div class="comment-main">
      <p class="comment-head"><strong><?= e(display_name($c) ?: t('eng.anon')) ?></strong>
        <span class="badge"><?= e(role_label($c['role'])) ?></span>
        <span class="muted small"><?= e(time_ago($c['created_at'])) ?></span>
        <?php if ($hidden): ?><span class="badge badge-warn"><?= e(t('eng.hidden')) ?></span><?php endif; ?></p>
      <p class="comment-text"><?= nl2br(e($c['body'])) ?></p>
      <p class="comment-actions">
        <?php if ($cu && !$hidden): ?><button class="link-btn" type="button" data-reply="<?= (int) $c['id'] ?>"><?= icon('undo') ?> <?= e(t('eng.reply')) ?></button><?php endif; ?>
        <?php if ($canModerate): ?>
          <form method="post" action="/comment/<?= (int) $c['id'] ?>/<?= $hidden ? 'show' : 'hide' ?>" class="inline"><?= Csrf::field() ?><button class="link-btn" type="submit"><?= icon($hidden ? 'eye' : 'eye-off') ?> <?= e(t($hidden ? 'eng.show' : 'eng.hide')) ?></button></form>
        <?php endif; ?>
        <?php if ($own || $canModerate): ?>
          <form method="post" action="/comment/<?= (int) $c['id'] ?>/delete" class="inline" data-confirm="<?= e(t('eng.confirm_delete')) ?>"><?= Csrf::field() ?><button class="link-btn danger" type="submit"><?= icon('trash-2') ?> <?= e(t('eng.delete')) ?></button></form>
        <?php endif; ?>
      </p>
    </div>
  </li>
<?php
};
?>
<section class="comments" id="comments" aria-labelledby="comments-h">
  <h2 id="comments-h" class="h-md"><?= icon('message-square') ?> <?= e(t('eng.comments')) ?> <span class="muted">(<?= e(num((int) $a['comment_count'])) ?>)</span></h2>

  <?php if ($cu): ?>
    <form method="post" action="/article/<?= $id ?>/comment" class="form comment-form" data-comment-form>
      <?= Csrf::field() ?><input type="hidden" name="parent" value="" data-parent>
      <p class="reply-note muted small" data-reply-note hidden><?= e(t('eng.replying')) ?> <button class="link-btn" type="button" data-reply-cancel><?= e(t('eng.cancel')) ?></button></p>
      <div class="field">
        <label for="cbody" class="sr-only"><?= e(t('eng.write')) ?></label>
        <textarea id="cbody" name="body" rows="3" maxlength="<?= $max ?>" placeholder="<?= e(t('eng.write')) ?>" required></textarea>
        <p class="field-hint"><span data-count-left><?= $max ?></span> <?= e(t('eng.chars_left')) ?></p>
      </div>
      <button class="btn btn-primary btn-sm" type="submit"><?= icon('send') ?> <?= e(t('eng.post')) ?></button>
    </form>
  <?php else: ?>
    <div class="card pad signin-prompt">
      <p><?= e(t('eng.signin_to_comment')) ?></p>
      <p class="actions"><a class="btn btn-primary btn-sm" href="/login?next=<?= $next ?>"><?= e(t('nav.login')) ?></a>
        <?php if (setting('reader_signup', '1') === '1'): ?><a class="btn btn-ghost btn-sm" href="/register?next=<?= $next ?>"><?= e(t('reader.create')) ?></a><?php endif; ?></p>
    </div>
  <?php endif; ?>

  <?php if (!$comments): ?>
    <p class="muted"><?= e(t('eng.no_comments')) ?></p>
  <?php else: ?>
    <ul class="comment-list">
      <?php foreach ($comments as $c): $one($c, false); ?>
        <?php if ($c['replies']): ?><li class="reply-wrap"><ul class="comment-list replies"><?php foreach ($c['replies'] as $r) $one($r, true); ?></ul></li><?php endif; ?>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
