<?php
/**
 * CampusCoin — authentication, sessions and role-based access control.
 * Every page starts by requiring this file.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/database.php';

/* Friendly 500 page for any uncaught exception. */
set_exception_handler(function (Throwable $e) {
    error_log('CampusCoin error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    renderErrorPage(
        500,
        'Something went wrong',
        'An unexpected error occurred. Please try again in a moment.',
        'alert',
        APP_DEBUG ? $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')' : null
    );
});

/* ---------------------------------------------------------------------
 * Session handling
 * ------------------------------------------------------------------- */
function startSecureSession(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string) (30 * 86400));
    session_name('campuscoin_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URL !== '' ? BASE_URL : '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Bind the session to the browser fingerprint (mitigates session theft).
    $fp = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    if (isset($_SESSION['fp']) && !hash_equals($_SESSION['fp'], $fp)) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['fp'] = $fp;

    // Idle timeout: 2 hours, or 30 days when "Remember me" was ticked.
    $limit = !empty($_SESSION['remember']) ? 30 * 86400 : 7200;
    if (isset($_SESSION['last_active']) && time() - (int) $_SESSION['last_active'] > $limit) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['fp'] = $fp;
    }
    $_SESSION['last_active'] = time();
}
startSecureSession();

/* ---------------------------------------------------------------------
 * Current user + role helpers
 * ------------------------------------------------------------------- */

/** Logged-in user row (fresh from DB, cached per request) or null. */
function currentUser(bool $reload = false): ?array
{
    static $loaded = false, $user = null;
    if ($reload) {
        $loaded = false;
    }
    if (!$loaded) {
        $loaded = true;
        $user = null;
        if (!empty($_SESSION['uid'])) {
            $accountType = ($_SESSION['account_type'] ?? 'student') === 'admin' ? 'admin' : 'student';
            $row = $accountType === 'admin'
                ? dbRow("SELECT id, name, email, 'admin' AS role, NULL AS academic_year, 0 AS monthly_savings_goal,
                                status, NULL AS avatar, must_change_password, created_at
                         FROM admins WHERE id = ?", [(int) $_SESSION['uid']])
                : dbRow("SELECT id, name, email, 'student' AS role, academic_year, monthly_savings_goal,
                                status, avatar, must_change_password, created_at
                         FROM users WHERE id = ?", [(int) $_SESSION['uid']]);
            if ($row && $row['status'] === 'active') {
                $user = $row;
            } else {
                // Account removed or disabled while logged in → end the session.
                unset($_SESSION['uid']);
                session_regenerate_id(true);
                if ($row && $row['status'] === 'pending') {
                    flash('error', 'Your account is pending admin approval. Please check back once it has been approved.');
                } elseif ($row) {
                    flash('error', 'This account has been disabled. Please contact support.');
                }
            }
        }
    }
    return $user;
}

function isLoggedIn(): bool
{
    return currentUser() !== null;
}

function isAdmin(): bool
{
    $u = currentUser();
    return $u !== null && $u['role'] === 'admin';
}

function isStudent(): bool
{
    $u = currentUser();
    return $u !== null && $u['role'] === 'student';
}

function homeFor(array $user): string
{
    return $user['role'] === 'admin' ? 'admin/dashboard.php' : 'student/dashboard.php';
}

/** Any authenticated user. Guests are sent to the login page. */
function requireLogin(): array
{
    $u = currentUser();
    if ($u === null) {
        flash('warning', 'Please sign in to continue.');
        redirect('login.php');
    }
    return $u;
}

/** Students only. Admins are redirected to their own console. */
function requireStudent(): array
{
    $u = requireLogin();
    if ($u['role'] !== 'student') {
        flash('info', 'Admin accounts use the admin console.');
        redirect('admin/dashboard.php');
    }
    // Force a password change after an admin-issued reset.
    if ((int) $u['must_change_password'] === 1 && basename($_SERVER['SCRIPT_NAME']) !== 'profile.php') {
        flash('warning', 'Please choose a new password to continue.');
        redirect('student/profile.php#password');
    }
    return $u;
}

/** Admins only. Students get a 403 page. */
function requireAdmin(): array
{
    $u = requireLogin();
    if ($u['role'] !== 'admin') {
        renderErrorPage(
            403,
            'Access denied',
            'You do not have permission to view this page. It is only available to administrators.',
            'lock',
            null,
            ['label' => 'Go to my dashboard', 'href' => url('student/dashboard.php')]
        );
    }
    return $u;
}

/** Redirect signed-in users away from guest-only pages (login, register…). */
function redirectIfLoggedIn(): void
{
    $u = currentUser();
    if ($u !== null) {
        redirect(homeFor($u));
    }
}

/* ---------------------------------------------------------------------
 * Login / logout with brute-force protection
 * ------------------------------------------------------------------- */
function clientIp(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

function loginThrottled(string $email): bool
{
    dbRun('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
    $byEmail = (int) dbValue('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)', [$email]);
    $byIp    = (int) dbValue('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)', [clientIp()]);
    return $byEmail >= 5 || $byIp >= 20;
}

/**
 * Try to sign in. Returns null on success or an error message.
 */
function attemptLogin(string $email, string $password, bool $remember): ?string
{
    if (loginThrottled($email)) {
        return 'Too many failed attempts. Please wait 15 minutes and try again.';
    }
    $user = dbRow(
        "SELECT id, password, status, 'student' AS role FROM users WHERE email = ?
         UNION ALL
         SELECT id, password, status, 'admin' AS role FROM admins WHERE email = ?
         LIMIT 1",
        [$email, $email]
    );

    // Always run password_verify so response time doesn't reveal whether the email exists.
    $dummy = '$2y$10$fhe3NrNU1xD6lGIofdq.0.LEJl5a25NAqX4MyBczAMGHHDlUGMPE.';
    $ok = password_verify($password, $user['password'] ?? $dummy);

    if (!$user || !$ok) {
        dbRun('INSERT INTO login_attempts (email, ip) VALUES (?, ?)', [$email, clientIp()]);
        return 'Invalid email or password.';
    }
    if ($user['status'] === 'pending') {
        return 'Your account is pending admin approval. Please check back once it has been approved.';
    }
    if ($user['status'] !== 'active') {
        return 'This account has been disabled. Please contact support.';
    }
    if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
        $table = $user['role'] === 'admin' ? 'admins' : 'users';
        dbRun("UPDATE $table SET password = ? WHERE id = ?", [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    dbRun('DELETE FROM login_attempts WHERE email = ?', [$email]);
    loginUser((int) $user['id'], $remember, $user['role']);
    return null;
}

/** Start an authenticated session (regenerates the session id → no fixation). */
function loginUser(int $userId, bool $remember = false, string $accountType = 'student'): void
{
    session_regenerate_id(true);
    $_SESSION['uid']        = $userId;
    $_SESSION['account_type'] = $accountType === 'admin' ? 'admin' : 'student';
    $_SESSION['remember']   = $remember;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    if ($remember) {
        setcookie(session_name(), session_id(), [
            'expires'  => time() + 30 * 86400,
            'path'     => BASE_URL !== '' ? BASE_URL : '/',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

/** Destroy the session completely and start a clean one (for the goodbye toast). */
function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    }
    session_destroy();
    startSecureSession();
    session_regenerate_id(true);
    flash('success', 'You have been signed out.');
}
