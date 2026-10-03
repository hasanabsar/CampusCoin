<?php
require_once __DIR__ . '/../includes/auth.php';
$admin = requireAdmin();

if (isPost()) {
    requireCsrf();
    $id = (int) post('id');
    $action = post('action');
    $target = $id > 0 ? dbRow("SELECT id, name, status FROM users WHERE id = ?", [$id]) : null;

    if (!$target) {
        flash('error', 'That account cannot be managed here.');
    } elseif ($action === 'approve') {
        dbRun("UPDATE users SET status = 'active' WHERE id = ?", [$id]);
        flash('success', $target['name'] . ' has been approved and can now sign in.');
    } elseif ($action === 'disable') {
        dbRun("UPDATE users SET status = 'disabled' WHERE id = ?", [$id]);
        flash('success', $target['name'] . ' has been disabled.');
    } elseif ($action === 'enable') {
        dbRun("UPDATE users SET status = 'active' WHERE id = ?", [$id]);
        flash('success', $target['name'] . ' has been re-enabled.');
    } elseif ($action === 'reset_password') {
        $temp = 'Cc' . random_int(1000, 9999) . chr(random_int(65, 90)) . chr(random_int(97, 122)) . '!';
        dbRun('UPDATE users SET password = ?, must_change_password = 1 WHERE id = ?', [password_hash($temp, PASSWORD_DEFAULT), $id]);
        $_SESSION['temp_password'] = ['name' => $target['name'], 'password' => $temp];
        flash('success', 'Password reset for ' . $target['name'] . '.');
    }
    redirectSelf();
}
$tempPw = $_SESSION['temp_password'] ?? null;
unset($_SESSION['temp_password']);

$q = mb_substr(query('q'), 0, 100);
$status = in_array(query('status'), ['pending', 'active', 'disabled'], true) ? query('status') : '';
$where = ['1 = 1'];
$params = [];
if ($q !== '')      { $where[] = '(name LIKE ? OR email LIKE ?)'; $like = '%' . addcslashes($q, '%_\\') . '%'; array_push($params, $like, $like); }
if ($status !== '') { $where[] = 'status = ?'; $params[] = $status; }
$whereSql = implode(' AND ', $where);

$total = (int) dbValue("SELECT COUNT(*) FROM users WHERE $whereSql", $params);
$pg = paginate($total, 12, (int) query('page', '1'));
$users = dbAll(
    "SELECT u.id, u.name, u.email, u.academic_year, u.status, u.avatar, u.created_at, u.must_change_password,
            (SELECT COUNT(*) FROM transactions t WHERE t.user_id = u.id) AS tx_count,
            (SELECT COALESCE(SUM(amount),0) FROM transactions t WHERE t.user_id = u.id AND t.type = 'expense') AS spent
     FROM users u WHERE $whereSql ORDER BY u.created_at DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}",
    $params
);
$baseParams = array_filter(['q' => $q, 'status' => $status]);

$layout = 'admin';
$pageTitle = 'Users';
require __DIR__ . '/../includes/header.php';
pageHead('Users', 'Manage student accounts.');
?>
<?php if ($tempPw): ?>
    <div class="alert alert-success" role="status">
        <?= icon('key', 20) ?>
        <span><b>Temporary password for <?= e($tempPw['name']) ?>:</b>
            <span class="temp-pw"><code><?= e($tempPw['password']) ?></code>
            <button type="button" class="btn btn-sm btn-outline" data-copy="<?= e($tempPw['password']) ?>"><?= icon('copy', 14) ?> Copy</button></span>
            Share it securely — the student will be asked to set a new password on next login.</span>
    </div>
<?php endif; ?>

<section class="card">
    <form class="filters" method="get" role="search" aria-label="Filter users" style="grid-template-columns:1.6fr 1fr auto">
        <div class="field"><label for="q">Search</label><input id="q" name="q" class="input" type="search" placeholder="Name or email" value="<?= e($q) ?>"></div>
        <div class="field"><label for="status">Status</label><select id="status" name="status" class="select"><option value="">All</option><option value="pending"<?= $status === 'pending' ? ' selected' : '' ?>>Pending approval</option><option value="active"<?= $status === 'active' ? ' selected' : '' ?>>Active</option><option value="disabled"<?= $status === 'disabled' ? ' selected' : '' ?>>Disabled</option></select></div>
        <div class="row"><button class="btn btn-primary" type="submit"><?= icon('filter', 16) ?> Apply</button><?php if ($q || $status): ?><a class="btn btn-ghost" href="<?= e(url('admin/users.php')) ?>">Reset</a><?php endif; ?></div>
    </form>
    <?php if (!$users): ?>
        <?= emptyState('users', 'No students found', 'Try a different search.') ?>
    <?php else: ?>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Student</th><th>Academic year</th><th>Transactions</th><th>Total spent</th><th>Joined</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><div class="row"><?= avatarHtml($u, 34) ?><div><div class="cell-main"><?= e($u['name']) ?></div><div class="cell-sub"><?= e($u['email']) ?></div></div></div></td>
                    <td><?= e($u['academic_year'] ?: '—') ?></td>
                    <td><?= (int) $u['tx_count'] ?></td>
                    <td><?= e(money($u['spent'])) ?></td>
                    <td><?= e(formatDate($u['created_at'])) ?></td>
                    <td><span class="badge <?= $u['status'] === 'active' ? 'green' : ($u['status'] === 'pending' ? 'amber' : 'red') ?>"><?= $u['status'] === 'pending' ? 'Pending approval' : ucfirst($u['status']) ?></span>
                        <?php if ((int) $u['must_change_password']): ?><span class="badge amber" title="Must set a new password">Pending reset</span><?php endif; ?></td>
                    <td class="text-right"><span class="actions">
                        <a class="icon-btn icon-action" href="<?= e(url('admin/user.php?id=' . (int) $u['id'])) ?>" aria-label="View full profile for <?= e($u['name']) ?>" title="View full profile"><?= icon('eye', 16) ?></a>
                        <?php if ($u['status'] === 'pending'): ?>
                            <form method="post" class="inline"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="approve">
                                <button type="button" class="icon-btn icon-action" aria-label="Approve <?= e($u['name']) ?>" title="Approve account" data-confirm="Approve <?= e($u['name']) ?>'s account? They will be able to sign in." data-confirm-title="Approve account?" data-confirm-label="Approve" data-confirm-variant="primary"><?= icon('check-circle', 16) ?></button></form>
                            <form method="post" class="inline"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="disable">
                                <button type="button" class="icon-btn icon-action danger" aria-label="Reject <?= e($u['name']) ?>" title="Reject account" data-confirm="Reject <?= e($u['name']) ?>'s account? They will not be able to sign in." data-confirm-title="Reject account?" data-confirm-label="Reject"><?= icon('lock', 16) ?></button></form>
                        <?php else: ?>
                        <form method="post" class="inline"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="reset_password">
                            <button type="button" class="icon-btn icon-action" aria-label="Reset password for <?= e($u['name']) ?>" title="Reset password" data-confirm="Generate a new temporary password for <?= e($u['name']) ?>? They will need to set a new password on next login." data-confirm-title="Reset password?" data-confirm-label="Reset" data-confirm-variant="primary"><?= icon('key', 16) ?></button></form>
                        <?php if ($u['status'] === 'active'): ?>
                            <form method="post" class="inline"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="disable">
                                <button type="button" class="icon-btn icon-action danger" aria-label="Disable <?= e($u['name']) ?>" title="Disable account" data-confirm="Disable <?= e($u['name']) ?>'s account? They will be signed out and unable to log in." data-confirm-title="Disable account?" data-confirm-label="Disable"><?= icon('lock', 16) ?></button></form>
                        <?php else: ?>
                            <form method="post" class="inline"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="enable">
                                <button type="button" class="icon-btn icon-action" aria-label="Enable <?= e($u['name']) ?>" title="Enable account" data-confirm="Re-enable <?= e($u['name']) ?>'s account?" data-confirm-title="Enable account?" data-confirm-label="Enable" data-confirm-variant="primary"><?= icon('check-circle', 16) ?></button></form>
                        <?php endif; ?>
                        <?php endif; ?>
                    </span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <div class="table-foot"><span>Showing <?= (int) ($pg['offset'] + 1) ?>–<?= (int) min($pg['offset'] + $pg['per'], $total) ?> of <?= (int) $total ?></span><?= renderPagination($pg, $baseParams) ?></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
