<?php
/** JSON endpoint: suggest an expense category for a description (students only, CSRF-protected). */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/AIService.php';

if (!isPost()) {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed.'], 405);
}
$user = currentUser();
if (!$user || $user['role'] !== 'student') {
    jsonResponse(['ok' => false, 'error' => 'Please sign in.'], 401);
}
if (!csrfValid()) {
    jsonResponse(['ok' => false, 'error' => 'Invalid security token. Refresh the page.'], 403);
}

// Light per-session rate limit: 40 requests / 10 minutes (protects any paid AI quota).
$now = time();
$win = $_SESSION['suggest_window'] ?? ['start' => $now, 'count' => 0];
if ($now - $win['start'] > 600) {
    $win = ['start' => $now, 'count' => 0];
}
if (++$win['count'] > 40) {
    $_SESSION['suggest_window'] = $win;
    jsonResponse(['ok' => false, 'error' => 'Too many requests. Please wait a few minutes.'], 429);
}
$_SESSION['suggest_window'] = $win;

$body = json_decode((string) file_get_contents('php://input'), true);
$description = is_array($body) && isset($body['description']) && is_string($body['description']) ? trim($body['description']) : '';
if ($description === '') {
    jsonResponse(['ok' => false, 'error' => 'Enter a description first.'], 422);
}

$s = AIService::suggestCategory((int) $user['id'], $description);
jsonResponse(['ok' => true, 'category' => $s ? ['id' => $s['id'], 'name' => $s['name']] : null, 'source' => $s['source'] ?? null]);
