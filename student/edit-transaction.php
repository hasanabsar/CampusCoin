<?php
require_once __DIR__ . '/../includes/auth.php';
$user = requireStudent();

// Ownership check: the row must belong to the logged-in student — never trust the id alone.
$id = (int) query('id');
$editTx = $id > 0 ? dbRow('SELECT * FROM transactions WHERE id = ? AND user_id = ?', [$id, (int) $user['id']]) : null;
if (!$editTx) {
    renderErrorPage(404, 'Transaction not found', 'This transaction does not exist or you do not have access to it.', 'search', null,
        ['label' => 'Back to transactions', 'href' => url('student/transactions.php')]);
}
$type = $editTx['type'];
require __DIR__ . '/../includes/transaction_form.php';
