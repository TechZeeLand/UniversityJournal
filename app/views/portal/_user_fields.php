<?php
/** Shared by the "new user" and "edit user" forms. Needs: $old, $roles, $departments, optional $lockRole. */
$lockRole = $lockRole ?? false;
?>
<div class="row">
  <div class="field">
    <label for="uid"><?= e(t('users.th_uid')) ?> <span class="req">*</span></label>
    <input id="uid" name="uid" type="text" maxlength="40" required value="<?= e($old['uid'] ?? '') ?>" autocapitalize="none" spellcheck="false">
    <p class="field-hint"><?= e(t('users.uid_hint')) ?></p>
  </div>
  <div class="field">
    <label for="role"><?= e(t('users.th_role')) ?> <span class="req">*</span></label>
    <select id="role" name="role"<?= $lockRole ? ' disabled' : '' ?>>
      <?php foreach ($roles as $r): ?><option value="<?= e($r) ?>"<?= ($old['role'] ?? '') === $r ? ' selected' : '' ?>><?= e(role_label($r)) ?></option><?php endforeach; ?>
      <?php if ($lockRole && !in_array($old['role'] ?? '', $roles, true)): ?><option selected><?= e(role_label($old['role'])) ?></option><?php endif; ?>
    </select>
  </div>
</div>
<div class="row">
  <div class="field">
    <label for="full_name"><?= e(t('profile.f.full_name')) ?> <span class="req">*</span></label>
    <input id="full_name" name="full_name" type="text" maxlength="190" required value="<?= e($old['full_name'] ?? '') ?>">
  </div>
  <div class="field">
    <label for="full_name_bn"><?= e(t('profile.f.full_name_bn')) ?></label>
    <input id="full_name_bn" name="full_name_bn" type="text" maxlength="190" lang="bn" value="<?= e($old['full_name_bn'] ?? '') ?>">
  </div>
</div>
<div class="row">
  <div class="field">
    <label for="department_id"><?= e(t('profile.f.department_id')) ?></label>
    <select id="department_id" name="department_id">
      <option value=""><?= e(t('profile.select')) ?></option>
      <?php foreach ($departments as $faculty => $list): ?>
        <optgroup label="<?= e($faculty) ?>">
          <?php foreach ($list as $d): ?><option value="<?= (int) $d['id'] ?>"<?= (string) $d['id'] === (string) ($old['department_id'] ?? '') ? ' selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?>
        </optgroup>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="email"><?= e(t('profile.f.email')) ?></label>
    <input id="email" name="email" type="email" maxlength="190" value="<?= e($old['email'] ?? '') ?>">
  </div>
</div>
<div class="row">
  <div class="field">
    <label for="academic_session"><?= e(t('profile.f.academic_session')) ?></label>
    <input id="academic_session" name="academic_session" type="text" maxlength="40" value="<?= e($old['academic_session'] ?? '') ?>">
  </div>
  <div class="field">
    <label for="designation"><?= e(t('profile.f.designation')) ?></label>
    <input id="designation" name="designation" type="text" maxlength="120" value="<?= e($old['designation'] ?? '') ?>">
  </div>
</div>
