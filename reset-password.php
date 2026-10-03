<?php
require_once __DIR__ . '/includes/auth.php';
redirectIfLoggedIn();

$token = isset($_POST['token']) ? (string) $_POST['token'] : query('token');
$row = null;
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $row = dbRow(
        "SELECT pr.id, pr.user_id FROM password_resets pr JOIN users u ON u.id = pr.user_id
         WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > ? AND u.status = 'active'",
        [hash('sha256', $token), date('Y-m-d H:i:s')]
    );
}

$errors = [];
if ($row && isPost()) {
    requireCsrf();
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirm  = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
    if ($msg = validatePassword($password)) $errors['password'] = $msg;
    if ($password !== $confirm) $errors['confirm_password'] = 'Passwords do not match.';
    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        dbRun('UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $row['user_id']]);
        dbRun('UPDATE password_resets SET used_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), $row['id']]);
        dbRun('DELETE FROM password_resets WHERE user_id = ? AND used_at IS NULL', [$row['user_id']]);
        $pdo->commit();
        flash('success', 'Password updated. You can now sign in.');
        redirect('login.php');
    }
}

$layout = 'auth';
$pageTitle = 'Reset password';
$panelTitle = 'Choose a fresh, strong password.';
$panelText = 'Use at least 8 characters with a mix of letters and numbers.';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-split">
    <?php require __DIR__ . '/includes/auth_panel.php'; ?>
    <div class="auth-form-wrap">
        <div class="auth-card">
            <a class="auth-logo-mobile" href="<?= e(url('index.php')) ?>"><?= logoFull('dark', 38) ?></a>
            <?php if (!$row): ?>
                <div class="empty">
                    <div class="empty-icon"><?= icon('lock', 30) ?></div>
                    <h3>This reset link is invalid or expired</h3>
                    <p>Reset links work once and expire after one hour. Request a new one to continue.</p>
                    <div class="empty-action"><a class="btn btn-primary" href="<?= e(url('forgot-password.php')) ?>">Request a new link</a></div>
                </div>
            <?php else: ?>
                <h1>Set a new password</h1>
                <p class="lead">Choose a password you have not used elsewhere.</p>
                <form method="post" novalidate data-validate data-loading>
                    <?= csrfField() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <div class="field<?= isset($errors['password']) ? ' has-error' : '' ?>">
                        <label for="password">New password</label>
                        <div class="input-icon has-toggle"><?= icon('lock', 18) ?>
                            <input id="password" name="password" type="password" class="input" required minlength="8" data-password autocomplete="new-password">
                            <button type="button" class="pw-toggle" data-toggle-password="password" aria-label="Show password"><span class="eye-on"><?= icon('eye', 18) ?></span><span class="eye-off" hidden><?= icon('eye-off', 18) ?></span></button>
                        </div>
                        <div class="strength" data-strength-for="password" data-level="0" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
                        <span class="strength-label">Enter a password</span>
                        <?php if (isset($errors['password'])): ?><p class="field-error"><?= e($errors['password']) ?></p><?php endif; ?>
                    </div>
                    <div class="field<?= isset($errors['confirm_password']) ? ' has-error' : '' ?>">
                        <label for="confirm_password">Confirm new password</label>
                        <div class="input-icon"><?= icon('lock', 18) ?><input id="confirm_password" name="confirm_password" type="password" class="input" required data-match="password" autocomplete="new-password"></div>
                        <?php if (isset($errors['confirm_password'])): ?><p class="field-error"><?= e($errors['confirm_password']) ?></p><?php endif; ?>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg btn-block"><span class="btn-label">Update password</span></button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
