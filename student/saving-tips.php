<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/tips.php';
$user = requireStudent();
$uid  = (int) $user['id'];

[$start, $end] = monthBounds(date('Y-m'));
$income  = sumTransactions($uid, 'income', $start, $end);
$expense = sumTransactions($uid, 'expense', $start, $end);
$saved   = $income - $expense;
$goal    = (float) $user['monthly_savings_goal'];
$goalPct = $goal > 0 ? (int) max(0, min(100, round($saved / $goal * 100))) : 0;

$tips = buildSavingTips($uid);
$featured = getPublishedTips(12);
$topics = ['general' => 'General', 'food' => 'Food', 'entertainment' => 'Entertainment', 'transport' => 'Transport', 'shopping' => 'Shopping', 'academics' => 'Academics'];

$layout = 'student';
$pageTitle = 'Saving Tips';
require __DIR__ . '/../includes/header.php';
pageHead('Saving tips', 'Personalised from your spending in ' . monthLabel(date('Y-m')) . '.');
?>
<section class="card">
    <div class="card-body">
        <div class="profile-hero">
            <?php if ($goal > 0): ?>
                <div class="ring" style="--p:<?= (int) $goalPct ?>" role="img" aria-label="Savings goal <?= (int) $goalPct ?> percent complete"><span><?= (int) $goalPct ?>%</span></div>
                <div class="profile-meta">
                    <h2><?= $saved >= $goal ? 'You reached your savings goal 🎉' : 'Your monthly savings goal' ?></h2>
                    <p>Saved <strong><?= e(money($saved)) ?></strong> so far this month · goal <strong><?= e(money($goal)) ?></strong></p>
                </div>
            <?php else: ?>
                <span class="stat-icon blue" style="width:64px;height:64px;border-radius:20px"><?= icon('target', 30) ?></span>
                <div class="profile-meta"><h2>Set a monthly savings goal</h2><p>A target makes tips sharper and progress visible.</p></div>
                <a class="btn btn-primary" href="<?= e(url('student/profile.php')) ?>">Set goal</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="stack" aria-label="Personalised tips">
    <h2 class="card-title">For you this month</h2>
    <?php foreach ($tips as $t): ?>
        <article class="tip-item <?= e($t['level']) ?>">
            <span class="tx-icon"><?= icon($t['icon'], 20) ?></span>
            <div><h3><?= e($t['title']) ?></h3><p><?= e($t['text']) ?></p></div>
        </article>
    <?php endforeach; ?>
</section>

<section aria-label="Tips from CampusCoin">
    <h2 class="card-title mb-3">Tips from CampusCoin</h2>
    <?php if (!$featured): ?>
        <div class="card"><?= emptyState('bulb', 'No featured tips yet', 'New saving tips from the CampusCoin team will show up here.') ?></div>
    <?php else: ?>
        <div class="grid grid-3">
            <?php foreach ($featured as $f): ?>
                <article class="card tip-card"><div class="card-body">
                    <span class="badge blue"><?= e($topics[$f['topic']] ?? ucfirst($f['topic'])) ?></span>
                    <h3><?= e($f['title']) ?></h3><p><?= e($f['content']) ?></p>
                </div></article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
