<?php
require_once __DIR__ . '/../includes/auth.php';
$admin = requireAdmin();

$totalStudents = (int) dbValue('SELECT COUNT(*) FROM users');
$activeStudents = (int) dbValue("SELECT COUNT(*) FROM users WHERE status = 'active'");
$pendingStudents = (int) dbValue("SELECT COUNT(*) FROM users WHERE status = 'pending'");
$newThisMonth = (int) dbValue("SELECT COUNT(*) FROM users WHERE DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')");
$totalTx = (int) dbValue('SELECT COUNT(*) FROM transactions');
$txThisMonth = (int) dbValue("SELECT COUNT(*) FROM transactions WHERE DATE_FORMAT(date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')");
$totalVolume = (float) dbValue('SELECT COALESCE(SUM(amount),0) FROM transactions');
$openMessages = (int) dbValue("SELECT COUNT(*) FROM contact_messages WHERE status = 'open'");

$months = lastMonths(date('Y-m'), 6);
$growth = [];
$activity = [];
foreach ($months as $m) {
    [$s, $e] = monthBounds($m);
    $growth[] = (int) dbValue("SELECT COUNT(*) FROM users WHERE created_at BETWEEN ? AND ?", [$s . ' 00:00:00', $e . ' 23:59:59']);
    $activity[] = (int) dbValue('SELECT COUNT(*) FROM transactions WHERE date BETWEEN ? AND ?', [$s, $e]);
}
$topCats = dbAll(
    "SELECT c.name, SUM(t.amount) AS total FROM transactions t JOIN categories c ON c.id = t.category_id
     WHERE t.type = 'expense' AND t.date BETWEEN ? AND ? GROUP BY c.id ORDER BY total DESC LIMIT 6",
    [monthBounds(date('Y-m'))[0], monthBounds(date('Y-m'))[1]]
);
$recentUsers = dbAll('SELECT id, name, email, created_at, status FROM users ORDER BY created_at DESC LIMIT 5');
$recentMessages = dbAll('SELECT id, name, subject, created_at, status FROM contact_messages ORDER BY created_at DESC LIMIT 5');

$pageData = ['charts' => [
    chartSpec('chartGrowth', 'bar', array_map(fn($m) => monthLabel($m, true), $months), [['label' => 'New students', 'data' => $growth, 'color' => 'blue']]),
    chartSpec('chartActivity', 'area', array_map(fn($m) => monthLabel($m, true), $months), [['label' => 'Transactions logged', 'data' => $activity, 'color' => 'emerald', 'fill' => true]]),
    chartSpec('chartTopCats', 'hbar', array_column($topCats, 'name'), [['label' => 'Spent', 'data' => array_map('floatval', array_column($topCats, 'total')), 'color' => 'coral']]),
]];

$layout = 'admin';
$pageTitle = 'Admin Dashboard';
$useCharts = true;
require __DIR__ . '/../includes/header.php';
pageHead('Admin dashboard', 'Platform overview and recent activity.');
?>
<section class="grid grid-4">
    <div class="card stat"><div class="stat-top"><span class="stat-label">Students</span><span class="stat-icon navy"><?= icon('users', 20) ?></span></div>
        <div class="stat-value" data-count="<?= $totalStudents ?>"><?= (int) $totalStudents ?></div><div class="stat-meta"><?= (int) $activeStudents ?> active · <span class="up">+<?= (int) $newThisMonth ?></span> this month<?php if ($pendingStudents > 0): ?> · <a href="<?= e(url('admin/users.php?status=pending')) ?>"><?= (int) $pendingStudents ?> pending approval</a><?php endif; ?></div></div>
    <div class="card stat"><div class="stat-top"><span class="stat-label">Transactions</span><span class="stat-icon blue"><?= icon('repeat', 20) ?></span></div>
        <div class="stat-value" data-count="<?= $totalTx ?>"><?= (int) $totalTx ?></div><div class="stat-meta"><?= (int) $txThisMonth ?> logged this month</div></div>
    <div class="card stat"><div class="stat-top"><span class="stat-label">Total volume tracked</span><span class="stat-icon emerald"><?= icon('trending-up', 20) ?></span></div>
        <div class="stat-value" <?= amountAttrs($totalVolume) ?>><?= e(money($totalVolume)) ?></div><div class="stat-meta">Across all students</div></div>
    <div class="card stat"><div class="stat-top"><span class="stat-label">Open messages</span><span class="stat-icon amber"><?= icon('mail', 20) ?></span></div>
        <div class="stat-value" data-count="<?= $openMessages ?>"><?= (int) $openMessages ?></div><div class="stat-meta">From the contact form</div></div>
</section>

<section class="grid grid-main">
    <div class="card"><div class="card-head"><div><h2 class="card-title">User growth</h2><p class="card-sub">New student sign-ups, last 6 months</p></div></div>
        <div class="card-body"><div class="chart-box"><canvas id="chartGrowth" role="img" aria-label="Bar chart of new student sign-ups per month"></canvas></div></div></div>
    <div class="card"><div class="card-head"><div><h2 class="card-title">Platform activity</h2><p class="card-sub">Transactions logged per month</p></div></div>
        <div class="card-body"><div class="chart-box"><canvas id="chartActivity" role="img" aria-label="Area chart of transactions logged per month"></canvas></div></div></div>
</section>

<section class="grid grid-main">
    <div class="card"><div class="card-head"><div><h2 class="card-title">Top categories this month</h2></div></div>
        <div class="card-body"><div class="chart-box sm">
            <?php if (!$topCats): ?><div class="chart-fallback">No expenses recorded this month yet.</div><?php else: ?><canvas id="chartTopCats" role="img" aria-label="Horizontal bar chart of top spending categories"></canvas><?php endif; ?>
        </div></div></div>
    <div class="stack">
        <div class="card"><div class="card-head"><h2 class="card-title">Recently joined</h2></div>
            <div class="card-body tight">
                <?php if (!$recentUsers): ?><?= emptyState('users', 'No students yet', 'New sign-ups will appear here.') ?><?php else: ?>
                <ul class="mini-list"><?php foreach ($recentUsers as $u): ?>
                    <li><div><strong><?= e($u['name']) ?></strong><small><?= e($u['email']) ?> · <?= e(formatDate($u['created_at'], 'd M')) ?></small></div>
                        <span class="badge <?= $u['status'] === 'active' ? 'green' : ($u['status'] === 'pending' ? 'amber' : 'red') ?>"><?= $u['status'] === 'pending' ? 'Pending' : ucfirst($u['status']) ?></span></li>
                <?php endforeach; ?></ul>
                <?php endif; ?>
            </div>
        </div>
        <div class="card"><div class="card-head"><h2 class="card-title">Recent messages</h2></div>
            <div class="card-body tight">
                <?php if (!$recentMessages): ?><?= emptyState('mail', 'No messages yet', 'Contact form submissions will appear here.') ?><?php else: ?>
                <ul class="mini-list"><?php foreach ($recentMessages as $m): ?>
                    <li><div><strong><?= e($m['name']) ?></strong><small><?= e($m['subject']) ?> · <?= e(formatDate($m['created_at'], 'd M')) ?></small></div>
                        <span class="badge <?= $m['status'] === 'open' ? 'amber' : 'blue' ?>"><?= ucfirst($m['status']) ?></span></li>
                <?php endforeach; ?></ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
