<?php
require_once __DIR__ . '/../includes/auth.php';
$user = requireStudent();
$type = 'expense';
require __DIR__ . '/../includes/transaction_form.php';
