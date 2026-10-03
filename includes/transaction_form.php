<?php
/**
 * Shared add / edit form for income and expenses.
 * Expects: $user (array), $type ('income'|'expense'), optional $editTx (array|null).
 * Handles the POST, then renders the whole page (header … footer).
 */
$editTx    = $editTx ?? null;
$editing   = $editTx !== null;
$isExpense = $type === 'expense';
$uid       = (int) $user['id'];
$errors    = [];

$fmtAmount = fn($a) => rtrim(rtrim(number_format((float) $a, 2, '.', ''), '0'), '.');
$form = [
    'category_id' => $editing ? (string) $editTx['category_id'] : query('category_id'),
    'amount'      => $editing ? $fmtAmount($editTx['amount']) : '',
    'description' => $editing ? (string) $editTx['description'] : mb_substr(query('description'), 0, 255),
    'date'        => $editing ? $editTx['date'] : date('Y-m-d'),
];

if (isPost()) {
    requireCsrf();
    $form = ['category_id' => post('category_id'), 'amount' => post('amount'), 'description' => post('description'), 'date' => post('date')];

    // Category must belong to this student (or be an active default). Editing keeps the original.
    $cat = findUsableCategory($uid, (int) $form['category_id'], $type);
    if (!$cat && $editing) {
        $cat = dbRow('SELECT id, name FROM categories WHERE id = ? AND type = ? AND (user_id IS NULL OR user_id = ?)', [(int) $form['category_id'], $type, $uid]);
    }
    if (!$cat) {
        $errors['category_id'] = 'Please choose a valid category.';
    }
    $amount = parseAmount($form['amount']);
    if ($amount === null) {
        $errors['amount'] = 'Enter a valid amount greater than zero (max 2 decimals).';
    }
    if (!isValidDate($form['date'])) {
        $errors['date'] = 'Please choose a valid date.';
    } elseif ($form['date'] > date('Y-m-d')) {
        $errors['date'] = 'The date cannot be in the future.';
    }
    if (mb_strlen($form['description']) > 255) {
        $errors['description'] = 'Description must be 255 characters or fewer.';
    }

    if (!$errors) {
        if ($editing) {
            // Ownership is enforced in the WHERE clause.
            dbRun('UPDATE transactions SET category_id = ?, amount = ?, description = ?, date = ? WHERE id = ? AND user_id = ?',
                [$cat['id'], $amount, $form['description'], $form['date'], $editTx['id'], $uid]);
            flash('success', ($isExpense ? 'Expense' : 'Income') . ' updated successfully.');
            redirect('student/transactions.php');
        }
        dbRun('INSERT INTO transactions (user_id, category_id, amount, type, description, date) VALUES (?, ?, ?, ?, ?, ?)',
            [$uid, $cat['id'], $amount, $type, $form['description'], $form['date']]);
        flash('success', $isExpense ? 'Expense added successfully.' : 'Income added successfully.');

        // Budget check after an expense.
        if ($isExpense) {
            foreach (getBudgetUsage($uid, substr($form['date'], 0, 7)) as $b) {
                if ((int) $b['category_id'] === (int) $cat['id'] && $b['level']['key'] !== 'ok') {
                    $pct = (int) round($b['level']['pct']);
                    flash('warning', $b['level']['key'] === 'over'
                        ? "Your {$b['name']} budget has been exceeded ({$pct}% used)."
                        : "Your {$b['name']} budget is {$pct}% used.");
                }
            }
        }
        redirect('student/' . ($isExpense ? 'add-expense.php' : 'add-income.php'));
    }
    flash('error', 'Unable to save transaction. Please fix the highlighted fields.');
}

$categories = getCategories($uid, $type, $editing ? (int) $editTx['category_id'] : null);
$recent = dbAll(
    'SELECT t.amount, t.date, t.description, c.name AS category FROM transactions t JOIN categories c ON c.id = t.category_id
     WHERE t.user_id = ? AND t.type = ? ORDER BY t.date DESC, t.id DESC LIMIT 5',
    [$uid, $type]
);

$layout    = 'student';
$pageTitle = ($editing ? 'Edit ' : 'Add ') . ($isExpense ? 'Expense' : 'Income');
require __DIR__ . '/header.php';

pageHead(
    $pageTitle,
    $isExpense ? 'Record what you spent so budgets and reports stay accurate.' : 'Record money coming in — allowance, scholarship, or side income.',
    '<a class="btn btn-outline" href="' . e(url('student/transactions.php')) . '">' . icon('repeat', 18) . ' All transactions</a>'
);
$defaults = array_filter($categories, fn($c) => (int) $c['is_default'] === 1);
$custom   = array_filter($categories, fn($c) => (int) $c['is_default'] !== 1);
?>
<div class="grid grid-form">
    <section class="card">
        <div class="card-body">
            <form method="post" novalidate data-validate data-loading>
                <?= csrfField() ?>
                <div class="field<?= isset($errors['category_id']) ? ' has-error' : '' ?>">
                    <label for="category_id"><?= $isExpense ? 'Expense' : 'Income' ?> category</label>
                    <select id="category_id" name="category_id" class="select" required>
                        <option value="">Select a category</option>
                        <?php if ($defaults): ?><optgroup label="Default categories">
                            <?php foreach ($defaults as $c): ?><option value="<?= (int) $c['id'] ?>"<?= (string) $c['id'] === $form['category_id'] ? ' selected' : '' ?>><?= e($c['name']) ?><?= !(int) $c['is_active'] ? ' (disabled)' : '' ?></option><?php endforeach; ?>
                        </optgroup><?php endif; ?>
                        <?php if ($custom): ?><optgroup label="My categories">
                            <?php foreach ($custom as $c): ?><option value="<?= (int) $c['id'] ?>"<?= (string) $c['id'] === $form['category_id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
                        </optgroup><?php endif; ?>
                    </select>
                    <?php if (isset($errors['category_id'])): ?><p class="field-error"><?= e($errors['category_id']) ?></p><?php endif; ?>
                </div>

                <div class="form-grid">
                    <div class="field<?= isset($errors['amount']) ? ' has-error' : '' ?>">
                        <label for="amount">Amount</label>
                        <div class="input-group"><span class="input-prefix"><?= e(CURRENCY) ?></span>
                            <input id="amount" name="amount" class="input" type="text" inputmode="decimal" autocomplete="off" placeholder="0.00" value="<?= e($form['amount']) ?>" required data-type="amount"></div>
                        <?php if (isset($errors['amount'])): ?><p class="field-error"><?= e($errors['amount']) ?></p><?php endif; ?>
                    </div>
                    <div class="field<?= isset($errors['date']) ? ' has-error' : '' ?>">
                        <label for="date">Date</label>
                        <input id="date" name="date" class="input" type="date" max="<?= e(date('Y-m-d')) ?>" value="<?= e($form['date']) ?>" required>
                        <?php if (isset($errors['date'])): ?><p class="field-error"><?= e($errors['date']) ?></p><?php endif; ?>
                    </div>
                </div>

                <div class="field<?= isset($errors['description']) ? ' has-error' : '' ?>">
                    <label for="description">Description <span class="optional">(optional)</span></label>
                    <input id="description" name="description" class="input" type="text" maxlength="255" autocomplete="off"
                           placeholder="<?= $isExpense ? 'e.g. Lunch at Campus Cafe' : 'e.g. Monthly allowance' ?>" value="<?= e($form['description']) ?>"
                           <?= $isExpense && !$editing ? 'data-suggest-url="' . e(url('api/suggest-category.php')) . '" data-suggest-target="category_id"' : '' ?>>
                    <?php if (isset($errors['description'])): ?><p class="field-error"><?= e($errors['description']) ?></p><?php endif; ?>
                    <?php if ($isExpense && !$editing): ?><div class="suggest-chip" id="suggestChip" hidden aria-live="polite"></div><?php endif; ?>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn <?= $isExpense ? 'btn-primary' : 'btn-accent' ?> btn-lg"><span class="btn-label"><?= icon('check', 18) ?> <?= $editing ? 'Save changes' : ($isExpense ? 'Add expense' : 'Add income') ?></span></button>
                    <a class="btn btn-ghost btn-lg" href="<?= e(url('student/transactions.php')) ?>">Cancel</a>
                </div>
            </form>
        </div>
    </section>

    <aside class="stack">
        <section class="card">
            <div class="card-head"><h2 class="card-title">Recent <?= $isExpense ? 'expenses' : 'income' ?></h2></div>
            <div class="card-body tight">
                <?php if (!$recent): ?>
                    <?= emptyState($isExpense ? 'minus-circle' : 'plus-circle', 'Nothing here yet', 'Your latest entries will appear here.') ?>
                <?php else: ?>
                    <ul class="mini-list">
                        <?php foreach ($recent as $r): ?>
                            <li><div><strong><?= e($r['category']) ?></strong><small><?= e($r['description'] ?: 'No description') ?> · <?= e(formatDate($r['date'], 'd M')) ?></small></div>
                                <span class="<?= $isExpense ? 'amt-expense' : 'amt-income' ?>"><?= e(money($r['amount'], !$isExpense)) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>
        <section class="card tip-card">
            <div class="card-body">
                <span class="tip-icon"><?= icon($isExpense ? 'bulb' : 'target', 22) ?></span>
                <h3><?= $isExpense ? 'Log it while it is fresh' : 'Count every source' ?></h3>
                <p><?= $isExpense ? 'Adding expenses the same day keeps your budgets honest and your reports accurate. Category suggestions appear as you type.' : 'Allowance, scholarships, tutoring and gifts all count. A complete picture makes your savings goal meaningful.' ?></p>
            </div>
        </section>
    </aside>
</div>
<?php require __DIR__ . '/footer.php'; ?>
