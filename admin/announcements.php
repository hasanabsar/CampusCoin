<?php
require_once __DIR__ . '/../includes/auth.php';
$admin = requireAdmin();

if (isPost()) {
    requireCsrf();
    $action = post('action');
    $id = (int) post('id');
    if ($action === 'save') {
        $title = mb_substr(trim(post('title')), 0, 150);
        $desc = mb_substr(trim(post('description')), 0, 500);
        $status = post('status') === 'active' ? 'active' : 'inactive';
        if (mb_strlen($title) < 3 || mb_strlen($desc) < 5) {
            flash('error', 'Please provide a title and description.');
        } elseif ($id > 0) {
            if (dbValue('SELECT id FROM announcements WHERE id = ?', [$id])) {
                dbRun('UPDATE announcements SET title = ?, description = ?, status = ? WHERE id = ?', [$title, $desc, $status, $id]);
                flash('success', 'Announcement updated.');
            } else {
                flash('error', 'That announcement no longer exists.');
            }
        } else {
            dbRun('INSERT INTO announcements (title, description, status, admin_id) VALUES (?, ?, ?, ?)', [$title, $desc, $status, (int) $admin['id']]);
            flash('success', 'Announcement created.');
        }
    } elseif ($action === 'toggle') {
        if (dbValue('SELECT id FROM announcements WHERE id = ?', [$id])) {
            dbRun("UPDATE announcements SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?", [$id]);
            flash('success', 'Status updated.');
        } else {
            flash('error', 'That announcement no longer exists.');
        }
    } elseif ($action === 'delete') {
        if (dbValue('SELECT id FROM announcements WHERE id = ?', [$id])) {
            dbRun('DELETE FROM announcements WHERE id = ?', [$id]);
            flash('success', 'Announcement deleted.');
        } else {
            flash('error', 'That announcement no longer exists.');
        }
    } else {
        flash('error', 'Unsupported announcement action.');
    }
    redirectSelf();
}

$announcements = dbAll('SELECT id, title, description, status, created_at FROM announcements ORDER BY created_at DESC');

$layout = 'admin';
$pageTitle = 'Announcements';
require __DIR__ . '/../includes/header.php';
pageHead('Announcements', 'Active announcements appear at the top of every student\'s dashboard.',
    '<button type="button" class="btn btn-primary" data-modal-open="annModal">' . icon('plus', 18) . ' New announcement</button>');
?>
<section class="stack">
    <?php if (!$announcements): ?>
        <div class="card"><?= emptyState('megaphone', 'No announcements yet', 'Create one to reach every student.') ?></div>
    <?php else: ?>
        <?php foreach ($announcements as $a):
            if (isset($a[0])) {
                $a = [
                    'id' => $a[0] ?? 0,
                    'title' => $a[1] ?? '',
                    'description' => $a[2] ?? '',
                    'status' => $a[3] ?? 'inactive',
                ];
            }
            $aId = (int) ($a['id'] ?? 0);
            $aTitle = $a['title'] ?? '';
            $aDesc = $a['description'] ?? '';
            $aStatus = $a['status'] ?? 'inactive';
        ?>
        <article class="card announcement-card">
            <div class="card-body">
                <div class="announcement-layout">
                    <div class="announcement-main">
                        <span class="tip-icon"><?= icon('megaphone', 20) ?></span>
                        <div class="announcement-copy"><strong><?= e($aTitle) ?></strong><p class="text-sm text-muted mt-2"><?= e($aDesc) ?></p></div>
                    </div>
                    <div class="announcement-actions">
                        <span class="badge <?= $aStatus === 'active' ? 'green' : 'gray' ?>"><?= ucfirst($aStatus) ?></span>
                        <span class="actions">
                            <button type="button" class="icon-btn icon-action" aria-label="Edit" title="Edit" data-modal-open="annModal" data-fill-id="<?= $aId ?>" data-fill-title="<?= e($aTitle) ?>" data-fill-description="<?= e($aDesc) ?>" data-fill-status="<?= e($aStatus) ?>"><?= icon('edit', 16) ?></button>
                            <form method="post" class="inline"><?= csrfField() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $aId ?>">
                                <button type="button" class="icon-btn icon-action" aria-label="Toggle status" title="Toggle" data-confirm="<?= $aStatus === 'active' ? 'Deactivate' : 'Activate' ?> this announcement?" data-confirm-title="Change status?" data-confirm-label="Confirm" data-confirm-variant="primary"><?= icon($aStatus === 'active' ? 'eye-off' : 'check-circle', 16) ?></button></form>
                            <form method="post" class="inline"><?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $aId ?>">
                                <button type="button" class="icon-btn icon-action danger" aria-label="Delete" title="Delete" data-confirm="Delete this announcement permanently?" data-confirm-title="Delete announcement?" data-confirm-label="Delete"><?= icon('trash', 16) ?></button></form>
                        </span>
                    </div>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<dialog class="modal" id="annModal" aria-labelledby="annModalTitle">
    <form method="post" novalidate data-validate>
        <?= csrfField() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="0" data-bind="id">
        <div class="modal-head"><h2 id="annModalTitle">Announcement</h2><button type="button" class="icon-btn" data-modal-close aria-label="Close"><?= icon('x') ?></button></div>
        <div class="modal-body">
            <div class="field"><label for="a_title">Title</label><input id="a_title" name="title" class="input" required minlength="3" maxlength="150" data-bind="title"></div>
            <div class="field"><label for="a_desc">Description</label><textarea id="a_desc" name="description" class="textarea" required minlength="5" maxlength="500" data-bind="description"></textarea></div>
            <div class="field"><label for="a_status">Status</label><select id="a_status" name="status" class="select" data-bind="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
        </div>
        <div class="modal-foot"><button type="button" class="btn btn-ghost" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
    </form>
</dialog>
<?php require __DIR__ . '/../includes/footer.php'; ?>
