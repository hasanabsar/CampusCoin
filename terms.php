<?php
require_once __DIR__ . '/includes/auth.php';
$layout = 'public';
$pageTitle = 'Terms of Service';
require __DIR__ . '/includes/header.php';
?>
<div class="container">
<article class="legal">
    <h1>Terms of Service</h1>
    <p class="text-muted">Last updated: <?= date('F Y') ?></p>
    <p>By using CampusCoin you agree to these terms. (Template text for the development build — have it reviewed before launching publicly.)</p>
    <h2>Using CampusCoin</h2>
    <p>CampusCoin is a personal budgeting tool. You are responsible for the accuracy of the information you enter and for keeping your password secure.</p>
    <h2>Not financial advice</h2>
    <p>Saving tips and insights are automated suggestions based on your own entries. They are not professional financial advice.</p>
    <h2>Acceptable use</h2>
    <ul>
        <li>Do not attempt to access other users' data or disrupt the service.</li>
        <li>Accounts that violate these terms may be disabled.</li>
    </ul>
    <h2>Changes</h2>
    <p>We may update these terms from time to time; continued use means you accept the updated terms.</p>
</article>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
