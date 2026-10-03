<?php
/**
 * CampusCoin — shared helper functions.
 * Grouped: output/routing, flash + CSRF, validation, formatting, layout helpers,
 * category + budget helpers, notifications.
 */

require_once __DIR__ . '/icons.php';

/* =====================================================================
 * Output & routing
 * ===================================================================== */

/** Escape output (XSS protection). Use for EVERY dynamic value in HTML. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Absolute (root-relative) URL for an app path. */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** URL for a static asset with a cache-busting version. */
function asset(string $path): string
{
    $file = ROOT_PATH . '/assets/' . $path;
    return url('assets/' . $path) . '?v=' . (is_file($file) ? filemtime($file) : 1);
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/** Redirect back to the current URL (post/redirect/get). */
function redirectSelf(): void
{
    $uri = $_SERVER['REQUEST_URI'] ?? url();
    header('Location: ' . (str_starts_with($uri, '/') ? $uri : url()));
    exit;
}

function isPost(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Trimmed POST string. */
function post(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

/** Trimmed GET string. */
function query(string $key, string $default = ''): string
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function jsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** JSON safe for embedding in a <script> block. */
function jsonForHtml($value): string
{
    return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
}

/* =====================================================================
 * Flash messages (rendered as toasts) & CSRF
 * ===================================================================== */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function pullFlash(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function csrfValid(): bool
{
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($token) && $token !== '' && hash_equals(csrfToken(), $token);
}

/** Call at the top of every POST handler. Redirects back on a bad token. */
function requireCsrf(): void
{
    if (!csrfValid()) {
        flash('error', 'Your session expired. Please try again.');
        redirectSelf();
    }
}

/* =====================================================================
 * Validation & parsing
 * ===================================================================== */

/** Parse a money amount such as "1,250.50". Returns null if invalid or <= 0. */
function parseAmount(string $raw): ?float
{
    $raw = str_replace([',', ' '], '', $raw);
    if (!preg_match('/^\d{1,9}(\.\d{1,2})?$/', $raw)) {
        return null;
    }
    $v = (float) $raw;
    return $v > 0 ? $v : null;
}

function isValidDate(string $d): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt !== false && $dt->format('Y-m-d') === $d;
}

function isValidEmail(string $email): bool
{
    return strlen($email) <= 190 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/** Returns an error message, or null when the password is acceptable. */
function validatePassword(string $p): ?string
{
    if (strlen($p) < 8) {
        return 'Password must be at least 8 characters long.';
    }
    if (strlen($p) > 72) {
        return 'Password must be 72 characters or fewer.';
    }
    if (!preg_match('/[A-Za-z]/', $p) || !preg_match('/\d/', $p)) {
        return 'Password must include at least one letter and one number.';
    }
    return null;
}

function academicYears(): array
{
    return ['1st Year', '2nd Year', '3rd Year', '4th Year', 'Final Year', 'Postgraduate', 'Other'];
}

/** Normalise a YYYY-MM string (falls back to the current month). */
function normalizeMonth(?string $m): string
{
    return ($m && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m)) ? $m : date('Y-m');
}

/** [first day, last day] of a YYYY-MM month. */
function monthBounds(string $ym): array
{
    $start = $ym . '-01';
    return [$start, date('Y-m-t', strtotime($start))];
}

function monthLabel(string $ym, bool $short = false): string
{
    return date($short ? 'M Y' : 'F Y', strtotime($ym . '-01'));
}

function shiftMonth(string $ym, int $delta): string
{
    return date('Y-m', strtotime($ym . '-01 ' . ($delta >= 0 ? '+' : '') . $delta . ' month'));
}

/** Ascending list of the last $n months ending at $endYm. */
function lastMonths(string $endYm, int $n): array
{
    $out = [];
    for ($i = $n - 1; $i >= 0; $i--) {
        $out[] = shiftMonth($endYm, -$i);
    }
    return $out;
}

/* =====================================================================
 * Formatting
 * ===================================================================== */

function money($amount, bool $signed = false): string
{
    $n   = (float) $amount;
    $abs = abs($n);
    $txt = (floor($abs) == $abs) ? number_format($abs, 0) : number_format($abs, 2);
    $sign = $n < 0 ? '-' : ($signed && $n > 0 ? '+' : '');
    return $sign . CURRENCY . ' ' . $txt;
}

function formatDate(?string $d, string $fmt = 'd M Y'): string
{
    return $d ? date($fmt, strtotime($d)) : '';
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $out !== '' ? $out : 'U';
}

/** Stable colour per category id. */
function categoryColor(int $id): string
{
    static $palette = ['#10B981', '#3B82F6', '#F59E0B', '#8B5CF6', '#EF4444', '#14B8A6', '#EC4899', '#6366F1', '#F97316', '#0EA5E9', '#84CC16', '#64748B'];
    return $palette[$id % count($palette)];
}

function avatarHtml(array $user, int $size = 40): string
{
    $style = 'width:' . $size . 'px;height:' . $size . 'px;font-size:' . round($size * 0.38) . 'px';
    if (!empty($user['avatar']) && is_file(AVATAR_DIR . '/' . basename($user['avatar']))) {
        return '<span class="avatar" style="' . $style . '"><img src="' . e(url('assets/uploads/avatars/' . basename($user['avatar']))) . '" alt="' . e($user['name'] ?? 'Profile photo') . '"></span>';
    }
    return '<span class="avatar" style="' . $style . '" aria-hidden="true">' . e(initials($user['name'] ?? 'U')) . '</span>';
}

/* =====================================================================
 * Layout helpers
 * ===================================================================== */

/** Page heading block used at the top of every app page. */
function pageHead(string $title, string $subtitle = '', string $actionsHtml = ''): void
{
    echo '<div class="page-head"><div><h1>' . e($title) . '</h1>';
    if ($subtitle !== '') {
        echo '<p>' . e($subtitle) . '</p>';
    }
    echo '</div>';
    if ($actionsHtml !== '') {
        echo '<div class="page-actions">' . $actionsHtml . '</div>';
    }
    echo '</div>';
}

/** Friendly empty state. $actionHtml is trusted HTML. */
function emptyState(string $icon, string $title, string $text, string $actionHtml = ''): string
{
    return '<div class="empty"><div class="empty-icon">' . icon($icon, 30) . '</div><h3>' . e($title) . '</h3><p>' . e($text) . '</p>'
        . ($actionHtml !== '' ? '<div class="empty-action">' . $actionHtml . '</div>' : '') . '</div>';
}

/** Standalone error page (403 / 404 / 500 / database). Ends the request. */
function renderErrorPage(int $code, string $title, string $message, string $iconName = 'alert', ?string $detail = null, ?array $action = null): void
{
    http_response_code($code);
    $action = $action ?? ['label' => 'Back to home', 'href' => url('index.php')];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/responsive.css')) ?>">
</head>
<body class="error-body">
<main class="error-page">
    <a class="error-logo" href="<?= e(url('index.php')) ?>"><?= logoFull('dark', 34) ?></a>
    <div class="error-card">
        <div class="error-code"><?= (int) $code ?></div>
        <div class="empty-icon"><?= icon($iconName, 30) ?></div>
        <h1><?= e($title) ?></h1>
        <p><?= e($message) ?></p>
        <?php if ($detail): ?><pre class="error-detail"><?= e($detail) ?></pre><?php endif; ?>
        <a class="btn btn-primary" href="<?= e($action['href']) ?>"><?= e($action['label']) ?></a>
    </div>
</main>
</body>
</html>
    <?php
    exit;
}

/** Pagination math. */
function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page  = min(max(1, $page), $pages);
    return ['page' => $page, 'pages' => $pages, 'per' => $perPage, 'offset' => ($page - 1) * $perPage, 'total' => $total];
}

function renderPagination(array $p, array $params = []): string
{
    if ($p['pages'] <= 1) {
        return '';
    }
    $link = function (int $n, string $label, bool $active = false, bool $disabled = false, string $aria = '') use ($params): string {
        if ($disabled) {
            return '<span class="page-link disabled">' . $label . '</span>';
        }
        $qs = http_build_query(array_merge($params, ['page' => $n]));
        return '<a class="page-link' . ($active ? ' active' : '') . '" href="?' . e($qs) . '"'
            . ($active ? ' aria-current="page"' : '') . ($aria ? ' aria-label="' . e($aria) . '"' : '') . '>' . $label . '</a>';
    };
    $html = '<nav class="pagination" aria-label="Pagination">';
    $html .= $link($p['page'] - 1, icon('chevron-left', 16), false, $p['page'] <= 1, 'Previous page');
    $start = max(1, $p['page'] - 2);
    $end   = min($p['pages'], $p['page'] + 2);
    for ($i = $start; $i <= $end; $i++) {
        $html .= $link($i, (string) $i, $i === $p['page']);
    }
    $html .= $link($p['page'] + 1, icon('chevron-right', 16), false, $p['page'] >= $p['pages'], 'Next page');
    return $html . '</nav>';
}

/** Build a chart specification consumed by assets/js/dashboard.js. */
function chartSpec(string $id, string $type, array $labels, array $datasets, array $opts = []): array
{
    return array_merge(['id' => $id, 'type' => $type, 'labels' => $labels, 'datasets' => $datasets], $opts);
}

/* =====================================================================
 * Categories
 * ===================================================================== */

/**
 * Categories a student may pick: active defaults + their own.
 * $includeId lets an edit form keep a (now disabled) category selectable.
 */
function getCategories(int $userId, string $type, ?int $includeId = null): array
{
    $sql = 'SELECT id, name, type, user_id, is_default, is_active FROM categories
            WHERE type = ? AND ((user_id IS NULL AND is_default = 1 AND is_active = 1) OR user_id = ?';
    $params = [$type, $userId];
    if ($includeId) {
        $sql .= ' OR id = ?';
        $params[] = $includeId;
    }
    return dbAll($sql . ') ORDER BY is_default DESC, name', $params);
}

/** Verify a category is usable by this student for the given type. */
function findUsableCategory(int $userId, int $categoryId, string $type): ?array
{
    return dbRow(
        'SELECT id, name, type, user_id, is_default FROM categories
         WHERE id = ? AND type = ? AND ((user_id IS NULL AND is_default = 1 AND is_active = 1) OR user_id = ?)',
        [$categoryId, $type, $userId]
    );
}

/* =====================================================================
 * Finance calculations
 * ===================================================================== */

function sumTransactions(int $userId, string $type, ?string $from = null, ?string $to = null): float
{
    $sql = 'SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = ? AND type = ?';
    $params = [$userId, $type];
    if ($from !== null && $to !== null) {
        $sql .= ' AND date BETWEEN ? AND ?';
        array_push($params, $from, $to);
    }
    return (float) dbValue($sql, $params);
}

function getBalance(int $userId): float
{
    return sumTransactions($userId, 'income') - sumTransactions($userId, 'expense');
}

/** Spending (or income) per category within a date range, largest first. */
function categoryBreakdown(int $userId, string $from, string $to, string $type = 'expense'): array
{
    return dbAll(
        'SELECT c.id, c.name, SUM(t.amount) AS total, COUNT(*) AS count
         FROM transactions t JOIN categories c ON c.id = t.category_id
         WHERE t.user_id = ? AND t.type = ? AND t.date BETWEEN ? AND ?
         GROUP BY c.id, c.name ORDER BY total DESC',
        [$userId, $type, $from, $to]
    );
}

/** Income and expense totals per month for the given list of YYYY-MM keys. */
function monthlyTotals(int $userId, array $months): array
{
    $first = $months[0] . '-01';
    $last  = date('Y-m-t', strtotime(end($months) . '-01'));
    $rows = dbAll(
        "SELECT DATE_FORMAT(date, '%Y-%m') AS ym, type, SUM(amount) AS total
         FROM transactions WHERE user_id = ? AND date BETWEEN ? AND ? GROUP BY ym, type",
        [$userId, $first, $last]
    );
    $out = [];
    foreach ($months as $m) {
        $out[$m] = ['income' => 0.0, 'expense' => 0.0];
    }
    foreach ($rows as $r) {
        if (isset($out[$r['ym']])) {
            $out[$r['ym']][$r['type']] = (float) $r['total'];
        }
    }
    return $out;
}

/** Budget usage state: 0-70 ok, 70-90 warn, 90-100 near, >100 over. */
function budgetLevel(float $used, float $limit): array
{
    $pct = $limit > 0 ? ($used / $limit) * 100 : 0;
    if ($pct > 100) {
        [$key, $label] = ['over', 'Exceeded'];
    } elseif ($pct >= 90) {
        [$key, $label] = ['near', 'Near limit'];
    } elseif ($pct >= 70) {
        [$key, $label] = ['warn', 'Warning'];
    } else {
        [$key, $label] = ['ok', 'On track'];
    }
    return ['pct' => $pct, 'key' => $key, 'label' => $label];
}

/** Budgets for a month with the amount already spent in each category. */
function getBudgetUsage(int $userId, string $ym): array
{
    [$start, $end] = monthBounds($ym);
    $rows = dbAll(
        "SELECT b.id, b.category_id, b.limit_amount, c.name,
                COALESCE((SELECT SUM(t.amount) FROM transactions t
                          WHERE t.user_id = b.user_id AND t.category_id = b.category_id
                            AND t.type = 'expense' AND t.date BETWEEN ? AND ?), 0) AS used
         FROM budgets b JOIN categories c ON c.id = b.category_id
         WHERE b.user_id = ? AND b.month = ? ORDER BY c.name",
        [$start, $end, $userId, $ym]
    );
    foreach ($rows as &$r) {
        $r['limit_amount'] = (float) $r['limit_amount'];
        $r['used']         = (float) $r['used'];
        $r['remaining']    = $r['limit_amount'] - $r['used'];
        $r['level']        = budgetLevel($r['used'], $r['limit_amount']);
    }
    return $rows;
}

/* =====================================================================
 * Notifications (computed on the fly — nothing to store)
 * ===================================================================== */

function getActiveAnnouncements(int $limit = 3): array
{
    return dbAll("SELECT id, title, description, created_at FROM announcements WHERE status = 'active' ORDER BY created_at DESC, id DESC LIMIT " . (int) $limit);
}

function getNotifications(int $userId): array
{
    static $cache = [];
    if (isset($cache[$userId])) {
        return $cache[$userId];
    }
    $items = [];
    foreach (getBudgetUsage($userId, date('Y-m')) as $b) {
        $lvl = $b['level'];
        if ($lvl['key'] === 'ok') {
            continue;
        }
        $pct = (int) round($lvl['pct']);
        $items[] = [
            'type'  => $lvl['key'] === 'over' ? 'error' : 'warning',
            'icon'  => 'alert',
            'title' => $lvl['key'] === 'over' ? $b['name'] . ' budget exceeded' : $b['name'] . ' budget at ' . $pct . '%',
            'text'  => 'You have used ' . money($b['used']) . ' of ' . money($b['limit_amount']) . ' this month.',
            'href'  => url('student/budgets.php'),
        ];
    }
    foreach (getActiveAnnouncements(3) as $a) {
        $items[] = ['type' => 'info', 'icon' => 'megaphone', 'title' => $a['title'], 'text' => mb_strimwidth($a['description'], 0, 90, '…'), 'href' => url('student/dashboard.php')];
    }
    return $cache[$userId] = $items;
}

/* =====================================================================
 * Small view helpers
 * ===================================================================== */

/** data-* attributes for the animated number counter (see app.js). */
function amountAttrs($n): string
{
    $n = (float) $n;
    $dec = (floor(abs($n)) == abs($n)) ? 0 : 2;
    return 'data-count="' . e(round($n, 2)) . '" data-prefix="' . e(CURRENCY . ' ') . '" data-decimals="' . $dec . '"';
}

/** Coloured progress bar for a budgetLevel() result. */
function progressBar(array $level): string
{
    $pct = min(100, max(0, $level['pct']));
    return '<div class="progress is-' . e($level['key']) . '" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . (int) round($pct)
        . '"><div class="progress-bar" data-width="' . e(round($pct, 1)) . '" style="width:0"></div></div>';
}

/** Badge for a budget level. */
function levelBadge(array $level): string
{
    $tone = ['ok' => 'green', 'warn' => 'amber', 'near' => 'amber', 'over' => 'red'][$level['key']] ?? 'gray';
    return '<span class="badge ' . $tone . '">' . e($level['label']) . '</span>';
}
