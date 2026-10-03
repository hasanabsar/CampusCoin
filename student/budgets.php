<?php
require_once __DIR__ . '/../includes/auth.php';
$user = requireStudent();
$uid  = (int) $user['id'];

$ym = normalizeMonth(post('month') ?: query('month'));

if (isPost()) {
    requireCsrf();
    $action = post('action');
    if ($action === 'save') {
        $cat = findUsableCategory($uid, (int) post('category_id'), 'expense');
        $limit = parseAmount(post('limit_amount'));
        if (!$cat) {
            flash('error', 'Please choose a valid expense category.');
        } elseif ($limit === null) {
            flash('error', 'Enter a valid budget amount greater than zero.');
        } else {
            dbRun('INSERT INTO budgets (user_id, category_id, month, limit_amount) VALUES (?, ?, ?, ?)
                   ON DUPLICATE KEY UPDATE limit_amount = VALUES(limit_amount)', [$uid, $cat['id'], $ym, $limit]);
            flash('success', "Budget for {$cat['name']} saved.");
        }
    } elseif ($action === 'delete') {
        $n = dbRun('DELETE FROM budgets WHERE id = ? AND user_id = ?', [(int) post('id'), $uid])->rowCount();
        $n ? flash('success', 'Budget removed.') : flash('error', 'Unable to remove that budget.');
    } elseif ($action === 'copy') {
        $n = dbRun('INSERT IGNORE INTO budgets (user_id, category_id, month, limit_amount)
                    SELECT user_id, category_id, ?, limit_amount FROM budgets WHERE user_id = ? AND month = ?', [$ym, $uid, shiftMonth($ym, -1)])->rowCount();
        $n ? flash('success', "Copied {$n} budget" . ($n === 1 ? '' : 's') . ' from ' . monthLabel(shiftMonth($ym, -1)) . '.') : flash('info', 'Nothing to copy — no new budgets found for the previous month.');
    }
    header('Location: ' . url('student/budgets.php?month=' . $ym));
    exit;
}

$budgets = getBudgetUsage($uid, $ym);
$totalLimit = array_sum(array_column($budgets, 'limit_amount'));
$totalUsed  = array_sum(array_column($budgets, 'used'));
$overall    = budgetLevel($totalUsed, $totalLimit);
$expenseCats = getCategories($uid, 'expense');
$prevYm = shiftMonth($ym, -1);
$nextYm = shiftMonth($ym, 1);

$layout = 'student';
$pageTitle = 'Budgets';
require __DIR__ . '/../includes/header.php';

pageHead('Budgets', 'Set monthly limits per category and watch your progress.',
    '<div class="month-nav"><a href="?month=' . e($prevYm) . '" aria-label="Previous month">' . icon('chevron-left', 18) . '</a><span>' . e(monthLabel($ym, true)) . '</span><a href="?month=' . e($nextYm) . '" aria-label="Next month">' . icon('chevron-right', 18) . '</a></div>'
    . '<form method="post" class="inline">' . csrfField() . '<input type="hidden" name="action" value="copy"><input type="hidden" name="month" value="' . e($ym) . '"><button type="submit" class="btn btn-outline">' . icon('copy', 18) . ' Copy last month</button></form>'
    . '<button type="button" class="btn btn-primary" data-modal-open="budgetModal">' . icon('plus', 18) . ' Set budget</button>');
?>
<?php if (!$budgets): ?>
    <section class="card"><?= emptyState('wallet', 'No budgets for ' . monthLabel($ym), 'Budgets make overspending visible before it happens. Start with the categories you spend on most.',
        '<button type="button" class="btn btn-primary" data-modal-open="budgetModal">Set your first budget</button>') ?></section>
<?php else: ?>
    <section class="card">
        <div class="card-body">
            <div class="row between wrap">
                <div><div class="stat-label">Total budgeted</div><div class="stat-value" <?= amountAttrs($totalLimit) ?>><?= e(money($totalLimit)) ?></div></div>
                <div><div class="stat-label">Spent</div><div class="stat-value amt-expense" <?= amountAttrs($totalUsed) ?>><?= e(money($totalUsed)) ?></div></div>
                <div><div class="stat-label"><?= $totalLimit - $totalUsed >= 0 ? 'Remaining' : 'Over budget' ?></div><div class="stat-value <?= $totalLimit - $totalUsed >= 0 ? 'amt-income' : 'amt-expense' ?>"><?= e(money(abs($totalLimit - $totalUsed))) ?></div></div>
                <div><?= levelBadge($overall) ?></div>
            </div>
            <div class="mt-3"><?= progressBar($overall) ?></div>
        </div>
    </section>

    <section class="grid grid-3">
        <?php foreach ($budgets as $b): $lv = $b['level']; ?>
            <article class="card budget-card">
                <div class="row between">
                    <div class="row"><span class="cat-dot" style="background:<?= e(categoryColor((int) $b['category_id'])) ?>;margin:0"></span><strong class="budget-name"><?= e($b['name']) ?></strong></div>
                    <?= levelBadge($lv) ?>
                </div>
                <div class="budget-pct"><?= (int) round($lv['pct']) ?>%</div>
                <?= progressBar($lv) ?>
                <div class="amounts"><span>Used <b><?= e(money($b['used'])) ?></b></span><span>Limit <b><?= e(money($b['limit_amount'])) ?></b></span></div>
                <div class="row between">
                    <span class="<?= $b['remaining'] >= 0 ? 'amt-income' : 'amt-expense' ?> text-sm"><?= $b['remaining'] >= 0 ? e(money($b['remaining'])) . ' remaining' : e(money(-$b['remaining'])) . ' over limit' ?></span>
                    <span class="actions">
                        <button type="button" class="icon-btn icon-action" aria-label="Edit budget for <?= e($b['name']) ?>" data-modal-open="budgetModal" data-fill-cat="<?= (int) $b['category_id'] ?>" data-fill-limit="<?= e(rtrim(rtrim(number_format($b['limit_amount'], 2, '.', ''), '0'), '.')) ?>"><?= icon('edit', 16) ?></button>
                        <form method="post" class="inline"><?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="month" value="<?= e($ym) ?>"><input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                            <button type="button" class="icon-btn icon-action danger" aria-label="Remove budget for <?= e($b['name']) ?>" data-confirm="Remove the <?= e($b['name']) ?> budget for <?= e(monthLabel($ym)) ?>?" data-confirm-title="Remove budget?" data-confirm-label="Remove"><?= icon('trash', 16) ?></button></form>
                    </span>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

    <div class="alert alert-info"><?= icon('info', 20) ?><span>Status levels: <b>On track</b> under 70% · <b>Warning</b> 70–90% · <b>Near limit</b> 90–100% · <b>Exceeded</b> above 100%.</span></div>
<?php endif; ?>

<dialog class="modal" id="budgetModal" aria-labelledby="budgetTitle">
    <form method="post" novalidate data-validate>
        <?= csrfField() ?><input type="hidden" name="action" value="save"><input type="hidden" name="month" value="<?= e($ym) ?>">
        <div class="modal-head"><h2 id="budgetTitle">Set budget · <?= e(monthLabel($ym, true)) ?></h2><button type="button" class="icon-btn" data-modal-close aria-label="Close"><?= icon('x') ?></button></div>
        <div class="modal-body">
            <div class="field"><label for="b_cat">Expense category</label>
                <select id="b_cat" name="category_id" class="select" data-bind="cat" required><option value="">Select a category</option>
                    <?php foreach ($expenseCats as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="b_limit">Monthly limit</label>
                <div class="input-group"><span class="input-prefix"><?= e(CURRENCY) ?></span><input id="b_limit" name="limit_amount" class="input" inputmode="decimal" placeholder="8,000" data-type="amount" data-bind="limit" required></div>
                <span class="field-hint">Saving again for the same category updates its limit.</span></div>
        </div>
        <div class="modal-foot"><button type="button" class="btn btn-ghost" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary">Save budget</button></div>
    </form>
</dialog>
<?php require __DIR__ . '/../includes/footer.php'; ?>
