<?php
require_once __DIR__ . '/../includes/auth.php';
$admin = requireAdmin();

$months = lastMonths(date('Y-m'), 12);
$growth = [];
$income = [];
$expense = [];
foreach ($months as $m) {
    [$s, $e] = monthBounds($m);
    $growth[] = (int) dbValue("SELECT COUNT(*) FROM users WHERE created_at BETWEEN ? AND ?", [$s . ' 00:00:00', $e . ' 23:59:59']);
    $income[] = (float) dbValue("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type = 'income' AND date BETWEEN ? AND ?", [$s, $e]);
    $expense[] = (float) dbValue("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type = 'expense' AND date BETWEEN ? AND ?", [$s, $e]);
}
$byYear = dbAll("SELECT academic_year, COUNT(*) AS n FROM users WHERE academic_year IS NOT NULL AND academic_year <> '' GROUP BY academic_year ORDER BY academic_year");
$topCats = dbAll("SELECT c.name, SUM(t.amount) AS total, COUNT(*) AS n FROM transactions t JOIN categories c ON c.id = t.category_id WHERE t.type = 'expense' GROUP BY c.id ORDER BY total DESC LIMIT 8");
$avgPerStudent = (int) dbValue('SELECT COUNT(*) FROM users') > 0
    ? (float) dbValue("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='expense'") / (int) dbValue('SELECT COUNT(*) FROM users')
    : 0.0;
$activeRate = (int) dbValue('SELECT COUNT(*) FROM users') > 0
    ? round((int) dbValue("SELECT COUNT(DISTINCT user_id) FROM transactions WHERE date >= (CURDATE() - INTERVAL 30 DAY)") / (int) dbValue('SELECT COUNT(*) FROM users') * 100)
    : 0;

$pageData = ['charts' => [
    chartSpec('sGrowth', 'bar', array_map(fn($m) => monthLabel($m, true), $months), [['label' => 'New students', 'data' => $growth, 'color' => 'blue']]),
    chartSpec('sIE', 'line', array_map(fn($m) => monthLabel($m, true), $months), [
        ['label' => 'Income', 'data' => $income, 'color' => 'emerald'], ['label' => 'Expenses', 'data' => $expense, 'color' => 'coral'],
    ]),
    chartSpec('sYear', 'doughnut', array_column($byYear, 'academic_year'), [['data' => array_map('intval', array_column($byYear, 'n'))]]),
    chartSpec('sCats', 'hbar', array_column($topCats, 'name'), [['label' => 'Spent', 'data' => array_map('floatval', array_column($topCats, 'total')), 'color' => 'navy']]),
]];

$layout = 'admin';
$pageTitle = 'Statistics';
$useCharts = true;
require __DIR__ . '/../includes/header.php';
pageHead('Platform statistics', '12-month overview across all students.');
?>
<section class="grid grid-3">
    <div class="card stat"><div class="stat-top"><span class="stat-label">Avg. spend / student</span><span class="stat-icon navy"><?= icon('users', 20) ?></span></div><div class="stat-value" <?= amountAttrs(round($avgPerStudent, 2)) ?>><?= e(money($avgPerStudent)) ?></div><div class="stat-meta">All-time average</div></div>
    <div class="card stat"><div class="stat-top"><span class="stat-label">Active last 30 days</span><span class="stat-icon emerald"><?= icon('activity', 20) ?></span></div><div class="stat-value" data-count="<?= (int) $activeRate ?>">0</div><div class="stat-meta">% of students with at least one entry</div></div>
    <div class="card stat"><div class="stat-top"><span class="stat-label">Categories tracked</span><span class="stat-icon blue"><?= icon('tag', 20) ?></span></div><div class="stat-value" data-count="<?= (int) dbValue('SELECT COUNT(*) FROM categories') ?>">0</div><div class="stat-meta">Default + student-created</div></div>
</section>

<section class="grid grid-main">
    <div class="card"><div class="card-head"><div><h2 class="card-title">Student growth</h2><p class="card-sub">Last 12 months</p></div></div><div class="card-body"><div class="chart-box"><canvas id="sGrowth" role="img" aria-label="Bar chart of student growth"></canvas></div></div></div>
    <div class="card"><div class="card-head"><div><h2 class="card-title">Income vs expenses</h2><p class="card-sub">Platform-wide, last 12 months</p></div></div><div class="card-body"><div class="chart-box"><canvas id="sIE" role="img" aria-label="Line chart of income versus expenses"></canvas></div></div></div>
</section>

<section class="grid grid-main">
    <div class="card"><div class="card-head"><div><h2 class="card-title">Students by academic year</h2></div></div>
        <div class="card-body"><div class="chart-box sm"><?php if (!$byYear): ?><div class="chart-fallback">No academic year data yet.</div><?php else: ?><canvas id="sYear" role="img" aria-label="Doughnut chart of students by academic year"></canvas><?php endif; ?></div></div></div>
    <div class="card"><div class="card-head"><div><h2 class="card-title">Top categories platform-wide</h2></div></div>
        <div class="card-body"><div class="chart-box sm"><?php if (!$topCats): ?><div class="chart-fallback">No expense data yet.</div><?php else: ?><canvas id="sCats" role="img" aria-label="Horizontal bar chart of top categories"></canvas><?php endif; ?></div></div></div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
