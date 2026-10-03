<?php
require_once __DIR__ . '/includes/auth.php';
redirectIfLoggedIn();

// The admin login hint only changes the presentation. Authentication always
// checks the separate admins table and student users table and redirects by
// the authenticated account type.
$adminMode = query('admin') === '1' || post('admin') === '1';

$email = '';
$error = null;
if (isPost()) {
    requireCsrf();
    $email    = strtolower(post('email'));
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (!isValidEmail($email) || !is_string($password) || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        $error = attemptLogin($email, $password, $remember);
        if ($error === null) {
            $user = currentUser(true);
            flash('success', 'Welcome back, ' . explode(' ', $user['name'])[0] . '!');
            redirect(homeFor($user));
        }
    }
}

$layout = 'auth';
$pageTitle = $adminMode ? 'Admin Login' : 'Login';
$panelTitle = $adminMode ? 'Admin console access.' : 'Welcome back to smarter money.';
$panelText = $adminMode
    ? 'Sign in with your administrator account to manage students, categories, tips and announcements.'
    : 'Pick up where you left off — your budgets, reports and saving tips are ready.';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-split">
    <?php require __DIR__ . '/includes/auth_panel.php'; ?>
    <div class="auth-form-wrap">
        <div class="auth-card">
            <a class="auth-logo-mobile" href="<?= e(url('index.php')) ?>"><?= logoFull('dark', 38) ?></a>
            <a class="auth-back" href="<?= e(url('index.php')) ?>"><?= icon('chevron-left', 16) ?> Back to home</a>
            <?php if ($adminMode): ?><span class="badge navy mb-2"><?= icon('shield', 13) ?> Admin sign-in</span><?php endif; ?>
            <h1><?= $adminMode ? 'Admin sign in' : 'Sign in' ?></h1>
            <p class="lead"><?= $adminMode ? 'Enter your administrator credentials to open the admin console.' : 'Enter your details to access your CampusCoin account.' ?></p>

            <?php if ($error): ?>
                <div class="alert alert-error mb-3" role="alert"><?= icon('alert-circle', 20) ?><span><?= e($error) ?></span></div>
            <?php endif; ?>

            <form method="post" novalidate data-validate data-loading>
                <?= csrfField() ?>
                <?php if ($adminMode): ?><input type="hidden" name="admin" value="1"><?php endif; ?>
                <div class="field">
                    <label for="email">Email</label>
                    <div class="input-icon"><?= icon('mail', 18) ?>
                        <input id="email" name="email" type="email" class="input" placeholder="<?= $adminMode ? 'admin@campuscoin.com' : 'you@university.edu' ?>" autocomplete="email" required value="<?= e($email) ?>" autofocus></div>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <div class="input-icon has-toggle"><?= icon('lock', 18) ?>
                        <input id="password" name="password" type="password" class="input" placeholder="Your password" autocomplete="current-password" required>
                        <button type="button" class="pw-toggle" data-toggle-password="password" aria-label="Show password">
                            <span class="eye-on"><?= icon('eye', 18) ?></span><span class="eye-off" hidden><?= icon('eye-off', 18) ?></span>
                        </button>
                    </div>
                </div>
                <div class="row between wrap mb-3">
                    <label class="checkbox"><input type="checkbox" name="remember" value="1"> Remember me</label>
                    <a href="<?= e(url('forgot-password.php')) ?>" class="text-sm"><strong>Forgot password?</strong></a>
                </div>
                <button type="submit" class="btn btn-primary btn-lg btn-block"><span class="btn-label">Sign in</span></button>
            </form>
            <?php if ($adminMode): ?>
                <p class="auth-foot"><a href="<?= e(url('login.php')) ?>">Sign in as a student instead</a></p>
            <?php else: ?>
                <p class="auth-foot">New to CampusCoin? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
