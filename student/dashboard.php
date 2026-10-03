<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/tips.php';
$user = requireStudent();
$uid  = (int) $user['id'];

$ym = normalizeMonth(query('month'));
[$start, $end] = monthBounds($ym);
$prevYm = shiftMonth($ym, -1);
$nextYm = shiftMonth($ym, 1);
$isCurrent = $ym === date('Y-m');

$income   = sumTransactions($uid, 'income', $start, $end);
$expense  = sumTransactions($uid, 'expense', $start, $end);
$savings  = $income - $expense;
$balance  = getBalance($uid);
$goal     = (float) $user['monthly_savings_goal'];
[$pStart, $pEnd] = monthBounds($prevYm);
$prevExpense = sumTransactions($uid, 'expense', $pStart, $pEnd);

$months  = lastMonths($ym, 6);
$totals  = monthlyTotals($uid, $months);
$cats    = categoryBreakdown($uid, $start, $end);
$budgets = getBudgetUsage($uid, $ym);
$recent  = dbAll(
    'SELECT t.id, t.amount, t.type, t.date, t.description, c.name AS category, c.id AS category_id
     FROM transactions t JOIN categories c ON c.id = t.category_id WHERE t.user_id = ?
     ORDER BY t.date DESC, t.id DESC LIMIT 6',
    [$uid]
);
$tips = buildSavingTips($uid);
$tip = $tips[0];
$announcements = getActiveAnnouncements(2);
$hasAny = (int) dbValue('SELECT COUNT(*) FROM transactions WHERE user_id = ?', [$uid]) > 0;

$pageData = ['charts' => [
    chartSpec('chartIncomeExpense', 'bar', array_map(fn($m) => monthLabel($m, true), $months), [
        ['label' => 'Income', 'data' => array_map(fn($m) => $totals[$m]['income'], $months), 'color' => 'emerald'],
        ['label' => 'Expenses', 'data' => array_map(fn($m) => $totals[$m]['expense'], $months), 'color' => 'coral'],
    ]),
    chartSpec('chartCategories', 'doughnut', array_column($cats, 'name'), [['data' => array_map('floatval', array_column($cats, 'total'))]],
        ['colors' => array_map(fn($c) => categoryColor((int) $c['id']), $cats)]),
]];

$layout = 'student';
$pageTitle = 'Dashboard';
$useCharts = true;
require __DIR__ . '/../includes/header.php';

pageHead(
    'Hi ' . explode(' ', $user['name'])[0] . ', here is your money',
    'Overview for ' . monthLabel($ym),
    '<div class="month-nav" aria-label="Month">'
    . '<a href="?month=' . e($prevYm) . '" aria-label="Previous month">' . icon('chevron-left', 18) . '</a>'
    . '<span>' . e(monthLabel($ym, true)) . '</span>'
    . ($isCurrent ? '<span class="text-muted" aria-hidden="true">' . icon('chevron-right', 18) . '</span>' : '<a href="?month=' . e($nextYm) . '" aria-label="Next month">' . icon('chevron-right', 18) . '</a>')
    . '</div>'
);
?>
<?php foreach ($announcements as $a): ?>
    <div class="announce" role="note">
        <span class="tip-icon"><?= icon('megaphone', 20) ?></span>
        <div><strong><?= e($a['title']) ?></strong><p><?= e($a['description']) ?></p></div>
    </div>
<?php endforeach; ?>

<!-- 1. Current balance -->
<section class="balance-card" aria-label="Remaining balance">
    <div>
        <div class="balance-label">Remaining balance</div>
        <div class="balance-value" <?= amountAttrs($balance) ?>><?= e(money($balance)) ?></div>
        <div class="balance-sub">
            <span class="pill">In <?= e(money($income, true)) ?></span>
            <span class="pill">Out <?= e(money(-$expense)) ?></span>
            <span class="pill"><?= e(monthLabel($ym, true)) ?></span>
        </div>
    </div>
    <div class="balance-actions">
        <a class="btn btn-accent" href="<?= e(url('student/add-income.php')) ?>"><?= icon('plus', 18) ?> Add income</a>
        <a class="btn btn-ghost-light" href="<?= e(url('student/add-expense.php')) ?>"><?= icon('minus-circle', 18) ?> Add expense</a>
    </div>
</section>

<!-- 2–3. Income, expenses, monthly savings -->
<section class="grid grid-3" aria-label="Monthly summary">
    <div class="card stat">
        <div class="stat-top"><span class="stat-label">Total income</span><span class="stat-icon emerald"><?= icon('arrow-down', 20) ?></span></div>
        <div class="stat-value" <?= amountAttrs($income) ?>><?= e(money($income)) ?></div>
        <div class="stat-meta">Earned in <?= e(monthLabel($ym, true)) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-top"><span class="stat-label">Total expenses</span><span class="stat-icon coral"><?= icon('arrow-up', 20) ?></span></div>
        <div class="stat-value" <?= amountAttrs($expense) ?>><?= e(money($expense)) ?></div>
        <div class="stat-meta">
            <?php if ($prevExpense > 0): $chg = ($expense / $prevExpense - 1) * 100; ?>
                <span class="<?= $chg > 0 ? 'down' : 'up' ?>"><?= $chg > 0 ? '▲' : '▼' ?> <?= e(abs(round($chg))) ?>%</span> vs <?= e(monthLabel($prevYm, true)) ?>
            <?php else: ?>Spent in <?= e(monthLabel($ym, true)) ?><?php endif; ?>
        </div>
    </div>
    <div class="card stat">
        <div class="stat-top"><span class="stat-label">Monthly savings</span><span class="stat-icon blue"><?= icon('target', 20) ?></span></div>
        <div class="stat-value" <?= amountAttrs($savings) ?>><?= e(money($savings)) ?></div>
        <?php if ($goal > 0): $gl = budgetLevel(max(0, $savings), $goal); $reached = $savings >= $goal; ?>
            <div class="stat-meta"><?= $reached ? '<span class="up">Goal reached</span>' : 'Goal ' . e(money($goal)) . ' · ' . (int) round(max(0, min(100, $gl['pct']))) . '%' ?></div>
            <div class="progress mt-2" role="progressbar" aria-label="Savings goal progress" aria-valuenow="<?= (int) min(100, round($gl['pct'])) ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" data-width="<?= e(min(100, round($gl['pct'], 1))) ?>" style="width:0"></div></div>
        <?php else: ?>
            <div class="stat-meta">Income − expenses. <a href="<?= e(url('student/profile.php')) ?>">Set a goal</a></div>
        <?php endif; ?>
    </div>
</section>

<!-- 5. Charts -->
<section class="grid grid-main">
    <div class="card">
        <div class="card-head"><div><h2 class="card-title">Income vs expenses</h2><p class="card-sub">Last 6 months</p></div></div>
        <div class="card-body"><div class="chart-box"><canvas id="chartIncomeExpense" role="img" aria-label="Bar chart of monthly income versus expenses"></canvas></div></div>
    </div>
    <div class="card">
        <div class="card-head"><div><h2 class="card-title">Spending by category</h2><p class="card-sub"><?= e(monthLabel($ym)) ?></p></div></div>
        <div class="card-body"><div class="chart-box"><canvas id="chartCategories" role="img" aria-label="Doughnut chart of spending by category"></canvas></div></div>
    </div>
</section>

<!-- 4 & 7. Budget progress, top category, saving tip -->
<section class="grid grid-main">
    <div class="card">
        <div class="card-head"><div><h2 class="card-title">Budget progress</h2><p class="card-sub"><?= e(monthLabel($ym)) ?></p></div>
            <a class="btn btn-outline btn-sm" href="<?= e(url('student/budgets.php?month=' . $ym)) ?>">Manage</a></div>
        <div class="card-body tight">
            <?php if (!$budgets): ?>
                <?= emptyState('wallet', 'No budgets yet', 'Set monthly limits per category and CampusCoin will warn you before you overspend.', '<a class="btn btn-primary btn-sm" href="' . e(url('student/budgets.php')) . '">Set a budget</a>') ?>
            <?php else: foreach ($budgets as $b): ?>
                <div class="budget-row">
                    <div class="row between"><span class="budget-name"><?= e($b['name']) ?></span><span><?= levelBadge($b['level']) ?></span></div>
                    <?= progressBar($b['level']) ?>
                    <div class="row between mt-2"><span class="budget-figures"><?= e(money($b['used'])) ?> of <?= e(money($b['limit_amount'])) ?></span>
                        <span class="budget-figures"><?= $b['remaining'] >= 0 ? e(money($b['remaining'])) . ' left' : e(money(-$b['remaining'])) . ' over' ?></span></div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <div class="stack">
        <div class="card">
            <div class="card-body">
                <?php if ($cats): $top = $cats[0]; ?>
                    <div class="top-cat">
                        <span class="stat-icon amber"><?= icon('tag', 22) ?></span>
                        <div><div class="stat-label">Top spending category</div>
                            <div class="stat-value" style="font-size:20px"><?= e($top['name']) ?></div>
                            <div class="stat-meta"><?= e(money($top['total'])) ?> · <?= $expense > 0 ? (int) round($top['total'] / $expense * 100) : 0 ?>% of spending</div></div>
                    </div>
                <?php else: ?>
                    <div class="top-cat"><span class="stat-icon amber"><?= icon('tag', 22) ?></span><div><div class="stat-label">Top spending category</div><div class="stat-meta">Nothing spent in <?= e(monthLabel($ym, true)) ?> yet.</div></div></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card tip-card">
            <div class="card-body">
                <span class="tip-icon"><?= icon($tip['icon'], 22) ?></span>
                <h3><?= e($tip['title']) ?></h3>
                <p><?= e($tip['text']) ?></p>
                <p class="mt-2"><a href="<?= e(url('student/saving-tips.php')) ?>"><strong>More saving tips</strong></a></p>
            </div>
        </div>
    </div>
</section>

<!-- 6. Recent transactions -->
<section class="card">
    <div class="card-head"><div><h2 class="card-title">Recent transactions</h2></div>
        <a class="btn btn-outline btn-sm" href="<?= e(url('student/transactions.php')) ?>">View all</a></div>
    <div class="card-body flush mt-2">
        <?php if (!$recent): ?>
            <?= emptyState('inbox', 'No transactions yet', 'Start tracking your first expense to see your financial insights.',
                '<a class="btn btn-primary" href="' . e(url('student/add-expense.php')) . '">Add expense</a> <a class="btn btn-outline" href="' . e(url('student/add-income.php')) . '">Add income</a>') ?>
        <?php else: ?>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Category</th><th>Description</th><th>Date</th><th class="text-right">Amount</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $t): ?>
                    <tr>
                        <td><div class="row"><span class="tx-icon <?= e($t['type']) ?>"><?= icon($t['type'] === 'income' ? 'arrow-down' : 'arrow-up', 18) ?></span><span class="cell-main"><?= e($t['category']) ?></span></div></td>
                        <td><?= e($t['description'] ?: '—') ?></td>
                        <td><?= e(formatDate($t['date'])) ?></td>
                        <td class="num <?= $t['type'] === 'income' ? 'amt-income' : 'amt-expense' ?>"><?= e($t['type'] === 'income' ? money($t['amount'], true) : money(-$t['amount'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
