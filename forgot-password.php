<?php
require_once __DIR__ . '/includes/auth.php';
redirectIfLoggedIn();

$sent = false;
$email = '';
$error = null;
$devLink = $_SESSION['dev_reset_link'] ?? null;
unset($_SESSION['dev_reset_link']);

if (isPost()) {
    requireCsrf();
    $email = strtolower(post('email'));
    if (!isValidEmail($email)) {
        $error = 'Enter a valid email address.';
    } else {
        $user = dbRow("SELECT id, name, email FROM users WHERE email = ? AND status = 'active'", [$email]);
        // Same response whether or not the account exists (prevents account enumeration).
        if ($user && (int) dbValue('SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND created_at > (NOW() - INTERVAL 1 HOUR)', [$user['id']]) < 5) {
            $token = bin2hex(random_bytes(32));
            dbRun('DELETE FROM password_resets WHERE user_id = ? AND used_at IS NULL', [$user['id']]);
            dbRun('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)', [$user['id'], hash('sha256', $token), date('Y-m-d H:i:s', time() + 3600)]);
            $link = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url('reset-password.php?token=' . $token);
            @mail($user['email'], 'Reset your CampusCoin password', "Hi {$user['name']},\n\nUse this link to choose a new password (valid for 1 hour):\n{$link}\n\nIf you did not request this, ignore this email.", 'From: no-reply@campuscoin.local');
            if (APP_ENV === 'development') {
                $_SESSION['dev_reset_link'] = $link;   // XAMPP has no mail server — show the link locally
            }
        }
        $_SESSION['reset_requested'] = true;
        redirectSelf();
    }
}
if (!empty($_SESSION['reset_requested'])) {
    $sent = true;
    unset($_SESSION['reset_requested']);
}

$layout = 'auth';
$pageTitle = 'Forgot password';
$panelTitle = 'Locked out? It happens.';
$panelText = 'Enter your email and we will send you a secure link to choose a new password.';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-split">
    <?php require __DIR__ . '/includes/auth_panel.php'; ?>
    <div class="auth-form-wrap">
        <div class="auth-card">
            <a class="auth-logo-mobile" href="<?= e(url('index.php')) ?>"><?= logoFull('dark', 38) ?></a>
            <a class="auth-back" href="<?= e(url('login.php')) ?>"><?= icon('chevron-left', 16) ?> Back to login</a>
            <h1>Forgot password</h1>
            <p class="lead">We will email you a reset link that is valid for one hour.</p>

            <?php if ($sent): ?>
                <div class="alert alert-success mb-3" role="status"><?= icon('check-circle', 20) ?><span>If an account exists for that email, a reset link is on its way.</span></div>
                <?php if ($devLink): ?>
                    <div class="dev-note"><strong>Development mode:</strong> XAMPP has no mail server, so here is your link:<br><a href="<?= e($devLink) ?>"><?= e($devLink) ?></a></div>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error mb-3" role="alert"><?= icon('alert-circle', 20) ?><span><?= e($error) ?></span></div><?php endif; ?>

            <form method="post" novalidate data-validate data-loading>
                <?= csrfField() ?>
                <div class="field">
                    <label for="email">Email</label>
                    <div class="input-icon"><?= icon('mail', 18) ?><input id="email" name="email" type="email" class="input" required autocomplete="email" placeholder="you@university.edu" value="<?= e($email) ?>"></div>
                </div>
                <button type="submit" class="btn btn-primary btn-lg btn-block"><span class="btn-label">Send reset link</span></button>
            </form>
            <p class="auth-foot">Remembered it? <a href="<?= e(url('login.php')) ?>">Back to sign in</a></p>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
