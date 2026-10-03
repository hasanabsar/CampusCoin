<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/AIService.php';
$user = requireStudent();
$uid  = (int) $user['id'];

$ym = normalizeMonth(post('month') ?: query('month'));

if (isPost()) {
    requireCsrf();
    if (post('action') === 'generate') {
        $r = AIService::generateAndStore($uid, $ym);
        flash('success', $r['source'] === 'ai' ? 'AI insight generated.' : 'Insight generated from your spending data.');
    }
    header('Location: ' . url('student/ai-insights.php?month=' . $ym));
    exit;
}

$insight = dbRow('SELECT summary, tip, generated_at FROM insights WHERE user_id = ? AND month = ?', [$uid, $ym]);
$history = dbAll('SELECT month, summary FROM insights WHERE user_id = ? AND month <> ? ORDER BY month DESC LIMIT 5', [$uid, $ym]);
$advice  = array_slice(buildSavingTips($uid), 0, 3);
$expenseCats = getCategories($uid, 'expense');
$aiOn = AIService::enabled();

$layout = 'student';
$pageTitle = 'AI Insights';
require __DIR__ . '/../includes/header.php';
pageHead('AI insights', 'Smart suggestions and plain-language summaries of your spending.',
    '<div class="month-nav"><a href="?month=' . e(shiftMonth($ym, -1)) . '" aria-label="Previous month">' . icon('chevron-left', 18) . '</a><span>' . e(monthLabel($ym, true)) . '</span>'
    . ($ym >= date('Y-m') ? '<span class="text-muted">' . icon('chevron-right', 18) . '</span>' : '<a href="?month=' . e(shiftMonth($ym, 1)) . '" aria-label="Next month">' . icon('chevron-right', 18) . '</a>') . '</div>');
?>
<div class="alert <?= $aiOn ? 'alert-success' : 'alert-info' ?>">
    <?= icon($aiOn ? 'sparkles' : 'info', 20) ?>
    <span><?php if ($aiOn): ?><b>AI is connected</b> (<?= e(AIService::provider()) ?>). Only aggregated totals are sent — never your name or individual transactions.
        <?php else: ?><b>Running in smart-rules mode.</b> Everything below works without an AI service. To enable AI, add an API key to the <code>.env</code> file (see README).<?php endif; ?></span>
</div>

<div class="grid grid-main">
    <section class="card">
        <div class="card-head"><div><h2 class="card-title">Monthly summary</h2><p class="card-sub"><?= e(monthLabel($ym)) ?></p></div>
            <form method="post" class="inline" data-loading><?= csrfField() ?><input type="hidden" name="action" value="generate"><input type="hidden" name="month" value="<?= e($ym) ?>">
                <button type="submit" class="btn btn-primary btn-sm"><span class="btn-label"><?= icon($insight ? 'refresh' : 'sparkles', 16) ?> <?= $insight ? 'Refresh' : 'Generate insight' ?></span></button></form></div>
        <div class="card-body">
            <?php if (!$insight): ?>
                <?= emptyState('sparkles', 'No insight yet for ' . monthLabel($ym, true), 'Generate a summary to see how your spending changed and what to do about it.') ?>
            <?php else: ?>
                <p style="font-size:16.5px;line-height:1.7"><?= e($insight['summary']) ?></p>
                <div class="tip-item info mt-3"><span class="tx-icon"><?= icon('bulb', 20) ?></span><div><h3>Suggestion</h3><p><?= e($insight['tip']) ?></p></div></div>
                <p class="text-muted text-sm mt-2">Generated <?= e(formatDate($insight['generated_at'], 'd M Y, H:i')) ?></p>
            <?php endif; ?>
        </div>
    </section>

    <section class="card">
        <div class="card-head"><div><h2 class="card-title">Category suggestion</h2><p class="card-sub">Describe an expense — we pick the category.</p></div></div>
        <div class="card-body">
            <form id="suggestForm" data-url="<?= e(url('api/suggest-category.php')) ?>" data-add-url="<?= e(url('student/add-expense.php')) ?>" novalidate>
                <div class="field"><label for="suggestText">Expense description</label>
                    <input id="suggestText" class="input" maxlength="120" placeholder="e.g. Lunch at Campus Cafe" autocomplete="off"></div>
                <button type="submit" class="btn btn-accent"><span class="btn-label"><?= icon('sparkles', 16) ?> Suggest category</span></button>
            </form>
            <div id="suggestResult" class="suggest-chip mt-3" hidden style="flex-direction:column;align-items:stretch">
                <div>Suggested: <strong id="suggestName"></strong> <span class="text-muted">(via <span id="suggestSource"></span>)</span></div>
                <div class="row wrap">
                    <a class="btn btn-primary btn-sm" id="suggestAccept" href="#">Accept &amp; add expense</a>
                    <button type="button" class="btn btn-outline btn-sm" id="suggestChangeToggle">Change</button>
                </div>
                <div class="row wrap" id="suggestChangeRow" hidden>
                    <select id="suggestChange" class="select" aria-label="Choose a different category" style="flex:1;min-height:38px">
                        <?php foreach ($expenseCats as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
                    </select>
                    <a class="btn btn-primary btn-sm" id="suggestChangeGo" href="#">Use this</a>
                </div>
            </div>
        </div>
    </section>
</div>

<section class="stack">
    <h2 class="card-title">Personalised saving advice</h2>
    <?php foreach ($advice as $t): ?>
        <article class="tip-item <?= e($t['level']) ?>"><span class="tx-icon"><?= icon($t['icon'], 20) ?></span><div><h3><?= e($t['title']) ?></h3><p><?= e($t['text']) ?></p></div></article>
    <?php endforeach; ?>
</section>

<?php if ($history): ?>
<section class="card">
    <div class="card-head"><h2 class="card-title">Previous summaries</h2></div>
    <div class="card-body tight"><ul class="mini-list">
        <?php foreach ($history as $h): ?>
            <li><div><strong><?= e(monthLabel($h['month'])) ?></strong><small><?= e(mb_strimwidth($h['summary'], 0, 170, '…')) ?></small></div>
                <a class="btn btn-outline btn-sm" href="?month=<?= e($h['month']) ?>">Open</a></li>
        <?php endforeach; ?>
    </ul></div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
