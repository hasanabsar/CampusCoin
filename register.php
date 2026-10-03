<?php
require_once __DIR__ . '/includes/auth.php';
redirectIfLoggedIn();

$form = ['name' => '', 'email' => '', 'academic_year' => '', 'goal' => ''];
$errors = [];
if (isPost()) {
    requireCsrf();
    $form = ['name' => post('name'), 'email' => strtolower(post('email')), 'academic_year' => post('academic_year'), 'goal' => post('goal')];
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $password = is_string($password) ? $password : '';
    $confirm  = is_string($confirm) ? $confirm : '';

    if (mb_strlen($form['name']) < 2 || mb_strlen($form['name']) > 100) $errors['name'] = 'Please enter your full name (2–100 characters).';
    if (!isValidEmail($form['email'])) {
        $errors['email'] = 'Enter a valid email address.';
    } elseif (dbValue('SELECT id FROM users WHERE email = ? LIMIT 1', [$form['email']])
        || dbValue('SELECT id FROM admins WHERE email = ? LIMIT 1', [$form['email']])) {
        $errors['email'] = 'An account with this email already exists.';
    }
    if ($msg = validatePassword($password)) $errors['password'] = $msg;
    if ($password !== $confirm) $errors['confirm_password'] = 'Passwords do not match.';
    if (!in_array($form['academic_year'], academicYears(), true)) $errors['academic_year'] = 'Please choose your academic year.';
    $goalRaw = str_replace(',', '', $form['goal']);
    if ($goalRaw === '') $goalRaw = '0';
    if (!preg_match('/^\d{1,9}(\.\d{1,2})?$/', $goalRaw)) $errors['goal'] = 'Enter a valid amount (or leave empty).';

    if (!$errors) {
        try {
            dbRun("INSERT INTO users (name, email, password, academic_year, monthly_savings_goal, status) VALUES (?, ?, ?, ?, ?, 'pending')",
                [$form['name'], $form['email'], password_hash($password, PASSWORD_DEFAULT), $form['academic_year'], (float) $goalRaw]);
            flash('success', 'Your account was created. It is now pending admin approval — you will be able to sign in once an administrator approves it.');
            redirect('login.php');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errors['email'] = 'An account with this email already exists.';
            } else {
                throw $e;
            }
        }
    }
    flash('error', 'Please fix the highlighted fields.');
}

$layout = 'auth';
$pageTitle = 'Create account';
$panelTitle = 'Start building better money habits.';
$panelText = 'Join students who track every rupee, stay inside their budgets and reach their savings goals.';
require __DIR__ . '/includes/header.php';
$err = fn($k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
?>
<div class="auth-split">
    <?php require __DIR__ . '/includes/auth_panel.php'; ?>
    <div class="auth-form-wrap">
        <div class="auth-card wide">
            <a class="auth-logo-mobile" href="<?= e(url('index.php')) ?>"><?= logoFull('dark', 38) ?></a>
            <a class="auth-back" href="<?= e(url('index.php')) ?>"><?= icon('chevron-left', 16) ?> Back to home</a>
            <h1>Create your account</h1>
            <p class="lead">It is free and takes less than a minute.</p>

            <form method="post" novalidate data-validate data-loading>
                <?= csrfField() ?>
                <div class="field<?= isset($errors['name']) ? ' has-error' : '' ?>">
                    <label for="name">Full name</label>
                    <div class="input-icon"><?= icon('user', 18) ?><input id="name" name="name" class="input" required maxlength="100" autocomplete="name" placeholder="Ayesha Khan" value="<?= e($form['name']) ?>"></div>
                    <?= $err('name') ?>
                </div>
                <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>">
                    <label for="email">Email</label>
                    <div class="input-icon"><?= icon('mail', 18) ?><input id="email" name="email" type="email" class="input" required maxlength="190" autocomplete="email" placeholder="you@university.edu" value="<?= e($form['email']) ?>"></div>
                    <?= $err('email') ?>
                </div>
                <div class="form-grid">
                    <div class="field<?= isset($errors['password']) ? ' has-error' : '' ?>">
                        <label for="password">Password</label>
                        <div class="input-icon has-toggle"><?= icon('lock', 18) ?>
                            <input id="password" name="password" type="password" class="input" required minlength="8" data-password autocomplete="new-password" placeholder="At least 8 characters">
                            <button type="button" class="pw-toggle" data-toggle-password="password" aria-label="Show password"><span class="eye-on"><?= icon('eye', 18) ?></span><span class="eye-off" hidden><?= icon('eye-off', 18) ?></span></button>
                        </div>
                        <div class="strength" data-strength-for="password" data-level="0" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
                        <span class="strength-label" aria-live="polite">Enter a password</span>
                        <?= $err('password') ?>
                    </div>
                    <div class="field<?= isset($errors['confirm_password']) ? ' has-error' : '' ?>">
                        <label for="confirm_password">Confirm password</label>
                        <div class="input-icon has-toggle"><?= icon('lock', 18) ?>
                            <input id="confirm_password" name="confirm_password" type="password" class="input" required data-match="password" autocomplete="new-password" placeholder="Repeat password">
                            <button type="button" class="pw-toggle" data-toggle-password="confirm_password" aria-label="Show password"><span class="eye-on"><?= icon('eye', 18) ?></span><span class="eye-off" hidden><?= icon('eye-off', 18) ?></span></button>
                        </div>
                        <?= $err('confirm_password') ?>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="field<?= isset($errors['academic_year']) ? ' has-error' : '' ?>">
                        <label for="academic_year">Academic year</label>
                        <select id="academic_year" name="academic_year" class="select" required>
                            <option value="">Select year</option>
                            <?php foreach (academicYears() as $y): ?><option<?= $form['academic_year'] === $y ? ' selected' : '' ?>><?= e($y) ?></option><?php endforeach; ?>
                        </select>
                        <?= $err('academic_year') ?>
                    </div>
                    <div class="field<?= isset($errors['goal']) ? ' has-error' : '' ?>">
                        <label for="goal">Monthly savings goal <span class="optional">(optional)</span></label>
                        <div class="input-group"><span class="input-prefix"><?= e(CURRENCY) ?></span><input id="goal" name="goal" class="input" inputmode="decimal" placeholder="5,000" data-type="amount" value="<?= e($form['goal']) ?>"></div>
                        <?= $err('goal') ?>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-lg btn-block"><span class="btn-label">Create account</span></button>
                <p class="field-hint mt-2 center">By registering you agree to our <a href="<?= e(url('terms.php')) ?>">Terms</a> and <a href="<?= e(url('privacy.php')) ?>">Privacy Policy</a>.</p>
            </form>
            <p class="auth-foot">Already have an account? <a href="<?= e(url('login.php')) ?>">Sign in</a></p>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
