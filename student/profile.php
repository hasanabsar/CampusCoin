<?php
require_once __DIR__ . '/../includes/auth.php';
$user = requireStudent();
$uid  = (int) $user['id'];

/** Validate + store an uploaded avatar. Returns an error message or null. */
function saveAvatar(int $uid, array $file, ?string $oldName): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return 'Choose an image to upload.';
    if ($file['error'] !== UPLOAD_ERR_OK) return 'The upload failed. Please try again.';
    if ($file['size'] > AVATAR_MAX_BYTES) return 'Image must be 2 MB or smaller.';
    if (!is_uploaded_file($file['tmp_name'])) return 'Invalid upload.';
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if ($ext === null || @getimagesize($file['tmp_name']) === false) return 'Only JPG, PNG or WebP images are allowed.';
    if (!is_dir(AVATAR_DIR) || !is_writable(AVATAR_DIR)) return 'The upload folder is not writable. Check permissions on assets/uploads/avatars.';
    $name = 'u' . $uid . '_' . bin2hex(random_bytes(8)) . '.' . $ext;   // random name: no user-controlled filenames
    if (!move_uploaded_file($file['tmp_name'], AVATAR_DIR . '/' . $name)) return 'Could not save the image.';
    dbRun('UPDATE users SET avatar = ? WHERE id = ?', [$name, $uid]);
    if ($oldName) @unlink(AVATAR_DIR . '/' . basename($oldName));
    return null;
}

$errors = [];
$form = ['name' => $user['name'], 'email' => $user['email'], 'academic_year' => (string) $user['academic_year'],
         'goal' => rtrim(rtrim(number_format((float) $user['monthly_savings_goal'], 2, '.', ''), '0'), '.')];

if (isPost()) {
    requireCsrf();
    $action = post('action');

    if ($action === 'profile') {
        $form = ['name' => preg_replace('/\s+/', ' ', post('name')), 'email' => strtolower(post('email')), 'academic_year' => post('academic_year'), 'goal' => post('goal')];
        if (mb_strlen($form['name']) < 2 || mb_strlen($form['name']) > 100) $errors['name'] = 'Please enter your full name (2–100 characters).';
        if (!isValidEmail($form['email'])) {
            $errors['email'] = 'Enter a valid email address.';
        } elseif (dbValue('SELECT id FROM users WHERE email = ? AND id <> ?', [$form['email'], $uid])) {
            $errors['email'] = 'That email is already used by another account.';
        }
        if ($form['academic_year'] !== '' && !in_array($form['academic_year'], academicYears(), true)) $errors['academic_year'] = 'Choose a valid academic year.';
        $g = str_replace(',', '', $form['goal']) ?: '0';
        if (!preg_match('/^\d{1,9}(\.\d{1,2})?$/', $g)) $errors['goal'] = 'Enter a valid amount.';
        if (!$errors) {
            dbRun('UPDATE users SET name = ?, email = ?, academic_year = ?, monthly_savings_goal = ? WHERE id = ?',
                [$form['name'], $form['email'], $form['academic_year'] ?: null, (float) $g, $uid]);
            flash('success', 'Profile updated successfully.');
            redirectSelf();
        }
        flash('error', 'Please fix the highlighted fields.');
    } elseif ($action === 'avatar') {
        $err = saveAvatar($uid, $_FILES['avatar'] ?? ['error' => UPLOAD_ERR_NO_FILE], $user['avatar']);
        $err ? flash('error', $err) : flash('success', 'Profile photo updated.');
        redirectSelf();
    } elseif ($action === 'remove_avatar') {
        if ($user['avatar']) @unlink(AVATAR_DIR . '/' . basename($user['avatar']));
        dbRun('UPDATE users SET avatar = NULL WHERE id = ?', [$uid]);
        flash('success', 'Profile photo removed.');
        redirectSelf();
    } elseif ($action === 'password') {
        $cur = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
        $new = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
        $conf = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
        $hash = (string) dbValue('SELECT password FROM users WHERE id = ?', [$uid]);
        if (!password_verify($cur, $hash)) {
            $errors['current_password'] = 'Your current password is incorrect.';
        } elseif ($msg = validatePassword($new)) {
            $errors['new_password'] = $msg;
        } elseif ($new === $cur) {
            $errors['new_password'] = 'Choose a password different from the current one.';
        } elseif ($new !== $conf) {
            $errors['confirm_password'] = 'Passwords do not match.';
        } else {
            dbRun('UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $uid]);
            session_regenerate_id(true);
            flash('success', 'Password changed successfully.');
            redirectSelf();
        }
        flash('error', 'Unable to change your password.');
    }
    $user = currentUser(true);
}

$layout = 'student';
$pageTitle = 'Profile';
require __DIR__ . '/../includes/header.php';
pageHead('Profile', 'Manage your personal details, photo and security.');
$err = fn($k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
?>
<?php if ((int) $user['must_change_password'] === 1): ?>
    <div class="alert alert-warn" role="alert"><?= icon('alert', 20) ?><span><b>Please set a new password.</b> An administrator reset your password — choose your own below to continue using CampusCoin.</span></div>
<?php endif; ?>

<section class="card">
    <div class="card-body">
        <div class="profile-hero">
            <div class="profile-avatar"><?= avatarHtml($user, 88) ?></div>
            <div class="profile-meta grow">
                <h2><?= e($user['name']) ?></h2>
                <p><?= e($user['email']) ?> · <?= e($user['academic_year'] ?: 'Academic year not set') ?></p>
                <p class="text-sm">Member since <?= e(formatDate($user['created_at'], 'F Y')) ?></p>
            </div>
            <div class="row wrap">
                <form method="post" enctype="multipart/form-data" class="row" data-loading>
                    <?= csrfField() ?><input type="hidden" name="action" value="avatar">
                    <label class="btn btn-outline btn-sm" for="avatar"><?= icon('camera', 16) ?> Upload photo</label>
                    <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" onchange="this.form.submit()">
                </form>
                <?php if ($user['avatar']): ?>
                    <form method="post" class="inline"><?= csrfField() ?><input type="hidden" name="action" value="remove_avatar">
                        <button type="button" class="btn btn-ghost btn-sm" data-confirm="Remove your profile photo?" data-confirm-title="Remove photo" data-confirm-label="Remove">Remove</button></form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<div class="grid grid-2">
    <section class="card">
        <div class="card-head"><h2 class="card-title">Personal details</h2></div>
        <div class="card-body">
            <form method="post" novalidate data-validate data-loading>
                <?= csrfField() ?><input type="hidden" name="action" value="profile">
                <div class="field<?= isset($errors['name']) ? ' has-error' : '' ?>"><label for="name">Full name</label><input id="name" name="name" class="input" required maxlength="100" value="<?= e($form['name']) ?>"><?= $err('name') ?></div>
                <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>"><label for="email">Email</label><input id="email" name="email" type="email" class="input" required maxlength="190" value="<?= e($form['email']) ?>"><?= $err('email') ?></div>
                <div class="form-grid">
                    <div class="field<?= isset($errors['academic_year']) ? ' has-error' : '' ?>"><label for="academic_year">Academic year</label>
                        <select id="academic_year" name="academic_year" class="select"><option value="">Not set</option>
                            <?php foreach (academicYears() as $y): ?><option<?= $form['academic_year'] === $y ? ' selected' : '' ?>><?= e($y) ?></option><?php endforeach; ?></select><?= $err('academic_year') ?></div>
                    <div class="field<?= isset($errors['goal']) ? ' has-error' : '' ?>"><label for="goal">Monthly savings goal</label>
                        <div class="input-group"><span class="input-prefix"><?= e(CURRENCY) ?></span><input id="goal" name="goal" class="input" inputmode="decimal" data-type="amount" value="<?= e($form['goal']) ?>"></div><?= $err('goal') ?></div>
                </div>
                <button type="submit" class="btn btn-primary"><span class="btn-label"><?= icon('check', 18) ?> Save changes</span></button>
            </form>
        </div>
    </section>

    <section class="card" id="password">
        <div class="card-head"><h2 class="card-title">Change password</h2></div>
        <div class="card-body">
            <form method="post" novalidate data-validate data-loading>
                <?= csrfField() ?><input type="hidden" name="action" value="password">
                <div class="field<?= isset($errors['current_password']) ? ' has-error' : '' ?>"><label for="current_password">Current password</label>
                    <div class="input-icon has-toggle"><?= icon('lock', 18) ?><input id="current_password" name="current_password" type="password" class="input" required autocomplete="current-password">
                        <button type="button" class="pw-toggle" data-toggle-password="current_password" aria-label="Show password"><span class="eye-on"><?= icon('eye', 18) ?></span><span class="eye-off" hidden><?= icon('eye-off', 18) ?></span></button></div><?= $err('current_password') ?></div>
                <div class="field<?= isset($errors['new_password']) ? ' has-error' : '' ?>"><label for="new_password">New password</label>
                    <div class="input-icon has-toggle"><?= icon('key', 18) ?><input id="new_password" name="new_password" type="password" class="input" required minlength="8" data-password autocomplete="new-password">
                        <button type="button" class="pw-toggle" data-toggle-password="new_password" aria-label="Show password"><span class="eye-on"><?= icon('eye', 18) ?></span><span class="eye-off" hidden><?= icon('eye-off', 18) ?></span></button></div>
                    <div class="strength" data-strength-for="new_password" data-level="0" aria-hidden="true"><i></i><i></i><i></i><i></i></div><span class="strength-label">Enter a password</span><?= $err('new_password') ?></div>
                <div class="field<?= isset($errors['confirm_password']) ? ' has-error' : '' ?>"><label for="confirm_password">Confirm new password</label>
                    <div class="input-icon"><?= icon('key', 18) ?><input id="confirm_password" name="confirm_password" type="password" class="input" required data-match="new_password" autocomplete="new-password"></div><?= $err('confirm_password') ?></div>
                <button type="submit" class="btn btn-primary"><span class="btn-label"><?= icon('lock', 18) ?> Update password</span></button>
            </form>
        </div>
    </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
