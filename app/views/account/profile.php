<?php
$fe = function (string $k) use ($errors) {
    return empty($errors[$k]) ? '' : '<p class="field-error">' . e($errors[$k]) . '</p>';
};
$req = fn(string $k) => in_array($k, $required, true) ? ' <span class="req" title="' . e(t('profile.required')) . '">*</span>' : '';
$isStudent = $u['role'] === 'student';
?>
<section class="section">
  <div class="wrap narrow-md">
    <h1 class="h-md"><?= e(t('profile.title')) ?></h1>
    <p class="muted"><?= e(t('profile.private_note')) ?> <?= $required ? e(t('profile.required_note')) : '' ?></p>
    <?php if ($errors): ?>
      <div class="alert alert-error" role="alert"><?= icon('circle-alert') ?><span><?= e(t('profile.fix_errors')) ?></span></div>
    <?php endif; ?>

    <form method="post" action="/profile" enctype="multipart/form-data" class="form card pad">
      <?= \App\Core\Csrf::field() ?>

      <div class="field">
        <span class="label"><?= e(t('profile.f.photo_path')) ?><?= $req('photo_path') ?></span>
        <div class="photo-row">
          <span id="photoPreview"><?= avatar(['photo_path' => $photo, 'full_name' => $old['full_name'], 'uid' => $u['uid']], 'avatar-lg') ?></span>
          <div>
            <input type="file" name="photo" id="photo" accept="image/jpeg,image/png,image/webp,image/gif" data-preview="photoPreview">
            <p class="field-hint"><?= e(t('profile.photo_hint', ['mb' => $maxMb])) ?></p>
            <?php if ($photo !== ''): ?>
              <label class="check"><input type="checkbox" name="remove_photo" value="1"> <?= e(t('profile.remove_photo')) ?></label>
            <?php endif; ?>
            <?= $fe('photo') ?>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="field">
          <label for="full_name"><?= e(t('profile.f.full_name')) ?><?= $req('full_name') ?></label>
          <input id="full_name" name="full_name" type="text" maxlength="190" value="<?= e($old['full_name']) ?>">
        </div>
        <div class="field">
          <label for="full_name_bn"><?= e(t('profile.f.full_name_bn')) ?><?= $req('full_name_bn') ?></label>
          <input id="full_name_bn" name="full_name_bn" type="text" maxlength="190" lang="bn" value="<?= e($old['full_name_bn']) ?>">
        </div>
      </div>

      <div class="row">
        <div class="field">
          <label for="department_id"><?= e(t('profile.f.department_id')) ?><?= $req('department_id') ?></label>
          <select id="department_id" name="department_id">
            <option value=""><?= e(t('profile.select')) ?></option>
            <?php foreach ($departments as $faculty => $list): ?>
              <optgroup label="<?= e($faculty) ?>">
                <?php foreach ($list as $d): ?>
                  <option value="<?= (int) $d['id'] ?>"<?= (string) $d['id'] === (string) $old['department_id'] ? ' selected' : '' ?>><?= e($d['name']) ?></option>
                <?php endforeach; ?>
              </optgroup>
            <?php endforeach; ?>
          </select>
          <?= $fe('department_id') ?>
        </div>
        <?php if ($isStudent): ?>
          <div class="field">
            <label for="academic_session"><?= e(t('profile.f.academic_session')) ?><?= $req('academic_session') ?></label>
            <input id="academic_session" name="academic_session" type="text" maxlength="40" value="<?= e($old['academic_session']) ?>" placeholder="2024-25">
          </div>
        <?php else: ?>
          <div class="field">
            <label for="designation"><?= e(t('profile.f.designation')) ?><?= $req('designation') ?></label>
            <input id="designation" name="designation" type="text" maxlength="120" value="<?= e($old['designation']) ?>">
          </div>
        <?php endif; ?>
      </div>

      <div class="field">
        <label for="bio"><?= e(t('profile.f.bio')) ?><?= $req('bio') ?></label>
        <textarea id="bio" name="bio" rows="5" maxlength="2000"><?= e($old['bio']) ?></textarea>
      </div>
      <div class="field">
        <label for="interests"><?= e(t('profile.f.interests')) ?><?= $req('interests') ?></label>
        <input id="interests" name="interests" type="text" maxlength="500" value="<?= e($old['interests']) ?>">
        <p class="field-hint"><?= e(t('profile.interests_hint')) ?></p>
      </div>
      <div class="field">
        <label for="links"><?= e(t('profile.f.links')) ?><?= $req('links') ?></label>
        <textarea id="links" name="links" rows="3" placeholder="https://"><?= e($old['links']) ?></textarea>
        <p class="field-hint"><?= e(t('profile.links_hint')) ?></p>
        <?= $fe('links') ?>
      </div>
      <div class="field">
        <label for="email"><?= e(t('profile.f.email')) ?></label>
        <input id="email" name="email" type="email" maxlength="190" value="<?= e($old['email']) ?>" autocomplete="email">
        <p class="field-hint"><?= e(t('profile.email_hint')) ?></p>
        <?= $fe('email') ?>
      </div>

      <div class="actions"><button class="btn btn-primary" type="submit"><?= e(t('profile.save')) ?></button>
        <a class="btn btn-ghost" href="<?= e(\App\Core\Auth::homeFor($u['role'])) ?>"><?= e(t('ui.cancel')) ?></a></div>
    </form>
  </div>
</section>
