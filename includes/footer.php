<?php
/** Page shell — closing: public footer, toasts, confirm modal, scripts. */
$layout    = $layout ?? 'public';
$useCharts = $useCharts ?? false;
$isApp     = in_array($layout, ['student', 'admin'], true);

if ($isApp) {
    echo "        </main>\n    </div>\n</div>\n";
} else {
    echo "    </main>\n";
}

if ($layout === 'public'): ?>
<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-brand">
            <a href="<?= e(url('index.php')) ?>" aria-label="CampusCoin home"><?= logoFull('light', 38) ?></a>
            <p><?= e(APP_TAGLINE) ?>. Built for students who want clarity, not spreadsheets.</p>
        </div>
        <nav aria-label="Product">
            <h2>Product</h2>
            <a href="<?= e(url('index.php#about')) ?>">About</a>
            <a href="<?= e(url('index.php#features')) ?>">Features</a>
            <a href="<?= e(url('index.php#how-it-works')) ?>">How it works</a>
            <a href="<?= e(url('index.php#contact')) ?>">Contact</a>
        </nav>
        <nav aria-label="Account">
            <h2>Account</h2>
            <a href="<?= e(url('login.php')) ?>">Login</a>
            <a href="<?= e(url('register.php')) ?>">Register</a>
            <a href="<?= e(url('forgot-password.php')) ?>">Forgot password</a>
        </nav>
        <nav aria-label="Legal">
            <h2>Legal</h2>
            <a href="<?= e(url('privacy.php')) ?>">Privacy</a>
            <a href="<?= e(url('terms.php')) ?>">Terms</a>
        </nav>
    </div>
    <div class="container footer-base">
        <span>&copy; <?= date('Y') ?> CampusCoin. All rights reserved.</span>
        <span>Made for students, with care.</span>
    </div>
</footer>
<?php endif; ?>

<div class="toast-root" id="toastRoot" aria-live="polite" aria-atomic="false"
     data-flash="<?= e(jsonForHtml(pullFlash())) ?>"></div>

<dialog class="modal modal-sm" id="confirmModal" aria-labelledby="confirmTitle">
    <div class="modal-body center">
        <div class="modal-icon" id="confirmIcon"><?= icon('alert', 26) ?></div>
        <h2 id="confirmTitle">Are you sure?</h2>
        <p id="confirmText"></p>
    </div>
    <div class="modal-foot">
        <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmOk">Confirm</button>
    </div>
</dialog>

<?php if (!empty($pageData)): ?>
<script id="page-data" type="application/json"><?= jsonForHtml($pageData) ?></script>
<?php endif; ?>

<?php if ($useCharts): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>window.Chart || document.write('<script src="<?= e(asset('js/vendor/chart.umd.min.js')) ?>"><\/script>');</script>
<script src="<?= e(asset('js/charts.js')) ?>"></script>
<script src="<?= e(asset('js/dashboard.js')) ?>"></script>
<?php endif; ?>
<script src="<?= e(asset('js/validation.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
