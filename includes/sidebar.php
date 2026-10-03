<?php
/** App sidebar (student or admin). Uses $layout and the current script name. */
$script = basename($_SERVER['SCRIPT_NAME'], '.php');
$aliases = ['edit-transaction' => 'transactions'];
$active = $aliases[$script] ?? $script;

if ($layout === 'admin') {
    $groups = [
        'Console' => [
            ['dashboard', 'Dashboard', 'grid', 'admin/dashboard.php'],
            ['users', 'Users', 'users', 'admin/users.php'],
            ['categories', 'Categories', 'tag', 'admin/categories.php'],
        ],
        'Content' => [
            ['tips', 'Saving Tips', 'bulb', 'admin/tips.php'],
            ['announcements', 'Announcements', 'megaphone', 'admin/announcements.php'],
            ['statistics', 'Statistics', 'bar-chart', 'admin/statistics.php'],
        ],
    ];
} else {
    $groups = [
        'Overview' => [
            ['dashboard', 'Dashboard', 'grid', 'student/dashboard.php'],
            ['transactions', 'Transactions', 'repeat', 'student/transactions.php'],
        ],
        'Money' => [
            ['add-income', 'Add Income', 'plus-circle', 'student/add-income.php'],
            ['add-expense', 'Add Expense', 'minus-circle', 'student/add-expense.php'],
            ['categories', 'Categories', 'tag', 'student/categories.php'],
            ['budgets', 'Budgets', 'wallet', 'student/budgets.php'],
        ],
        'Insights' => [
            ['reports', 'Reports', 'bar-chart', 'student/reports.php'],
            ['saving-tips', 'Saving Tips', 'bulb', 'student/saving-tips.php'],
            ['ai-insights', 'AI Insights', 'sparkles', 'student/ai-insights.php'],
        ],
        'Account' => [
            ['profile', 'Profile', 'user', 'student/profile.php'],
        ],
    ];
}
?>
<aside class="sidebar" id="sidebar" aria-label="Main navigation">
    <div class="sidebar-top">
        <a class="sidebar-brand" href="<?= e(url(homeFor($authUser))) ?>" aria-label="CampusCoin dashboard"><?= logoFull('light', 36) ?></a>
        <button type="button" class="icon-btn sidebar-close" data-sidebar-toggle aria-label="Close menu"><?= icon('x') ?></button>
    </div>
    <nav class="nav-scroll">
        <?php foreach ($groups as $title => $items): ?>
            <div class="nav-group">
                <div class="nav-group-title"><?= e($title) ?></div>
                <ul class="nav-list">
                    <?php foreach ($items as [$key, $label, $ico, $href]): ?>
                        <li>
                            <a class="nav-link<?= $active === $key ? ' active' : '' ?>" href="<?= e(url($href)) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>>
                                <?= icon($ico, 19) ?><span><?= e($label) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </nav>
    <form class="sidebar-foot" method="post" action="<?= e(url('logout.php')) ?>">
    <?= csrfField() ?>
    <button
        type="submit"
        class="nav-link nav-logout"
        data-confirm="Are you sure you want to logout from CampusCoin?"
        data-confirm-title="Confirm Logout"
        data-confirm-label="Logout"
    >
        <?= icon('logout', 19) ?>
        <span>Logout</span>
    </button>
</form>
</aside>
<div class="sidebar-overlay" data-sidebar-toggle></div>
