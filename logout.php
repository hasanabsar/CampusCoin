<?php
require_once __DIR__ . '/includes/auth.php';
// Logout only via POST + CSRF token (prevents forced-logout attacks).
if (isPost() && csrfValid()) {
    logoutUser();
    redirect('login.php');
}
redirect('index.php');
