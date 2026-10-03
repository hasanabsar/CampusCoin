<?php
require_once __DIR__ . '/../includes/auth.php';
$admin = requireAdmin();

if (isPost()) {
    requireCsrf();
    $action = post('action');
    $name = preg_replace('/\s+/', ' ', post('name'));

    if ($action === 'add') {
        $type = post('type');
        if (!in_array($type, ['income', 'expense'], true) || mb_strlen($name) < 2 || mb_strlen($name) > 40) {
            flash('error', 'Please provide a valid name and type.');
        } elseif (dbValue('SELECT id FROM categories WHERE user_id IS NULL AND type = ? AND LOWER(name) = LOWER(?)', [$type, $name])) {
            flash('error', 'A default category with that name already exists.');
        } else {
            dbRun('INSERT INTO categories (name, type, user_id, is_default, is_active) VALUES (?, ?, NULL, 1, 1)', [$name, $type]);
            flash('success', 'Default category added.');
        }
    } else {
        $cat = dbRow('SELECT id FROM categories WHERE id = ? AND user_id IS NULL AND is_default = 1', [(int) post('id')]);
        if (!$cat) {
            flash('error', 'That category cannot be changed here.');
        } elseif ($action === 'toggle') {
            dbRun('UPDATE categories SET is_active = 1 - is_active WHERE id = ?', [$cat['id']]);
            flash('success', 'Category availability updated.');
        } elseif ($action === 'delete') {
            $uses = (int) dbValue('SELECT COUNT(*) FROM transactions WHERE category_id = ?', [$cat['id']]);
            if ($uses > 0) {
                flash('error', "This default category is used by {$uses} transaction" . ($uses === 1 ? '' : 's') . ' across students. Disable it instead of deleting.');
            } else {
                dbRun('DELETE FROM budgets WHERE category_id = ?', [$cat['id']]);
                dbRun('DELETE FROM categories WHERE id = ?', [$cat['id']]);
                flash('success', 'Default category deleted.');
            }
        }
    }
    redirectSelf();
}

$lists = [];
foreach (['income' => 'Income', 'expense' => 'Expense'] as $type => $label) {
    $lists[$type] = dbAll(
        "SELECT c.id, c.name, c.is_active, (SELECT COUNT(*) FROM transactions t WHERE t.category_id = c.id) AS uses,
                (SELECT COUNT(DISTINCT user_id) FROM transactions t WHERE t.category_id = c.id) AS students
         FROM categories c WHERE c.user_id IS NULL AND c.is_default = 1 AND c.type = ? ORDER BY c.name", [$type]
    );
}

$layout = 'admin';
$pageTitle = 'Categories';
require __DIR__ . '/../includes/header.php';
pageHead('Default categories', 'Manage the categories every student starts with. Students can also add their own.',
    '<button type="button" class="btn btn-primary" data-modal-open="addDefaultCategoryModal">' . icon('plus', 18) . ' New default category</button>');
?>
<div class="grid grid-2">
<?php foreach ($lists as $type => $items): $isInc = $type === 'income'; ?>
    <section class="card">
        <div class="card-head"><div class="row"><span class="stat-icon <?= $isInc ? 'emerald' : 'coral' ?>"><?= icon($isInc ? 'arrow-down' : 'arrow-up', 20) ?></span>
            <div><h2 class="card-title"><?= $isInc ? 'Income' : 'Expense' ?> categories</h2><p class="card-sub"><?= count($items) ?> default categories</p></div></div></div>
        <div class="card-body tight">
            <?php if (!$items): ?><?= emptyState('tag', 'No default categories', 'Add the first default ' . $type . ' category.') ?><?php else: ?>
                <ul class="cat-list<?= '' ?>">
                    <?php foreach ($items as $c): ?>
                        <li class="<?= (int) $c['is_active'] ? '' : 'text-muted' ?>">
                            <span class="cat-dot" style="background:<?= e(categoryColor((int) $c['id'])) ?>;margin:0"></span>
                            <div class="grow"><strong><?= e($c['name']) ?></strong><small><?= (int) $c['uses'] ?> transactions · <?= (int) $c['students'] ?> students</small></div>
                            <span class="badge <?= (int) $c['is_active'] ? 'green' : 'red' ?>"><?= (int) $c['is_active'] ? 'Active' : 'Disabled' ?></span>
                            <span class="actions">
                                <form method="post" class="inline"><?= csrfField() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                    <button type="button" class="icon-btn icon-action" aria-label="Toggle <?= e($c['name']) ?>" title="<?= (int) $c['is_active'] ? 'Disable' : 'Enable' ?>" data-confirm="<?= (int) $c['is_active'] ? 'Disable' : 'Enable' ?> the “<?= e($c['name']) ?>” category for all students?" data-confirm-title="Change availability?" data-confirm-label="Confirm" data-confirm-variant="primary"><?= icon((int) $c['is_active'] ? 'lock' : 'check-circle', 16) ?></button></form>
                                <form method="post" class="inline"><?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                    <button type="button" class="icon-btn icon-action danger" aria-label="Delete <?= e($c['name']) ?>" title="Delete" data-confirm="Delete the default category “<?= e($c['name']) ?>”? This only works if no transactions use it." data-confirm-title="Delete category?" data-confirm-label="Delete"><?= icon('trash', 16) ?></button></form>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>
<?php endforeach; ?>
</div>

<dialog class="modal" id="addDefaultCategoryModal" aria-labelledby="addDefTitle">
    <form method="post" novalidate data-validate>
        <?= csrfField() ?><input type="hidden" name="action" value="add">
        <div class="modal-head"><h2 id="addDefTitle">New default category</h2><button type="button" class="icon-btn" data-modal-close aria-label="Close"><?= icon('x') ?></button></div>
        <div class="modal-body">
            <div class="field"><label for="dc_type">Type</label><select id="dc_type" name="type" class="select" required><option value="expense">Expense</option><option value="income">Income</option></select></div>
            <div class="field"><label for="dc_name">Name</label><input id="dc_name" name="name" class="input" required minlength="2" maxlength="40" placeholder="e.g. Textbooks"></div>
            <p class="field-hint">This will appear as an option for every student.</p>
        </div>
        <div class="modal-foot"><button type="button" class="btn btn-ghost" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary">Add category</button></div>
    </form>
</dialog>
<?php require __DIR__ . '/../includes/footer.php'; ?>
