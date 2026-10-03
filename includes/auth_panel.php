<?php /** Left-hand marketing panel used by the auth pages. Expects $panelTitle, $panelText. */ ?>
<aside class="auth-panel" aria-label="About CampusCoin">
    <a href="<?= e(url('index.php')) ?>" aria-label="CampusCoin home"><?= logoFull('light', 40) ?></a>
    <div>
        <h2><?= e($panelTitle) ?></h2>
        <p><?= e($panelText) ?></p>
        <img class="auth-illus" src="<?= e(asset('images/illustrations/auth.svg')) ?>" alt="Illustration of a savings dashboard with a rising bar chart and a coin" width="460" height="354">
    </div>
    <ul class="auth-points">
        <li><?= icon('check-circle', 20) ?> Track income and expenses in seconds</li>
        <li><?= icon('check-circle', 20) ?> Budgets that warn you before you overspend</li>
        <li><?= icon('check-circle', 20) ?> Clear reports built for student life</li>
    </ul>
</aside>
