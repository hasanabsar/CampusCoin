<?php
/** Public navigation (layout "public") or app top bar (student/admin). */
if ($layout === 'public'):
    $home = url('index.php');
    ?>
<header class="site-header" id="siteHeader">
    <div class="container header-inner">
        <a class="brand" href="<?= e($home) ?>" aria-label="CampusCoin home"><?= logoFull('light', 38) ?></a>
        <nav class="public-nav" id="publicNav" aria-label="Primary">
            <a href="<?= e($home) ?>#top">Home</a>
            <a href="<?= e($home) ?>#features">Features</a>
            <a href="<?= e($home) ?>#how-it-works">How it works</a>
            <a href="<?= e($home) ?>#about">About</a>
            <a href="<?= e($home) ?>#contact">Contact</a>
            <div class="public-nav-cta">
                <?php if ($authUser): ?>
                    <a class="btn btn-accent" href="<?= e(url(homeFor($authUser))) ?>">Open dashboard</a>
                <?php else: ?>
                    <a class="btn btn-ghost-light" href="<?= e(url('login.php?admin=1')) ?>"><?= icon('shield', 16) ?> Admin</a>
                    <a class="btn btn-ghost-light" href="<?= e(url('login.php')) ?>">Login</a>
                    <a class="btn btn-accent" href="<?= e(url('register.php')) ?>">Get Started</a>
                <?php endif; ?>
            </div>
        </nav>
        <button type="button" class="icon-btn nav-toggle" id="navToggle" aria-label="Toggle menu" aria-controls="publicNav" aria-expanded="false"><?= icon('menu', 22) ?></button>
    </div>
</header>
<?php else:
    $notifications = $layout === 'student' ? getNotifications((int) $authUser['id']) : [];
    ?>
<header class="topbar">
    <button type="button" class="icon-btn menu-btn" data-sidebar-toggle aria-label="Open menu" aria-controls="sidebar"><?= icon('menu', 22) ?></button>

    <?php if ($layout === 'student'): ?>
        <form method="post" action="<?= e(url('logout.php')) ?>">
    <?= csrfField() ?>
    <button
        type="submit"
        class="dropdown-link"
        data-confirm="Are you sure you want to logout from CampusCoin?"
        data-confirm-title="Confirm Logout"
        data-confirm-label="Logout"
    >
        <?= icon('logout', 17) ?> Logout
    </button>
</form>
    <?php else: ?>
        <div class="topbar-title"><?= icon('shield', 18) ?> Admin console</div>
    <?php endif; ?>

    <div class="topbar-actions">
        <?php if ($layout === 'student'): ?>
        <div class="dropdown" data-dropdown>
            <button type="button" class="icon-btn notif-btn" data-dropdown-toggle aria-haspopup="true" aria-expanded="false" aria-label="Notifications (<?= count($notifications) ?>)">
                <?= icon('bell', 21) ?>
                <?php if ($notifications): ?><span class="notif-dot"><?= count($notifications) ?></span><?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-wide" hidden>
                <div class="dropdown-head">Notifications</div>
                <?php if (!$notifications): ?>
                    <div class="dropdown-empty"><?= icon('check-circle', 22) ?><span>You're all caught up.</span></div>
                <?php else: foreach ($notifications as $n): ?>
                    <a class="notif-item notif-<?= e($n['type']) ?>" href="<?= e($n['href']) ?>">
                        <span class="notif-icon"><?= icon($n['icon'], 18) ?></span>
                        <span><strong><?= e($n['title']) ?></strong><small><?= e($n['text']) ?></small></span>
                    </a>
                <?php endforeach; endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="dropdown" data-dropdown>
            <button type="button" class="user-chip" data-dropdown-toggle aria-haspopup="true" aria-expanded="false">
                <?= avatarHtml($authUser, 36) ?>
                <span class="user-chip-name"><?= e($authUser['name']) ?><small><?= $layout === 'admin' ? 'Administrator' : e($authUser['academic_year'] ?: 'Student') ?></small></span>
                <?= icon('chevron-down', 16) ?>
            </button>
            <div class="dropdown-menu" hidden>
                <?php if ($layout === 'student'): ?>
                    <a class="dropdown-link" href="<?= e(url('student/profile.php')) ?>"><?= icon('user', 17) ?> My profile</a>
                <?php endif; ?>
                <form method="post" action="<?= e(url('logout.php')) ?>">
                    <?= csrfField() ?>
                    <button type="submit" class="dropdown-link"><?= icon('logout', 17) ?> Logout</button>
                </form>
            </div>
        </div>
    </div>
</header>
<?php endif; ?>
