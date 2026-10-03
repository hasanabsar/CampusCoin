<?php
require_once __DIR__ . '/../includes/auth.php';
$admin = requireAdmin();

$userId = (int) query('id');
$user = $userId > 0 ? dbRow(
    'SELECT id, name, email, academic_year, monthly_savings_goal, status, avatar, must_change_password, created_at, updated_at
    FROM users WHERE id = ?',
    [$userId]
) : null;

if (!$user) {
    renderErrorPage(404, 'Student not found', 'The requested student account does not exist.', 'users', null,
        ['label' => 'Back to users', 'href' => url('admin/users.php')]);
}

if (isPost()) {
    requireCsrf();
    $action = post('action');
    if ($action === 'approve') {
        dbRun("UPDATE users SET status = 'active' WHERE id = ?", [$userId]);
        flash('success', $user['name'] . ' has been approved and can now sign in.');
    } elseif ($action === 'disable') {
        dbRun("UPDATE users SET status = 'disabled' WHERE id = ?", [$userId]);
        flash('success', $user['name'] . ' has been disabled.');
    } elseif ($action === 'enable') {
        dbRun("UPDATE users SET status = 'active' WHERE id = ?", [$userId]);
        flash('success', $user['name'] . ' has been re-enabled.');
    }
    redirectSelf();
}

$transactions = dbAll(
    'SELECT t.id, t.amount, t.type, t.description, t.date, t.created_at, c.name AS category
     FROM transactions t
     JOIN categories c ON c.id = t.category_id
     WHERE t.user_id = ? ORDER BY t.date DESC, t.id DESC',
    [$userId]
);
$categories = dbAll(
    'SELECT id, name, type, is_default, is_active, created_at
     FROM categories WHERE user_id = ? OR (user_id IS NULL AND is_default = 1)
     ORDER BY is_default DESC, type, name',
    [$userId]
);
$budgets = dbAll(
    'SELECT b.month, b.limit_amount, c.name AS category, c.type
     FROM budgets b JOIN categories c ON c.id = b.category_id
     WHERE b.user_id = ? ORDER BY b.month DESC, c.name',
    [$userId]
);
$insights = dbAll(
    'SELECT month, summary, tip, generated_at FROM insights WHERE user_id = ? ORDER BY month DESC',
    [$userId]
);
$income = (float) dbValue("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = ? AND type = 'income'", [$userId]);
$expenses = (float) dbValue("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = ? AND type = 'expense'", [$userId]);

$layout = 'admin';
$pageTitle = $user['name'];
require __DIR__ . '/../includes/header.php';
pageHead('Student profile', 'Full account and financial data.',
    '<a class="btn btn-outline" href="' . e(url('admin/users.php')) . '">' . icon('chevron-left', 16) . ' Back to users</a>');
?>
<section class="card">
    <div class="card-body">
        <div class="profile-hero">
            <div class="profile-avatar"><?= avatarHtml($user, 88) ?></div>
            <div class="profile-meta grow">
                <h2><?= e($user['name']) ?></h2>
                <p><?= e($user['email']) ?> · <?= e($user['academic_year'] ?: 'Academic year not set') ?></p>
                <p class="text-sm">Member since <?= e(formatDate($user['created_at'], 'F Y')) ?> · Last updated <?= e(formatDate($user['updated_at'], 'd M Y, H:i')) ?></p>
            </div>
            <div class="row wrap">
                <span class="badge <?= $user['status'] === 'active' ? 'green' : ($user['status'] === 'pending' ? 'amber' : 'red') ?>"><?= $user['status'] === 'pending' ? 'Pending approval' : ucfirst($user['status']) ?></span>
                <?php if ((int) $user['must_change_password']): ?><span class="badge amber">Pending password change</span><?php endif; ?>
                <?php if ($user['status'] === 'pending'): ?>
                    <form method="post" class="inline"><?= csrfField() ?><input type="hidden" name="action" value="approve"><button type="button" class="btn btn-sm btn-primary" data-confirm="Approve <?= e($user['name']) ?>'s account? They will be able to sign in." data-confirm-title="Approve account?" data-confirm-label="Approve" data-confirm-variant="primary"><?= icon('check-circle', 16) ?> Approve</button></form>
                    <form method="post" class="inline"><?= csrfField() ?><input type="hidden" name="action" value="disable"><button type="button" class="btn btn-sm btn-outline" data-confirm="Reject <?= e($user['name']) ?>'s account? They will not be able to sign in." data-confirm-title="Reject account?" data-confirm-label="Reject"><?= icon('lock', 16) ?> Reject</button></form>
                <?php elseif ($user['status'] === 'active'): ?>
                    <form method="post" class="inline"><?= csrfField() ?><input type="hidden" name="action" value="disable"><button type="button" class="btn btn-sm btn-outline" data-confirm="Disable <?= e($user['name']) ?>'s account? They will be signed out and unable to log in." data-confirm-title="Disable account?" data-confirm-label="Disable"><?= icon('lock', 16) ?> Disable</button></form>
                <?php else: ?>
                    <form method="post" class="inline"><?= csrfField() ?><input type="hidden" name="action" value="enable"><button type="button" class="btn btn-sm btn-primary" data-confirm="Re-enable <?= e($user['name']) ?>'s account?" data-confirm-title="Enable account?" data-confirm-label="Enable" data-confirm-variant="primary"><?= icon('check-circle', 16) ?> Enable</button></form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="grid grid-4">
    <div class="card stat"><span class="stat-label">Monthly savings goal</span><div class="stat-value"><?= e(money($user['monthly_savings_goal'])) ?></div></div>
    <div class="card stat"><span class="stat-label">Total income</span><div class="stat-value amt-income"><?= e(money($income)) ?></div></div>
    <div class="card stat"><span class="stat-label">Total expenses</span><div class="stat-value amt-expense"><?= e(money($expenses)) ?></div></div>
    <div class="card stat"><span class="stat-label">Balance</span><div class="stat-value"><?= e(money($income - $expenses)) ?></div></div>
</section>

<div class="grid grid-2">
    <section class="card">
        <div class="card-head"><h2 class="card-title">Personal details</h2></div>
        <div class="card-body"><dl class="detail-list">
            <dt>User ID</dt><dd><?= (int) $user['id'] ?></dd>
            <dt>Name</dt><dd><?= e($user['name']) ?></dd>
            <dt>Email</dt><dd><?= e($user['email']) ?></dd>
            <dt>Academic year</dt><dd><?= e($user['academic_year'] ?: 'Not set') ?></dd>
            <dt>Savings goal</dt><dd><?= e(money($user['monthly_savings_goal'])) ?> per month</dd>
            <dt>Account status</dt><dd><?= e($user['status'] === 'pending' ? 'Pending approval' : ucfirst($user['status'])) ?></dd>
            <dt>Joined</dt><dd><?= e(formatDate($user['created_at'], 'd M Y, H:i')) ?></dd>
        </dl></div>
    </section>

    <section class="card">
        <div class="card-head"><h2 class="card-title">Categories</h2><span class="card-sub"><?= count($categories) ?> available</span></div>
        <div class="card-body tight">
            <?php if (!$categories): ?><?= emptyState('tag', 'No categories', 'This student has no available categories.') ?><?php else: ?>
                <ul class="mini-list"><?php foreach ($categories as $category): ?><li>
                    <div><strong><?= e($category['name']) ?></strong><small><?= ucfirst($category['type']) ?> · <?= (int) $category['is_default'] ? 'Shared default' : 'Custom category' ?></small></div>
                    <span class="badge <?= $category['is_active'] ? 'green' : 'red' ?>"><?= $category['is_active'] ? 'Active' : 'Inactive' ?></span>
                </li><?php endforeach; ?></ul>
            <?php endif; ?>
        </div>
    </section>
</div>

<section class="card">
    <div class="card-head"><h2 class="card-title">Budgets</h2><span class="card-sub"><?= count($budgets) ?> total</span></div>
    <div class="card-body flush">
        <?php if (!$budgets): ?><?= emptyState('target', 'No budgets', 'This student has not created any budgets.') ?><?php else: ?>
            <div class="table-wrap"><table class="table"><thead><tr><th>Month</th><th>Category</th><th>Type</th><th class="text-right">Limit</th></tr></thead><tbody>
            <?php foreach ($budgets as $budget): ?><tr><td><?= e(monthLabel($budget['month'], true)) ?></td><td><?= e($budget['category']) ?></td><td><span class="badge <?= $budget['type'] === 'income' ? 'green' : 'red' ?>"><?= ucfirst($budget['type']) ?></span></td><td class="text-right"><?= e(money($budget['limit_amount'])) ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div>
</section>

<section class="card">
    <div class="card-head"><h2 class="card-title">All transactions</h2><span class="card-sub"><?= count($transactions) ?> total</span></div>
    <div class="card-body flush">
        <?php if (!$transactions): ?><?= emptyState('inbox', 'No transactions', 'This student has not recorded any transactions.') ?><?php else: ?>
            <div class="table-wrap"><table class="table"><thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Type</th><th class="text-right">Amount</th><th>Recorded</th></tr></thead><tbody>
            <?php foreach ($transactions as $transaction): $isIncome = $transaction['type'] === 'income'; ?><tr>
                <td><?= e(formatDate($transaction['date'])) ?></td><td><?= e($transaction['category']) ?></td><td><?= e($transaction['description'] ?: '—') ?></td>
                <td><span class="badge <?= $isIncome ? 'green' : 'red' ?>"><?= $isIncome ? 'Income' : 'Expense' ?></span></td>
                <td class="text-right <?= $isIncome ? 'amt-income' : 'amt-expense' ?>"><?= e($isIncome ? money($transaction['amount'], true) : money(-$transaction['amount'])) ?></td>
                <td><?= e(formatDate($transaction['created_at'], 'd M Y, H:i')) ?></td>
            </tr><?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div>
</section>

<section class="card">
    <div class="card-head"><h2 class="card-title">AI insights history</h2><span class="card-sub"><?= count($insights) ?> total</span></div>
    <div class="card-body">
        <?php if (!$insights): ?><?= emptyState('sparkles', 'No insights', 'No monthly insights have been generated for this student.') ?><?php else: ?>
            <div class="stack"><?php foreach ($insights as $insight): ?><div class="budget-row">
                <div class="row between wrap"><strong><?= e(monthLabel($insight['month'], true)) ?></strong><span class="text-sm text-muted">Generated <?= e(formatDate($insight['generated_at'], 'd M Y, H:i')) ?></span></div>
                <p class="mt-2"><?= nl2br(e($insight['summary'])) ?></p><p class="text-sm text-muted mt-2"><b>Tip:</b> <?= nl2br(e($insight['tip'])) ?></p>
            </div><?php endforeach; ?></div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
