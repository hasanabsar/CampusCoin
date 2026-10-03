<?php
require_once __DIR__ . '/../includes/auth.php';
$admin = requireAdmin();
$topics = ['general' => 'General', 'food' => 'Food', 'entertainment' => 'Entertainment', 'transport' => 'Transport', 'shopping' => 'Shopping', 'academics' => 'Academics'];

if (isPost()) {
    requireCsrf();
    $action = post('action');
    if ($action === 'save') {
        $id = (int) post('id');
        $title = mb_substr(trim(post('title')), 0, 150);
        $content = mb_substr(trim(post('content')), 0, 600);
        $topic = array_key_exists(post('topic'), $topics) ? post('topic') : 'general';
        if (mb_strlen($title) < 3 || mb_strlen($content) < 10) {
            flash('error', 'Please provide a title (3+ chars) and content (10+ chars).');
        } elseif ($id > 0) {
            if (dbValue('SELECT id FROM saving_tips WHERE id = ?', [$id])) {
                dbRun('UPDATE saving_tips SET title = ?, content = ?, topic = ? WHERE id = ?', [$title, $content, $topic, $id]);
                flash('success', 'Tip updated.');
            } else {
                flash('error', 'Tip not found.');
            }
        } else {
            dbRun('INSERT INTO saving_tips (title, content, topic, is_published, admin_id) VALUES (?, ?, ?, 0, ?)', [$title, $content, $topic, (int) $admin['id']]);
            flash('success', 'Tip created as a draft.');
        }
    } elseif ($action === 'publish') {
        dbRun('UPDATE saving_tips SET is_published = 1 - is_published WHERE id = ?', [(int) post('id')]);
        flash('success', 'Publish status updated.');
    } elseif ($action === 'delete') {
        dbRun('DELETE FROM saving_tips WHERE id = ?', [(int) post('id')]);
        flash('success', 'Tip deleted.');
    }
    redirectSelf();
}

$tips = dbAll('SELECT id, title, content, topic, is_published, created_at FROM saving_tips ORDER BY created_at DESC');

$layout = 'admin';
$pageTitle = 'Saving Tips';
require __DIR__ . '/../includes/header.php';
pageHead('Saving tips', 'Publish tips that appear on every student\'s Saving Tips page.',
    '<button type="button" class="btn btn-primary" data-modal-open="tipModal">' . icon('plus', 18) . ' New tip</button>');
?>
<section class="card">
    <?php if (!$tips): ?>
        <?= emptyState('bulb', 'No tips yet', 'Create the first saving tip for students.') ?>
    <?php else: ?>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Title</th><th>Topic</th><th>Status</th><th>Created</th><th class="text-right">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($tips as $t): ?>
                <tr>
                    <td><div class="cell-main"><?= e($t['title']) ?></div><div class="cell-sub"><?= e(mb_strimwidth($t['content'], 0, 90, '…')) ?></div></td>
                    <td><span class="badge blue"><?= e($topics[$t['topic']] ?? ucfirst($t['topic'])) ?></span></td>
                    <td><span class="badge <?= (int) $t['is_published'] ? 'green' : 'amber' ?>"><?= (int) $t['is_published'] ? 'Published' : 'Draft' ?></span></td>
                    <td><?= e(formatDate($t['created_at'])) ?></td>
                    <td class="text-right"><span class="actions">
                        <button type="button" class="icon-btn icon-action" aria-label="Edit tip" title="Edit" data-modal-open="tipModal"
                            data-fill-id="<?= (int) $t['id'] ?>" data-fill-title="<?= e($t['title']) ?>" data-fill-content="<?= e($t['content']) ?>" data-fill-topic="<?= e($t['topic']) ?>"><?= icon('edit', 16) ?></button>
                        <form method="post" class="inline"><?= csrfField() ?><input type="hidden" name="action" value="publish"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                            <button type="button" class="icon-btn icon-action" aria-label="Toggle publish" title="<?= (int) $t['is_published'] ? 'Unpublish' : 'Publish' ?>" data-confirm="<?= (int) $t['is_published'] ? 'Unpublish' : 'Publish' ?> this tip?" data-confirm-title="Change status?" data-confirm-label="Confirm" data-confirm-variant="primary"><?= icon((int) $t['is_published'] ? 'eye-off' : 'check-circle', 16) ?></button></form>
                        <form method="post" class="inline"><?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                            <button type="button" class="icon-btn icon-action danger" aria-label="Delete tip" title="Delete" data-confirm="Delete this saving tip permanently?" data-confirm-title="Delete tip?" data-confirm-label="Delete"><?= icon('trash', 16) ?></button></form>
                    </span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>

<dialog class="modal" id="tipModal" aria-labelledby="tipModalTitle">
    <form method="post" novalidate data-validate>
        <?= csrfField() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="0" data-bind="id">
        <div class="modal-head"><h2 id="tipModalTitle">Saving tip</h2><button type="button" class="icon-btn" data-modal-close aria-label="Close"><?= icon('x') ?></button></div>
        <div class="modal-body">
            <div class="field"><label for="tip_topic">Topic</label><select id="tip_topic" name="topic" class="select" data-bind="topic">
                <?php foreach ($topics as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="tip_title">Title</label><input id="tip_title" name="title" class="input" required minlength="3" maxlength="150" data-bind="title"></div>
            <div class="field"><label for="tip_content">Content</label><textarea id="tip_content" name="content" class="textarea" required minlength="10" maxlength="600" data-bind="content"></textarea></div>
        </div>
        <div class="modal-foot"><button type="button" class="btn btn-ghost" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary">Save tip</button></div>
    </form>
</dialog>
<?php require __DIR__ . '/../includes/footer.php'; ?>
