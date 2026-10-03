<?php
require_once __DIR__ . '/../includes/auth.php';
$user = requireStudent();
$uid  = (int) $user['id'];

/* ---------- Delete (POST + CSRF + ownership) ---------- */
if (isPost()) {
    requireCsrf();
    if (post('action') === 'delete') {
        $deleted = dbRun('DELETE FROM transactions WHERE id = ? AND user_id = ?', [(int) post('id'), $uid])->rowCount();
        $deleted ? flash('success', 'Transaction deleted.') : flash('error', 'Unable to delete that transaction.');
    }
    redirectSelf();
}

/* ---------- Filters ---------- */
$q      = mb_substr(query('q'), 0, 100);
$type   = in_array(query('type'), ['income', 'expense'], true) ? query('type') : '';
$catId  = (int) query('category');
$from   = isValidDate(query('from')) ? query('from') : '';
$to     = isValidDate(query('to')) ? query('to') : '';
$sort   = query('sort', 'date_desc');
$sorts  = ['date_desc' => 't.date DESC, t.id DESC', 'date_asc' => 't.date ASC, t.id ASC', 'amount_desc' => 't.amount DESC, t.id DESC', 'amount_asc' => 't.amount ASC, t.id ASC', 'category' => 'c.name ASC, t.date DESC'];
if (!isset($sorts[$sort])) $sort = 'date_desc';

$where = ['t.user_id = ?'];
$params = [$uid];
if ($type !== '')  { $where[] = 't.type = ?';        $params[] = $type; }
if ($catId > 0)    { $where[] = 't.category_id = ?'; $params[] = $catId; }
if ($from !== '')  { $where[] = 't.date >= ?';       $params[] = $from; }
if ($to !== '')    { $where[] = 't.date <= ?';       $params[] = $to; }
if ($q !== '')     { $where[] = '(t.description LIKE ? OR c.name LIKE ?)'; $like = '%' . addcslashes($q, '%_\\') . '%'; array_push($params, $like, $like); }
$whereSql = implode(' AND ', $where);
$join = 'FROM transactions t JOIN categories c ON c.id = t.category_id';

$total = (int) dbValue("SELECT COUNT(*) $join WHERE $whereSql", $params);
$pg = paginate($total, 10, (int) query('page', '1'));
$rows = dbAll(
    "SELECT t.id, t.amount, t.type, t.date, t.description, t.created_at, t.category_id, c.name AS category
     $join WHERE $whereSql ORDER BY {$sorts[$sort]} LIMIT {$pg['per']} OFFSET {$pg['offset']}",
    $params
);
$sums = ['income' => 0.0, 'expense' => 0.0];
foreach (dbAll("SELECT t.type, SUM(t.amount) AS s $join WHERE $whereSql GROUP BY t.type", $params) as $r) {
    $sums[$r['type']] = (float) $r['s'];
}
$hasFilters = $q !== '' || $type !== '' || $catId > 0 || $from !== '' || $to !== '';
$hasAny = $hasFilters ? (int) dbValue('SELECT COUNT(*) FROM transactions WHERE user_id = ?', [$uid]) > 0 : $total > 0;

$catOptions = dbAll('SELECT id, name, type FROM categories WHERE user_id = ? OR (user_id IS NULL AND is_default = 1) ORDER BY type, name', [$uid]);
$baseParams = array_filter(['q' => $q, 'type' => $type, 'category' => $catId ?: '', 'from' => $from, 'to' => $to, 'sort' => $sort !== 'date_desc' ? $sort : ''], fn($v) => $v !== '');
$sortLink = function (string $asc, string $desc, string $label) use ($baseParams, $sort): string {
    $next = $sort === $desc ? $asc : $desc;
    $arrow = $sort === $desc ? ' ↓' : ($sort === $asc ? ' ↑' : '');
    return '<a href="?' . e(http_build_query(array_merge($baseParams, ['sort' => $next]))) . '">' . e($label . $arrow) . '</a>';
};

$layout = 'student';
$pageTitle = 'Transactions';
require __DIR__ . '/../includes/header.php';

pageHead('Transactions', 'Search, filter and manage everything you have recorded.',
    '<a class="btn btn-outline" href="' . e(url('student/add-income.php')) . '">' . icon('plus', 18) . ' Income</a>'
    . '<a class="btn btn-primary" href="' . e(url('student/add-expense.php')) . '">' . icon('plus', 18) . ' Expense</a>');
?>
<section class="card">
    <form class="filters" method="get" role="search" aria-label="Filter transactions">
        <div class="field search"><label for="q">Search</label><input id="q" name="q" class="input" type="search" placeholder="Description or category" value="<?= e($q) ?>"></div>
        <div class="field"><label for="type">Type</label>
            <select id="type" name="type" class="select"><option value="">All types</option><option value="income"<?= $type === 'income' ? ' selected' : '' ?>>Income</option><option value="expense"<?= $type === 'expense' ? ' selected' : '' ?>>Expense</option></select></div>
        <div class="field"><label for="category">Category</label>
            <select id="category" name="category" class="select"><option value="">All categories</option>
                <?php foreach (['income' => 'Income', 'expense' => 'Expense'] as $tk => $tl): ?><optgroup label="<?= e($tl) ?>">
                    <?php foreach ($catOptions as $c): if ($c['type'] !== $tk) continue; ?><option value="<?= (int) $c['id'] ?>"<?= $catId === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
                </optgroup><?php endforeach; ?></select></div>
        <div class="field"><label for="from">From</label><input id="from" name="from" type="date" class="input" value="<?= e($from) ?>"></div>
        <div class="field"><label for="to">To</label><input id="to" name="to" type="date" class="input" value="<?= e($to) ?>"></div>
        <div class="row"><button class="btn btn-primary" type="submit"><?= icon('filter', 16) ?> Apply</button>
            <?php if ($hasFilters): ?><a class="btn btn-ghost" href="<?= e(url('student/transactions.php')) ?>">Reset</a><?php endif; ?></div>
    </form>

    <?php if ($total > 0): ?>
        <div class="summary-strip">
            <span><b><?= (int) $total ?></b> transaction<?= $total === 1 ? '' : 's' ?></span>
            <span>Income <b class="amt-income"><?= e(money($sums['income'])) ?></b></span>
            <span>Expenses <b class="amt-expense"><?= e(money($sums['expense'])) ?></b></span>
            <span>Net <b><?= e(money($sums['income'] - $sums['expense'])) ?></b></span>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr>
                    <th><?= $sortLink('date_asc', 'date_desc', 'Date') ?></th>
                    <th><a href="?<?= e(http_build_query(array_merge($baseParams, ['sort' => 'category']))) ?>">Category<?= $sort === 'category' ? ' ↑' : '' ?></a></th>
                    <th>Description</th><th>Type</th>
                    <th class="text-right"><?= $sortLink('amount_asc', 'amount_desc', 'Amount') ?></th>
                    <th class="text-right">Actions</th>
                </tr></thead>
                <tbody>
                <?php foreach ($rows as $t): $isInc = $t['type'] === 'income'; ?>
                    <tr>
                        <td><?= e(formatDate($t['date'])) ?></td>
                        <td><span class="cat-dot" style="background:<?= e(categoryColor((int) $t['category_id'])) ?>"></span><span class="cell-main"><?= e($t['category']) ?></span></td>
                        <td><?= e($t['description'] !== null && $t['description'] !== '' ? $t['description'] : '—') ?></td>
                        <td><span class="badge <?= $isInc ? 'green' : 'red' ?>"><?= $isInc ? 'Income' : 'Expense' ?></span></td>
                        <td class="num <?= $isInc ? 'amt-income' : 'amt-expense' ?>"><?= e($isInc ? money($t['amount'], true) : money(-$t['amount'])) ?></td>
                        <td class="text-right">
                            <span class="actions">
                                <button type="button" class="icon-btn icon-action" aria-label="View transaction" title="View" data-modal-open="viewTxModal"
                                        data-fill-category="<?= e($t['category']) ?>" data-fill-description="<?= e($t['description'] ?: '—') ?>"
                                        data-fill-type="<?= $isInc ? 'Income' : 'Expense' ?>" data-fill-amount="<?= e(money($t['amount'])) ?>"
                                        data-fill-date="<?= e(formatDate($t['date'], 'l, d F Y')) ?>" data-fill-created="<?= e(formatDate($t['created_at'], 'd M Y, H:i')) ?>"><?= icon('eye', 17) ?></button>
                                <a class="icon-btn icon-action" href="<?= e(url('student/edit-transaction.php?id=' . (int) $t['id'])) ?>" aria-label="Edit transaction" title="Edit"><?= icon('edit', 17) ?></a>
                                <form method="post" class="inline">
                                    <?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                    <button type="button" class="icon-btn icon-action danger" aria-label="Delete transaction" title="Delete"
                                            data-confirm="This will permanently delete the <?= e(strtolower($t['category'])) ?> transaction of <?= e(money($t['amount'])) ?>. This cannot be undone."
                                            data-confirm-title="Delete transaction?" data-confirm-label="Delete"><?= icon('trash', 17) ?></button>
                                </form>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="table-foot">
            <span>Showing <?= (int) ($pg['offset'] + 1) ?>–<?= (int) min($pg['offset'] + $pg['per'], $total) ?> of <?= (int) $total ?></span>
            <?= renderPagination($pg, $baseParams) ?>
        </div>
    <?php elseif ($hasAny): ?>
        <?= emptyState('search', 'No matching transactions', 'Try a different search term or clear some filters.', '<a class="btn btn-outline" href="' . e(url('student/transactions.php')) . '">Clear filters</a>') ?>
    <?php else: ?>
        <?= emptyState('inbox', 'No transactions yet', 'Start tracking your first expense to see your financial insights.',
            '<a class="btn btn-primary" href="' . e(url('student/add-expense.php')) . '">Add expense</a> <a class="btn btn-outline" href="' . e(url('student/add-income.php')) . '">Add income</a>') ?>
    <?php endif; ?>
</section>

<dialog class="modal" id="viewTxModal" aria-labelledby="viewTxTitle">
    <div class="modal-head"><h2 id="viewTxTitle">Transaction details</h2><button type="button" class="icon-btn" data-modal-close aria-label="Close"><?= icon('x') ?></button></div>
    <div class="modal-body">
        <dl class="detail-list">
            <dt>Category</dt><dd data-bind="category"></dd>
            <dt>Type</dt><dd data-bind="type"></dd>
            <dt>Amount</dt><dd data-bind="amount"></dd>
            <dt>Date</dt><dd data-bind="date"></dd>
            <dt>Description</dt><dd data-bind="description"></dd>
            <dt>Recorded</dt><dd data-bind="created"></dd>
        </dl>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-primary" data-modal-close>Close</button></div>
</dialog>
<?php require __DIR__ . '/../includes/footer.php'; ?>
