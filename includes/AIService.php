<?php
/**
 * CampusCoin — optional AI service.
 *
 * The application is fully functional WITHOUT this. When AI_ENABLED=true and an API key is
 * configured in .env, the methods below use the model; otherwise (or on any failure) they
 * fall back to local rule-based logic. Only aggregated numbers are ever sent to the API.
 */

require_once __DIR__ . '/tips.php';

final class AIService
{
    public static function enabled(): bool
    {
        return filter_var(env('AI_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN)
            && (string) env('AI_API_KEY', '') !== ''
            && (string) env('AI_MODEL', '') !== '';
    }

    public static function provider(): string
    {
        return strtolower((string) env('AI_PROVIDER', 'anthropic'));
    }

    /** Send one prompt to the configured provider. Returns text or null on failure. */
    public static function complete(string $system, string $prompt, int $maxTokens = 400): ?string
    {
        if (!self::enabled()) {
            return null;
        }
        try {
            $key = (string) env('AI_API_KEY');
            $model = (string) env('AI_MODEL');
            if (self::provider() === 'openai') {
                $res = self::http(
                    (string) env('AI_API_URL', 'https://api.openai.com/v1/chat/completions'),
                    ['Authorization: Bearer ' . $key],
                    ['model' => $model, 'max_tokens' => $maxTokens, 'temperature' => 0.4,
                     'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $prompt]]]
                );
                return isset($res['choices'][0]['message']['content']) ? trim((string) $res['choices'][0]['message']['content']) : null;
            }
            $res = self::http(
                (string) env('AI_API_URL', 'https://api.anthropic.com/v1/messages'),
                ['x-api-key: ' . $key, 'anthropic-version: 2023-06-01'],
                ['model' => $model, 'max_tokens' => $maxTokens, 'system' => $system,
                 'messages' => [['role' => 'user', 'content' => $prompt]]]
            );
            return isset($res['content'][0]['text']) ? trim((string) $res['content'][0]['text']) : null;
        } catch (Throwable $e) {
            error_log('CampusCoin AI error: ' . $e->getMessage());
            return null;
        }
    }

    private static function http(string $url, array $headers, array $body): ?array
    {
        $headers[] = 'Content-Type: application/json';
        $payload = json_encode($body);
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 8,
            ]);
            $raw = curl_exec($ch);
            curl_close($ch);
        } else {
            $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $payload, 'timeout' => 20, 'ignore_errors' => true]]);
            $raw = @file_get_contents($url, false, $ctx);
        }
        $data = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($data) ? $data : null;
    }

    /* ------------------------------------------------------------------
     * 1. Expense category suggestion
     * ---------------------------------------------------------------- */

    /** @return array{id:int,name:string,source:string}|null */
    public static function suggestCategory(int $userId, string $description): ?array
    {
        $description = trim(mb_substr($description, 0, 120));
        $cats = getCategories($userId, 'expense');
        if ($description === '' || !$cats) {
            return null;
        }
        $byName = [];
        foreach ($cats as $c) {
            $byName[mb_strtolower($c['name'])] = $c;
        }

        // AI first (when configured), validated against the student's own category list.
        if (self::enabled()) {
            $names = implode(', ', array_column($cats, 'name'));
            $out = self::complete(
                'You classify student expenses. Reply with ONLY one category name from the provided list, nothing else.',
                "Categories: {$names}\nExpense: {$description}",
                20
            );
            if ($out !== null && isset($byName[mb_strtolower(trim($out, " .\"'\n"))])) {
                $c = $byName[mb_strtolower(trim($out, " .\"'\n"))];
                return ['id' => (int) $c['id'], 'name' => $c['name'], 'source' => 'ai'];
            }
        }

        // The student's own history: same word used before → same category.
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($description), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        usort($tokens, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        if ($tokens && mb_strlen($tokens[0]) >= 4) {
            $like = '%' . addcslashes($tokens[0], '%_\\') . '%';
            $hit = dbRow(
                "SELECT category_id, COUNT(*) AS c FROM transactions
                 WHERE user_id = ? AND type = 'expense' AND description LIKE ?
                 GROUP BY category_id ORDER BY c DESC LIMIT 1",
                [$userId, $like]
            );
            if ($hit && ($cat = findUsableCategory($userId, (int) $hit['category_id'], 'expense'))) {
                return ['id' => (int) $cat['id'], 'name' => $cat['name'], 'source' => 'history'];
            }
        }

        // Keyword rules.
        $rules = [
            'food'          => ['lunch', 'dinner', 'breakfast', 'cafe', 'coffee', 'tea', 'pizza', 'burger', 'restaurant', 'snack', 'biryani', 'grocery', 'groceries', 'foodpanda', 'meal', 'canteen', 'juice', 'delivery', 'kfc', 'chai', 'paratha'],
            'transport'     => ['bus', 'uber', 'careem', 'taxi', 'rickshaw', 'fuel', 'petrol', 'metro', 'ride', 'fare', 'parking', 'train', 'bykea', 'commute'],
            'academics'     => ['book', 'books', 'stationery', 'tuition', 'exam', 'fee', 'fees', 'course', 'notes', 'photocopy', 'printing', 'lab', 'library', 'pen', 'notebook'],
            'entertainment' => ['movie', 'cinema', 'game', 'gaming', 'concert', 'party', 'outing', 'bowling', 'arcade', 'hangout'],
            'shopping'      => ['clothes', 'shoes', 'mall', 'amazon', 'daraz', 'shirt', 'jeans', 'bag', 'watch', 'shopping'],
            'health'        => ['medicine', 'pharmacy', 'doctor', 'clinic', 'hospital', 'gym', 'dentist'],
            'subscriptions' => ['netflix', 'spotify', 'youtube', 'subscription', 'icloud', 'prime', 'chatgpt'],
            'rent'          => ['rent', 'electricity', 'internet', 'wifi', 'bill', 'hostel', 'utility', 'utilities', 'gas'],
        ];
        $lower = mb_strtolower($description);
        $best = null;
        $bestScore = 0;
        foreach ($rules as $catKey => $words) {
            $score = 0;
            foreach ($words as $w) {
                if (in_array($w, $tokens, true) || (str_contains($w, ' ') && str_contains($lower, $w))) {
                    $score++;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $catKey;
            }
        }
        if ($best !== null) {
            foreach ($byName as $name => $c) {
                if (str_contains($name, $best)) {
                    return ['id' => (int) $c['id'], 'name' => $c['name'], 'source' => 'rules'];
                }
            }
        }
        if (isset($byName['other'])) {
            return ['id' => (int) $byName['other']['id'], 'name' => $byName['other']['name'], 'source' => 'fallback'];
        }
        return null;
    }

    /* ------------------------------------------------------------------
     * 2. Monthly summary + saving advice (stored in `insights`)
     * ---------------------------------------------------------------- */

    /** @return array{summary:string, tip:string, source:string} */
    public static function monthlySummary(int $userId, string $ym): array
    {
        [$s, $e] = monthBounds($ym);
        [$ps, $pe] = monthBounds(shiftMonth($ym, -1));
        $cur = categoryBreakdown($userId, $s, $e);
        $prev = categoryBreakdown($userId, $ps, $pe);
        $income = sumTransactions($userId, 'income', $s, $e);
        $spent = sumTransactions($userId, 'expense', $s, $e);
        $prevSpent = sumTransactions($userId, 'expense', $ps, $pe);

        $tips = buildSavingTips($userId);
        $ruleTip = $tips[0]['text'] ?? 'Keep logging every expense — awareness is the first step to saving.';

        if ($spent <= 0) {
            return ['summary' => 'No spending has been recorded for ' . monthLabel($ym) . ' yet. Add a few expenses and CampusCoin will summarise your habits here.', 'tip' => $ruleTip, 'source' => 'rules'];
        }

        // Change per category vs the previous month.
        $prevMap = [];
        foreach ($prev as $r) {
            $prevMap[$r['name']] = (float) $r['total'];
        }
        $changes = [];
        foreach ($cur as $r) {
            $changes[$r['name']] = (float) $r['total'] - ($prevMap[$r['name']] ?? 0.0);
        }
        arsort($changes);

        $facts = ['month' => monthLabel($ym), 'income' => $income, 'spent' => $spent, 'previous_month_spent' => $prevSpent,
                  'currency' => 'Rs.', 'top_categories' => array_slice(array_map(fn($r) => ['name' => $r['name'], 'total' => (float) $r['total']], $cur), 0, 5),
                  'change_vs_previous' => array_slice($changes, 0, 3, true)];

        if (self::enabled()) {
            $out = self::complete(
                "You are CampusCoin's friendly finance coach for university students. Using ONLY the JSON facts, write exactly two lines. "
                . "Line 1 starts with 'SUMMARY:' (2 sentences on how spending changed and why). Line 2 starts with 'TIP:' (one specific, practical saving tip). "
                . 'Plain text, no markdown, amounts written like Rs. 1,200.',
                json_encode($facts),
                300
            );
            if ($out !== null && preg_match('/SUMMARY:\s*(.+?)\s*\R+\s*TIP:\s*(.+)$/si', $out, $m)) {
                return ['summary' => trim($m[1]), 'tip' => trim($m[2]), 'source' => 'ai'];
            }
        }

        // Rule-based summary.
        $text = 'In ' . monthLabel($ym) . ' you spent ' . money($spent) . ' across ' . count($cur) . ' categories';
        if ($prevSpent > 0) {
            $pct = round(abs($spent / $prevSpent - 1) * 100);
            $text .= ', which is ' . $pct . '% ' . ($spent >= $prevSpent ? 'higher' : 'lower') . ' than ' . monthLabel(shiftMonth($ym, -1)) . ' (' . money($prevSpent) . ').';
            $up = array_filter($changes, fn($v) => $v > 0);
            $down = array_filter($changes, fn($v) => $v < 0);
            if ($spent >= $prevSpent && $up) {
                $parts = [];
                foreach (array_slice($up, 0, 2, true) as $n => $v) {
                    $parts[] = $n . ' (' . money($v, true) . ')';
                }
                $text .= ' The increase came mainly from ' . implode(' and ', $parts) . '.';
            } elseif ($down) {
                asort($down);
                $parts = [];
                foreach (array_slice($down, 0, 2, true) as $n => $v) {
                    $parts[] = $n . ' (' . money($v) . ')';
                }
                $text .= ' You cut back most on ' . implode(' and ', $parts) . '.';
            }
        } else {
            $text .= '. Your biggest category was ' . $cur[0]['name'] . ' at ' . money($cur[0]['total']) . '.';
        }
        if ($income > 0) {
            $text .= ' You kept ' . money($income - $spent) . ' (' . round(($income - $spent) / $income * 100) . '% of income).';
        }
        return ['summary' => $text, 'tip' => $ruleTip, 'source' => 'rules'];
    }

    /** Generate and store (upsert) the insight for a month. */
    public static function generateAndStore(int $userId, string $ym): array
    {
        $r = self::monthlySummary($userId, $ym);
        dbRun(
            'INSERT INTO insights (user_id, month, summary, tip) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE summary = VALUES(summary), tip = VALUES(tip), generated_at = CURRENT_TIMESTAMP',
            [$userId, $ym, $r['summary'], $r['tip']]
        );
        return $r;
    }
}
