<?php
require_once __DIR__ . '/../includes/auth.php';
$user = requireStudent();
$type = 'income';
require __DIR__ . '/../includes/transaction_form.php';
