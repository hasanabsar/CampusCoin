<?php
require_once __DIR__ . '/../includes/auth.php';
$user = requireStudent();
$uid  = (int) $user['id'];
$today = date('Y-m-d');

/* ---------- Period: a month (default) or a custom date range (max 366 days) ---------- */
$from = query('from');
$to   = query('to');
$custom = isValidDate($from) && isValidDate($to) && $from <= $to && (strtotime($to) - strtotime($from)) / 86400 <= 366;
if ($custom) {
    [$rs, $re] = [$from, $to];
    $ym = substr($re, 0, 7);
    $periodLabel = formatDate($rs) . ' – ' . formatDate($re);
} else {
    $ym = normalizeMonth(query('month'));
    [$rs, $re] = monthBounds($ym);
    $periodLabel = monthLabel($ym);
}

$income  = sumTransactions($uid, 'income', $rs, $re);
$expense = sumTransactions($uid, 'expense', $rs, $re);
$net     = $income - $expense;

/* Daily spending (fill gaps with zero) */
$dayMap = [];
foreach (dbAll("SELECT date, SUM(amount) AS s FROM transactions WHERE user_id = ? AND type = 'expense' AND date BETWEEN ? AND ? GROUP BY date", [$uid, $rs, $re]) as $r) {
    $dayMap[$r['date']] = (float) $r['s'];
}
$dEnd = ($re > $today && $rs <= $today) ? $today : $re;
$dayLabels = [];
$dayValues = [];
for ($d = new DateTime($rs), $stop = new DateTime($dEnd); $d <= $stop; $d->modify('+1 day')) {
    $dayLabels[] = $d->format('d M');
    $dayValues[] = $dayMap[$d->format('Y-m-d')] ?? 0.0;
}
$daysElapsed = max(1, count($dayLabels));

/* Weekly spending (ISO weeks) */
$weekLabels = [];
$weekValues = [];
foreach (dbAll("SELECT YEARWEEK(date, 1) AS yw, MIN(date) AS d0, SUM(amount) AS s FROM transactions
                WHERE user_id = ? AND type = 'expense' AND date BETWEEN ? AND ? GROUP BY yw ORDER BY yw", [$uid, $rs, $re]) as $r) {
    $weekLabels[] = 'Week of ' . date('d M', strtotime('monday this week', strtotime($r['d0'])));
    $weekValues[] = (float) $r['s'];
}

/* Category-wise, monthly trend */
$cats   = categoryBreakdown($uid, $rs, $re);
$months = lastMonths($ym, 6);
$totals = monthlyTotals($uid, $months);
$largest = dbRow("SELECT t.amount, t.description, c.name AS category FROM transactions t JOIN categories c ON c.id = t.category_id
                  WHERE t.user_id = ? AND t.type = 'expense' AND t.date BETWEEN ? AND ? ORDER BY t.amount DESC LIMIT 1", [$uid, $rs, $re]);
$hasAny = (int) dbValue('SELECT COUNT(*) FROM transactions WHERE user_id = ?', [$uid]) > 0;

$pageData = ['charts' => [
    chartSpec('chartDaily', 'line', $dayLabels, [['label' => 'Spent', 'data' => $dayValues, 'color' => 'coral', 'fill' => true]], ['maxTicks' => 10]),
    chartSpec('chartWeekly', 'bar', $weekLabels, [['label' => 'Spent', 'data' => $weekValues, 'color' => 'blue']]),
    chartSpec('chartCategory', 'doughnut', array_column($cats, 'name'), [['data' => array_map('floatval', array_column($cats, 'total'))]],
        ['colors' => array_map(fn($c) => categoryColor((int) $c['id']), $cats)]),
    chartSpec('chartIE', 'bar', ['Income', 'Expenses', 'Savings'], [['label' => 'Amount', 'data' => [$income, $expense, max(0, $net)], 'colors' => ['#10B981', '#EF4444', '#3B82F6']]]),
    chartSpec('chartMonthly', 'bar', array_map(fn($m) => monthLabel($m, true), $months), [['label' => 'Expenses', 'data' => array_map(fn($m) => $totals[$m]['expense'], $months), 'color' => 'coral']]),
    chartSpec('chartTrend', 'area', array_map(fn($m) => monthLabel($m, true), $months), [
        ['label' => 'Income', 'data' => array_map(fn($m) => $totals[$m]['income'], $months), 'color' => 'emerald'],
        ['label' => 'Expenses', 'data' => array_map(fn($m) => $totals[$m]['expense'], $months), 'color' => 'coral'],
    ]),
]];

$layout = 'student';
$pageTitle = 'Reports';
$useCharts = true;
require __DIR__ . '/../includes/header.php';

pageHead('Reports', $periodLabel,
    '<button type="button" class="btn btn-outline" data-print>' . icon('printer', 18) . ' Print</button>'
    . '<button type="button" class="btn btn-primary" data-print title="Choose “Save as PDF” in the print dialog">' . icon('file', 18) . ' Save as PDF</button>');

$chartCard = function (string $id, string $title, string $sub, string $file, string $size = '') {
    ?>
    <section class="card">
        <div class="card-head"><div><h2 class="card-title"><?= e($title) ?></h2><p class="card-sub"><?= e($sub) ?></p></div>
            <div class="chart-tools"><button type="button" class="icon-btn icon-action" data-chart-download="<?= e($id) ?>" data-filename="<?= e($file) ?>" aria-label="Download <?= e($title) ?> as image" title="Download image"><?= icon('image', 17) ?></button></div></div>
        <div class="card-body"><div class="chart-box <?= e($size) ?>"><canvas id="<?= e($id) ?>" role="img" aria-label="<?= e($title) ?> chart"></canvas></div></div>
    </section>
    <?php
};
?>
<?php if (!$hasAny): ?>
    <section class="card"><?= emptyState('bar-chart', 'No report data yet', 'Add some income and expenses and your charts will appear here.',
        '<a class="btn btn-primary" href="' . e(url('student/add-expense.php')) . '">Add expense</a> <a class="btn btn-outline" href="' . e(url('student/add-income.php')) . '">Add income</a>') ?></section>
<?php else: ?>
    <section class="card">
        <form class="report-toolbar" method="get" aria-label="Report period">
            <div class="field"><label for="month">Month</label><input id="month" name="month" type="month" class="input" max="<?= e(date('Y-m')) ?>" value="<?= e($custom ? '' : $ym) ?>"></div>
            <div class="field"><label for="from">Custom range from</label><input id="from" name="from" type="date" class="input" value="<?= e($custom ? $from : '') ?>"></div>
            <div class="field"><label for="to">to</label><input id="to" name="to" type="date" class="input" value="<?= e($custom ? $to : '') ?>"></div>
            <button class="btn btn-primary" type="submit"><?= icon('filter', 16) ?> Apply</button>
            <a class="btn btn-ghost" href="<?= e(url('student/reports.php')) ?>">Reset</a>
        </form>
    </section>

    <section class="grid grid-4">
        <div class="card stat"><div class="stat-top"><span class="stat-label">Income</span><span class="stat-icon emerald"><?= icon('arrow-down', 20) ?></span></div><div class="stat-value" <?= amountAttrs($income) ?>><?= e(money($income)) ?></div></div>
        <div class="card stat"><div class="stat-top"><span class="stat-label">Spent</span><span class="stat-icon coral"><?= icon('arrow-up', 20) ?></span></div><div class="stat-value" <?= amountAttrs($expense) ?>><?= e(money($expense)) ?></div></div>
        <div class="card stat"><div class="stat-top"><span class="stat-label">Net savings</span><span class="stat-icon blue"><?= icon('target', 20) ?></span></div><div class="stat-value" <?= amountAttrs($net) ?>><?= e(money($net)) ?></div></div>
        <div class="card stat"><div class="stat-top"><span class="stat-label">Average per day</span><span class="stat-icon amber"><?= icon('calendar', 20) ?></span></div><div class="stat-value" <?= amountAttrs(round($expense / $daysElapsed, 2)) ?>><?= e(money($expense / $daysElapsed)) ?></div>
            <div class="stat-meta"><?= $largest ? 'Largest: ' . e(money($largest['amount'])) . ' · ' . e($largest['category']) : 'No expenses in this period' ?></div></div>
    </section>

    <?php $chartCard('chartDaily', 'Daily spending', $periodLabel, 'daily-spending', 'tall'); ?>
    <div class="grid grid-2">
        <?php $chartCard('chartWeekly', 'Weekly spending', 'Grouped by week (Mon–Sun)', 'weekly-spending'); ?>
        <?php $chartCard('chartCategory', 'Category-wise spending', $periodLabel, 'category-spending'); ?>
    </div>
    <div class="grid grid-2">
        <?php $chartCard('chartIE', 'Income vs expense', $periodLabel, 'income-vs-expense'); ?>
        <?php $chartCard('chartMonthly', 'Monthly spending', 'Last 6 months', 'monthly-spending'); ?>
    </div>
    <?php $chartCard('chartTrend', '6-month trend', 'Income and expenses over time', 'six-month-trend'); ?>

    <section class="card">
        <div class="card-head"><div><h2 class="card-title">Category breakdown</h2><p class="card-sub"><?= e($periodLabel) ?></p></div></div>
        <div class="card-body flush mt-2">
            <?php if (!$cats): ?>
                <?= emptyState('pie', 'No spending in this period', 'Pick another month or date range to see a category breakdown.') ?>
            <?php else: ?>
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>Category</th><th>Transactions</th><th>Share</th><th class="text-right">Amount</th></tr></thead>
                    <tbody>
                    <?php foreach ($cats as $c): $share = $expense > 0 ? $c['total'] / $expense * 100 : 0; ?>
                        <tr>
                            <td><span class="cat-dot" style="background:<?= e(categoryColor((int) $c['id'])) ?>"></span><span class="cell-main"><?= e($c['name']) ?></span></td>
                            <td><?= (int) $c['count'] ?></td>
                            <td><div class="bar-pct"><div class="progress"><div class="progress-bar" data-width="<?= e(round($share, 1)) ?>" style="width:0"></div></div><span class="text-sm"><?= (int) round($share) ?>%</span></div></td>
                            <td class="num"><?= e(money($c['total'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
