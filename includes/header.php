<?php
/**
 * Page shell — opening. Set before including:
 *   $pageTitle (string), $layout ('public'|'auth'|'student'|'admin'), $useCharts (bool)
 */
$layout     = $layout ?? 'public';
$pageTitle  = $pageTitle ?? APP_NAME;
$useCharts  = $useCharts ?? false;
$authUser   = currentUser();
$isApp      = in_array($layout, ['student', 'admin'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
<meta name="description" content="<?= e(APP_NAME . ' — ' . APP_TAGLINE) ?>. Track income, control spending and build better financial habits.">
<meta name="csrf-token" content="<?= e(csrfToken()) ?>">
<meta name="app-base" content="<?= e(BASE_URL) ?>">
<meta name="theme-color" content="#0B1B3A">
<link rel="icon" href="<?= e(asset('images/logo/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/responsive.css')) ?>">
</head>
<body class="layout-<?= e($layout) ?>">
<a class="skip-link" href="#main">Skip to main content</a>
<?php if ($isApp): ?>
<div class="app-shell">
    <?php require __DIR__ . '/sidebar.php'; ?>
    <div class="app-main">
        <?php require __DIR__ . '/navbar.php'; ?>
        <main id="main" class="app-content" tabindex="-1">
<?php elseif ($layout === 'public'): ?>
    <?php require __DIR__ . '/navbar.php'; ?>
    <main id="main">
<?php else: ?>
    <main id="main" class="auth-main">
<?php endif; ?>
