<?php
require_once __DIR__ . '/../includes/auth.php';
$user = requireStudent();
$uid  = (int) $user['id'];

/** Is this name already used by an active default or the student's own category of the same type? */
function categoryNameTaken(int $uid, string $type, string $name, int $exceptId = 0): bool
{
    return (bool) dbValue(
        'SELECT id FROM categories WHERE type = ? AND LOWER(name) = LOWER(?) AND id <> ? AND ((user_id IS NULL AND is_default = 1) OR user_id = ?) LIMIT 1',
        [$type, $name, $exceptId, $uid]
    );
}

if (isPost()) {
    requireCsrf();
    $action = post('action');
    $name = preg_replace('/\s+/', ' ', post('name'));

    if ($action === 'add') {
        $type = post('type');
        if (!in_array($type, ['income', 'expense'], true)) {
            flash('error', 'Choose a valid category type.');
        } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 40) {
            flash('error', 'Category name must be 2–40 characters.');
        } elseif (categoryNameTaken($uid, $type, $name)) {
            flash('error', 'You already have a ' . $type . ' category named "' . $name . '".');
        } else {
            dbRun('INSERT INTO categories (name, type, user_id, is_default) VALUES (?, ?, ?, 0)', [$name, $type, $uid]);
            flash('success', 'Category added successfully.');
        }
    } elseif ($action === 'edit' || $action === 'delete') {
        // Only the student's OWN, non-default categories can be changed.
        $cat = dbRow('SELECT id, type FROM categories WHERE id = ? AND user_id = ? AND is_default = 0', [(int) post('id'), $uid]);
        if (!$cat) {
            flash('error', 'That category cannot be changed.');
        } elseif ($action === 'edit') {
            if (mb_strlen($name) < 2 || mb_strlen($name) > 40) {
                flash('error', 'Category name must be 2–40 characters.');
            } elseif (categoryNameTaken($uid, $cat['type'], $name, (int) $cat['id'])) {
                flash('error', 'Another category already uses that name.');
            } else {
                dbRun('UPDATE categories SET name = ? WHERE id = ? AND user_id = ?', [$name, $cat['id'], $uid]);
                flash('success', 'Category updated.');
            }
        } else {
            $uses = (int) dbValue('SELECT COUNT(*) FROM transactions WHERE category_id = ?', [$cat['id']]);
            if ($uses > 0) {
                flash('error', "This category is used by {$uses} transaction" . ($uses === 1 ? '' : 's') . '. Edit or delete those first (or rename the category instead).');
            } else {
                $pdo = db();
                $pdo->beginTransaction();
                dbRun('DELETE FROM budgets WHERE category_id = ? AND user_id = ?', [$cat['id'], $uid]);
                dbRun('DELETE FROM categories WHERE id = ? AND user_id = ?', [$cat['id'], $uid]);
                $pdo->commit();
                flash('success', 'Category deleted.');
            }
        }
    }
    redirectSelf();
}

$lists = [];
foreach (['income' => 'Income categories', 'expense' => 'Expense categories'] as $type => $label) {
    $lists[$type] = dbAll(
        'SELECT c.id, c.name, c.is_default, (SELECT COUNT(*) FROM transactions t WHERE t.category_id = c.id AND t.user_id = ?) AS uses
         FROM categories c WHERE c.type = ? AND ((c.user_id IS NULL AND c.is_default = 1 AND c.is_active = 1) OR c.user_id = ?)
         ORDER BY c.is_default DESC, c.name',
        [$uid, $type, $uid]
    );
}

$layout = 'student';
$pageTitle = 'Categories';
require __DIR__ . '/../includes/header.php';
pageHead('Categories', 'Organise income and spending. Default categories are shared; create your own for anything else.',
    '<button type="button" class="btn btn-primary" data-modal-open="addCategoryModal">' . icon('plus', 18) . ' New category</button>');
?>
<div class="grid grid-2">
<?php foreach ($lists as $type => $items): $isInc = $type === 'income'; ?>
    <section class="card">
        <div class="card-head">
            <div class="row"><span class="stat-icon <?= $isInc ? 'emerald' : 'coral' ?>"><?= icon($isInc ? 'arrow-down' : 'arrow-up', 20) ?></span>
                <div><h2 class="card-title"><?= $isInc ? 'Income categories' : 'Expense categories' ?></h2><p class="card-sub"><?= count($items) ?> available</p></div></div>
            <button type="button" class="btn btn-outline btn-sm" data-modal-open="addCategoryModal" data-fill-type="<?= e($type) ?>"><?= icon('plus', 16) ?> Add</button>
        </div>
        <div class="card-body tight">
            <?php if (!$items): ?>
                <?= emptyState('tag', 'No categories yet', 'Create your first ' . $type . ' category to get started.') ?>
            <?php else: ?>
                <ul class="cat-list">
                    <?php foreach ($items as $c): ?>
                        <li>
                            <span class="cat-dot" style="background:<?= e(categoryColor((int) $c['id'])) ?>;margin:0"></span>
                            <div class="grow"><strong><?= e($c['name']) ?></strong><small><?= (int) $c['uses'] ?> transaction<?= (int) $c['uses'] === 1 ? '' : 's' ?></small></div>
                            <?php if ((int) $c['is_default']): ?>
                                <span class="badge blue" title="Provided by CampusCoin — cannot be edited"><?= icon('lock', 12) ?> Default</span>
                            <?php else: ?>
                                <span class="badge green">Custom</span>
                                <span class="actions">
                                    <button type="button" class="icon-btn icon-action" aria-label="Edit <?= e($c['name']) ?>" data-modal-open="editCategoryModal" data-fill-id="<?= (int) $c['id'] ?>" data-fill-name="<?= e($c['name']) ?>"><?= icon('edit', 16) ?></button>
                                    <form method="post" class="inline"><?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                        <button type="button" class="icon-btn icon-action danger" aria-label="Delete <?= e($c['name']) ?>" data-confirm="Delete the category “<?= e($c['name']) ?>”? Its budgets will be removed too." data-confirm-title="Delete category?" data-confirm-label="Delete"><?= icon('trash', 16) ?></button></form>
                                </span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>
<?php endforeach; ?>
</div>

<dialog class="modal" id="addCategoryModal" aria-labelledby="addCatTitle">
    <form method="post" novalidate data-validate>
        <?= csrfField() ?><input type="hidden" name="action" value="add">
        <div class="modal-head"><h2 id="addCatTitle">New category</h2><button type="button" class="icon-btn" data-modal-close aria-label="Close"><?= icon('x') ?></button></div>
        <div class="modal-body">
            <div class="field"><label for="add_type">Type</label>
                <select id="add_type" name="type" class="select" data-bind="type" required><option value="expense">Expense</option><option value="income">Income</option></select></div>
            <div class="field"><label for="add_name">Name</label><input id="add_name" name="name" class="input" required minlength="2" maxlength="40" placeholder="e.g. Coffee runs"></div>
        </div>
        <div class="modal-foot"><button type="button" class="btn btn-ghost" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary">Add category</button></div>
    </form>
</dialog>

<dialog class="modal" id="editCategoryModal" aria-labelledby="editCatTitle">
    <form method="post" novalidate data-validate>
        <?= csrfField() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" data-bind="id">
        <div class="modal-head"><h2 id="editCatTitle">Rename category</h2><button type="button" class="icon-btn" data-modal-close aria-label="Close"><?= icon('x') ?></button></div>
        <div class="modal-body">
            <div class="field"><label for="edit_name">Name</label><input id="edit_name" name="name" class="input" required minlength="2" maxlength="40" data-bind="name"></div>
        </div>
        <div class="modal-foot"><button type="button" class="btn btn-ghost" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary">Save changes</button></div>
    </form>
</dialog>
<?php require __DIR__ . '/../includes/footer.php'; ?>
