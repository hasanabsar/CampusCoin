<?php
/**
 * CampusCoin — rule-based saving tips.
 * Works entirely on the student's own data; no AI required.
 */

/** Sum spending for categories whose name contains any of the keywords. */
function spendMatching(array $rows, array $keywords): float
{
    $sum = 0.0;
    foreach ($rows as $r) {
        $name = mb_strtolower($r['name']);
        foreach ($keywords as $k) {
            if (str_contains($name, $k)) {
                $sum += (float) $r['total'];
                break;
            }
        }
    }
    return $sum;
}

/**
 * Build personalised tips for the current month.
 * @return array<int, array{level:string, icon:string, title:string, text:string}>
 */
function buildSavingTips(int $userId): array
{
    $ym = date('Y-m');
    [$start, $end] = monthBounds($ym);
    [$pStart, $pEnd] = monthBounds(shiftMonth($ym, -1));

    $income      = sumTransactions($userId, 'income', $start, $end);
    $expense     = sumTransactions($userId, 'expense', $start, $end);
    $prevExpense = sumTransactions($userId, 'expense', $pStart, $pEnd);
    $rows        = categoryBreakdown($userId, $start, $end);
    $goal        = (float) dbValue('SELECT monthly_savings_goal FROM users WHERE id = ?', [$userId]);
    $budgets     = getBudgetUsage($userId, $ym);

    $tips = [];
    $add = function (string $level, string $icon, string $title, string $text) use (&$tips) {
        $tips[] = ['level' => $level, 'icon' => $icon, 'title' => $title, 'text' => $text];
    };

    if ($expense <= 0 && $income <= 0) {
        $add('info', 'bulb', 'Start with the basics', 'Log your allowance and your first few expenses this month. CampusCoin will turn them into personalised saving tips.');
    }

    /* Budget alerts (highest priority) */
    foreach ($budgets as $b) {
        $pct = (int) round($b['level']['pct']);
        if ($b['level']['key'] === 'over') {
            $add('warn', 'alert', $b['name'] . ' budget exceeded', 'You have spent ' . money($b['used']) . ' against a ' . money($b['limit_amount']) . ' budget (' . $pct . '%). Pause non-essential ' . mb_strtolower($b['name']) . ' spending for the rest of the month.');
        } elseif ($b['level']['key'] === 'near') {
            $add('warn', 'alert', 'Your ' . $b['name'] . ' budget is ' . $pct . '% used', 'Only ' . money($b['remaining']) . ' left this month. Plan the remaining days carefully.');
        }
    }

    /* Spending more than earning */
    if ($income > 0 && $expense > $income) {
        $add('warn', 'trending-up', 'You are spending more than you earn', 'Expenses are ' . money($expense - $income) . ' above income this month. Trim the largest discretionary category first.');
    }

    /* Food delivery */
    $del = dbRow(
        "SELECT COALESCE(SUM(amount), 0) AS total, COUNT(*) AS n FROM transactions
         WHERE user_id = ? AND type = 'expense' AND date BETWEEN ? AND ? AND description REGEXP ?",
        [$userId, $start, $end, '(delivery|foodpanda|uber ?eats|takeaway|takeout)']
    );
    if ($del && (int) $del['n'] >= 2) {
        $avg = (float) $del['total'] / (int) $del['n'];
        $save = round($avg * min((int) $del['n'], 4));
        $add('warn', 'coffee', 'Food delivery is adding up', 'You spent ' . money($del['total']) . ' on food delivery this month (' . (int) $del['n'] . ' orders). Replacing one order per week with a campus meal could save about ' . money($save) . '.');
    }

    /* Category share rules */
    $food = spendMatching($rows, ['food', 'dining', 'grocer', 'cafe', 'meal']);
    if ($expense > 0 && $food / $expense >= 0.30) {
        $add('info', 'coffee', 'Food is your biggest lever', 'Food makes up ' . round($food / $expense * 100) . '% of your spending (' . money($food) . '). Meal-prepping or using the campus cafeteria a few days a week can cut this quickly.');
    }
    $fun = spendMatching($rows, ['entertain', 'movie', 'game', 'leisure', 'fun']);
    if ($fun > 0 && (($expense > 0 && $fun / $expense >= 0.15) || ($income > 0 && $fun / $income >= 0.10))) {
        $add('info', 'sparkles', 'Entertainment spending is high', 'You spent ' . money($fun) . ' on entertainment. Look for student discounts or free campus events, and cap outings at a weekly amount.');
    }
    $shop = spendMatching($rows, ['shop', 'cloth']);
    if ($expense > 0 && $shop / $expense >= 0.20) {
        $add('info', 'tag', 'Shopping is taking a big share', 'Shopping is ' . round($shop / $expense * 100) . '% of your expenses. Try a 48-hour rule: wait two days before any non-essential purchase.');
    }
    $transport = spendMatching($rows, ['transport', 'travel', 'fuel', 'ride']);
    if ($expense > 0 && $transport / $expense >= 0.20) {
        $add('info', 'repeat', 'Transport costs stand out', 'Transport is ' . money($transport) . ' this month. A monthly pass, carpooling, or bundling trips could reduce the total.');
    }

    /* Trend vs last month */
    if ($prevExpense > 0 && $expense > $prevExpense * 1.2) {
        $add('info', 'trending-up', 'Spending is up on last month', 'You have spent ' . round(($expense / $prevExpense - 1) * 100) . '% more than last month (' . money($expense) . ' vs ' . money($prevExpense) . ').');
    }

    /* Savings goal */
    if ($goal > 0) {
        $saved = $income - $expense;
        if ($saved >= $goal) {
            $add('good', 'target', 'Savings goal reached', 'You have saved ' . money($saved) . ' this month — above your ' . money($goal) . ' goal. Consider moving the surplus into a savings account.');
        } elseif ($income > 0) {
            $daysLeft = max(1, (int) date('t') - (int) date('j') + 1);
            $gap = $goal - $saved;
            $add('info', 'target', 'You are ' . money($gap) . ' from your savings goal', 'Keeping daily spending about ' . money(ceil($gap / $daysLeft)) . ' lower for the remaining ' . $daysLeft . ' days would get you there.');
        }
    }

    /* Setup nudges */
    if (!$budgets && $expense > 0) {
        $add('info', 'wallet', 'Set your first budget', 'Budgets make overspending visible before it happens. Start with Food and Transport — the categories students overspend on most.');
    }
    if (!$tips) {
        $add('good', 'check-circle', 'You are on track', 'Nothing unusual this month. Keep logging expenses and try the 50/30/20 rule: needs, wants, savings.');
    }

    $order = ['warn' => 0, 'info' => 1, 'good' => 2];
    usort($tips, fn($a, $b) => $order[$a['level']] <=> $order[$b['level']]);
    return $tips;
}

/** Admin-published tips (optionally limited). */
function getPublishedTips(int $limit = 12): array
{
    return dbAll('SELECT id, title, content, topic FROM saving_tips WHERE is_published = 1 ORDER BY created_at DESC, id DESC LIMIT ' . (int) $limit);
}
