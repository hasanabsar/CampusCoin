<?php
require_once __DIR__ . '/includes/auth.php';
$layout = 'public';
$pageTitle = 'Privacy Policy';
require __DIR__ . '/includes/header.php';
?>
<div class="container">
<article class="legal">
    <h1>Privacy Policy</h1>
    <p class="text-muted">Last updated: <?= date('F Y') ?></p>
    <p>CampusCoin helps students track their own money. This page explains what we store and how it is protected. (This is a template for the development build — have it reviewed before launching publicly.)</p>
    <h2>What we store</h2>
    <ul>
        <li>Account details: name, email, academic year and savings goal.</li>
        <li>Your income, expenses, categories and budgets.</li>
        <li>Passwords are stored only as salted hashes — never in plain text.</li>
    </ul>
    <h2>Who can see your data</h2>
    <p>Only you can view your transactions. Administrators can manage accounts and see aggregate, anonymous statistics, but not individual transactions.</p>
    <h2>Optional AI features</h2>
    <p>If the operator enables AI insights, only aggregated totals (for example spending per category) are sent to the AI provider — never your name, email or individual transaction records.</p>
    <h2>Your choices</h2>
    <p>You can edit your profile at any time and contact us to close your account.</p>
</article>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
