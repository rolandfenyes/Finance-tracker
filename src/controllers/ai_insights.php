<?php

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../fx.php';

function ai_insights_show(PDO $pdo): void
{
    require_login();
    $userId = uid();
    $snapshot = ai_build_snapshot($pdo, $userId);

    view('ai/insights', [
        'pageTitle' => __('AI insights'),
        'pageDescription' => __('Let the built-in coach surface quick wins from your recent activity.'),
        'snapshot' => $snapshot,
    ]);
}

function ai_insights_generate(PDO $pdo): void
{
    verify_csrf();
    require_login();
    $userId = uid();
    $snapshot = ai_build_snapshot($pdo, $userId);

    $config = app_config('ai') ?? [];
    $provider = $config['provider'] ?? 'openai';
    if ($provider !== 'openai') {
        json_error(__('AI provider is not supported.'), 503);
    }

    $openai = $config['openai'] ?? [];
    $apiKey = trim((string)($openai['api_key'] ?? ''));
    if ($apiKey === '' && getenv('OPENAI_API_KEY')) {
        $apiKey = trim((string)getenv('OPENAI_API_KEY'));
    }
    if ($apiKey === '') {
        json_error(__('AI suggestions are not available because the integration is not configured.'), 503);
    }

    $model = trim((string)($openai['model'] ?? ''));
    if ($model === '') {
        $model = 'gpt-4o-mini';
    }
    $baseUrl = rtrim((string)($openai['base_url'] ?? ''), '/');
    if ($baseUrl === '') {
        $baseUrl = 'https://api.openai.com/v1';
    }
    $timeout = (int)($openai['timeout'] ?? 30);
    if ($timeout <= 0) {
        $timeout = 30;
    }

    $endpoint = $baseUrl . '/chat/completions';

    $messages = [
        [
            'role' => 'system',
            'content' => 'You are MyMoneyMap Coach, an empathetic financial assistant. Provide concise, actionable recommendations tailored to the data you receive. Use bullet points, keep the tone encouraging, and highlight the most impactful next steps.',
        ],
        [
            'role' => 'user',
            'content' => ai_snapshot_to_prompt($snapshot),
        ],
    ];

    $payload = [
        'model' => $model,
        'messages' => $messages,
        'temperature' => 0.4,
        'max_tokens' => 600,
    ];

    $ch = curl_init($endpoint);
    if ($ch === false) {
        json_error(__('Unable to initialize the AI request.'), 500);
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: ' . 'Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => $timeout,
    ]);

    $result = curl_exec($ch);
    if ($result === false) {
        $error = curl_error($ch);
        curl_close($ch);
        json_error(sprintf(__('Failed to contact the AI service: %s'), $error ?: 'Unknown error'), 502);
    }

    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($status === 0) {
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    }
    curl_close($ch);

    if ($status < 200 || $status >= 300) {
        $body = json_decode($result, true);
        $detail = '';
        if (is_array($body)) {
            $detail = (string)($body['error']['message'] ?? '');
        }
        if ($detail === '') {
            $detail = trim($result);
        }
        json_error(sprintf(__('AI service returned an error: %s'), $detail ?: 'Unknown error'), 502);
    }

    $data = json_decode($result, true);
    if (!is_array($data)) {
        json_error(__('Unexpected response from the AI service.'), 502);
    }

    $choices = $data['choices'] ?? [];
    $content = '';
    if ($choices && isset($choices[0]['message']['content'])) {
        $content = trim((string)$choices[0]['message']['content']);
    }

    if ($content === '') {
        json_error(__('The AI service did not return any suggestions.'), 502);
    }

    json_response([
        'success' => true,
        'suggestions' => $content,
        'snapshot' => $snapshot,
    ]);
}

function ai_build_snapshot(PDO $pdo, int $userId): array
{
    $main = strtoupper(fx_user_main($pdo, $userId) ?: 'HUF');
    $today = date('Y-m-d');
    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');
    $monthLabel = format_month_year($today);

    $incomeMain = 0.0;
    $spendingMain = 0.0;
    $categoryTotals = [];

    $txStmt = $pdo->prepare(<<<'SQL'
        SELECT
            t.amount,
            COALESCE(t.currency, ?) AS currency,
            t.kind,
            t.occurred_on,
            COALESCE(c.label, '') AS category
        FROM transactions t
        LEFT JOIN categories c ON c.id = t.category_id AND c.user_id = t.user_id
        WHERE t.user_id = ? AND t.occurred_on BETWEEN ?::date AND ?::date
    SQL);
    $txStmt->execute([$main, $userId, $monthStart, $monthEnd]);
    while ($row = $txStmt->fetch(PDO::FETCH_ASSOC)) {
        $currency = strtoupper((string)($row['currency'] ?? $main));
        $amount = (float)($row['amount'] ?? 0);
        $occurredOn = $row['occurred_on'] ?: $today;
        $converted = $currency === $main ? $amount : fx_convert($pdo, $amount, $currency, $main, $occurredOn);

        if (($row['kind'] ?? '') === 'income') {
            $incomeMain += $converted;
        } elseif (($row['kind'] ?? '') === 'spending') {
            $spendingMain += $converted;
            $category = trim((string)($row['category'] ?? ''));
            if ($category === '') {
                $category = __('Uncategorised');
            }
            if (!isset($categoryTotals[$category])) {
                $categoryTotals[$category] = 0.0;
            }
            $categoryTotals[$category] += $converted;
        }
    }

    arsort($categoryTotals);
    $topCategories = [];
    $spendingDenominator = $spendingMain > 0 ? $spendingMain : 1;
    foreach (array_slice($categoryTotals, 0, 5, true) as $label => $value) {
        $topCategories[] = [
            'label' => $label,
            'spending_main' => round($value, 2),
            'share_pct' => round(($value / $spendingDenominator) * 100, 1),
        ];
    }

    $savingsRate = 0.0;
    if ($incomeMain > 0.0) {
        $savingsRate = round(max(0.0, min(100.0, (($incomeMain - $spendingMain) / $incomeMain) * 100)), 1);
    }

    $recentStmt = $pdo->prepare(<<<'SQL'
        SELECT
            t.amount,
            COALESCE(t.currency, ?) AS currency,
            t.kind,
            t.occurred_on,
            COALESCE(c.label, '') AS category
        FROM transactions t
        LEFT JOIN categories c ON c.id = t.category_id AND c.user_id = t.user_id
        WHERE t.user_id = ?
        ORDER BY t.occurred_on DESC, t.id DESC
        LIMIT 8
    SQL);
    $recentStmt->execute([$main, $userId]);
    $recent = [];
    while ($row = $recentStmt->fetch(PDO::FETCH_ASSOC)) {
        $currency = strtoupper((string)($row['currency'] ?? $main));
        $amount = (float)($row['amount'] ?? 0);
        $occurredOn = $row['occurred_on'] ?: $today;
        $converted = $currency === $main ? $amount : fx_convert($pdo, $amount, $currency, $main, $occurredOn);
        $category = trim((string)($row['category'] ?? ''));
        if ($category === '') {
            $category = __('Uncategorised');
        }
        $recent[] = [
            'date' => $occurredOn,
            'kind' => $row['kind'] ?? '',
            'amount_main' => round($converted, 2),
            'category' => $category,
        ];
    }

    $efStmt = $pdo->prepare('SELECT total, target_amount, currency FROM emergency_fund WHERE user_id = ?');
    $efStmt->execute([$userId]);
    $efRow = $efStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $emergencyBalance = 0.0;
    $emergencyTarget = 0.0;
    if ($efRow) {
        $efCurrency = strtoupper((string)($efRow['currency'] ?? $main));
        $total = (float)($efRow['total'] ?? 0);
        $target = (float)($efRow['target_amount'] ?? 0);
        $emergencyBalance = $efCurrency === $main ? $total : fx_convert($pdo, $total, $efCurrency, $main, $today);
        $emergencyTarget = $efCurrency === $main ? $target : fx_convert($pdo, $target, $efCurrency, $main, $today);
    }
    $emergencyProgress = $emergencyTarget > 0 ? round(max(0.0, min(100.0, ($emergencyBalance / $emergencyTarget) * 100)), 1) : 0.0;

    $goalStmt = $pdo->prepare('SELECT status, target_amount, current_amount, currency FROM goals WHERE user_id = ?');
    $goalStmt->execute([$userId]);
    $goalActive = 0;
    $goalCompleted = 0;
    $goalCurrentMain = 0.0;
    $goalTargetMain = 0.0;
    while ($row = $goalStmt->fetch(PDO::FETCH_ASSOC)) {
        $currency = strtoupper((string)($row['currency'] ?? $main));
        $current = (float)($row['current_amount'] ?? 0);
        $target = (float)($row['target_amount'] ?? 0);
        $currentMain = $currency === $main ? $current : fx_convert($pdo, $current, $currency, $main, $today);
        $targetMain = $currency === $main ? $target : fx_convert($pdo, $target, $currency, $main, $today);
        $goalCurrentMain += $currentMain;
        $goalTargetMain += $targetMain;
        if (($row['status'] ?? 'active') === 'done') {
            $goalCompleted++;
        } else {
            $goalActive++;
        }
    }
    $goalProgress = $goalTargetMain > 0 ? round(max(0.0, min(100.0, ($goalCurrentMain / $goalTargetMain) * 100)), 1) : 0.0;

    $loanStmt = $pdo->prepare('SELECT balance, currency, interest_rate, finished_at, archived_at FROM loans WHERE user_id = ?');
    $loanStmt->execute([$userId]);
    $loanActive = 0;
    $loanFinished = 0;
    $loanBalanceMain = 0.0;
    $loanRateSum = 0.0;
    $loanRateCount = 0;
    while ($row = $loanStmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row['archived_at'])) {
            continue;
        }
        $balance = (float)($row['balance'] ?? 0);
        $currency = strtoupper((string)($row['currency'] ?? $main));
        $balanceMain = $currency === $main ? $balance : fx_convert($pdo, $balance, $currency, $main, $today);
        $isFinished = !empty($row['finished_at']) || $balanceMain <= 0.01;
        if ($isFinished) {
            $loanFinished++;
            continue;
        }
        $loanActive++;
        $loanBalanceMain += $balanceMain;
        $rate = (float)($row['interest_rate'] ?? 0);
        if ($rate > 0) {
            $loanRateSum += $rate;
            $loanRateCount++;
        }
    }
    $loanAverageRate = $loanRateCount > 0 ? round($loanRateSum / $loanRateCount, 2) : 0.0;

    return [
        'generated_at' => date(DATE_ATOM),
        'main_currency' => $main,
        'month' => [
            'label' => $monthLabel,
            'start' => $monthStart,
            'end' => $monthEnd,
            'income_main' => round($incomeMain, 2),
            'spending_main' => round($spendingMain, 2),
            'net_main' => round($incomeMain - $spendingMain, 2),
            'savings_rate_pct' => $savingsRate,
            'top_categories' => $topCategories,
        ],
        'emergency' => [
            'balance_main' => round($emergencyBalance, 2),
            'target_main' => round($emergencyTarget, 2),
            'progress_pct' => $emergencyProgress,
        ],
        'goals' => [
            'active_count' => $goalActive,
            'completed_count' => $goalCompleted,
            'current_main' => round($goalCurrentMain, 2),
            'target_main' => round($goalTargetMain, 2),
            'progress_pct' => $goalProgress,
        ],
        'loans' => [
            'active_count' => $loanActive,
            'finished_count' => $loanFinished,
            'balance_main' => round($loanBalanceMain, 2),
            'average_rate_pct' => $loanAverageRate,
        ],
        'recent_activity' => $recent,
    ];
}

function ai_snapshot_to_prompt(array $snapshot): string
{
    $main = $snapshot['main_currency'] ?? 'USD';
    $lines = [];
    $lines[] = 'Anonymized MyMoneyMap user snapshot:';
    $lines[] = 'Main currency: ' . $main . '.';

    $month = $snapshot['month'] ?? [];
    if ($month) {
        $lines[] = sprintf(
            'Current month (%s): income %s %s, spending %s %s, net %s %s, savings rate %s%%.',
            $month['label'] ?? '',
            number_format((float)($month['income_main'] ?? 0), 2, '.', ''),
            $main,
            number_format((float)($month['spending_main'] ?? 0), 2, '.', ''),
            $main,
            number_format((float)($month['net_main'] ?? 0), 2, '.', ''),
            $main,
            number_format((float)($month['savings_rate_pct'] ?? 0), 1, '.', '')
        );
    }

    if (!empty($month['top_categories'])) {
        $lines[] = 'Top spending categories this month:';
        foreach ($month['top_categories'] as $cat) {
            $lines[] = sprintf(
                '- %s: %s %s (%.1f%% of spending)',
                $cat['label'],
                number_format((float)($cat['spending_main'] ?? 0), 2, '.', ''),
                $main,
                (float)($cat['share_pct'] ?? 0)
            );
        }
    }

    $emergency = $snapshot['emergency'] ?? [];
    if ($emergency) {
        $lines[] = sprintf(
            'Emergency fund balance %s %s versus target %s %s (progress %.1f%%).',
            number_format((float)($emergency['balance_main'] ?? 0), 2, '.', ''),
            $main,
            number_format((float)($emergency['target_main'] ?? 0), 2, '.', ''),
            $main,
            (float)($emergency['progress_pct'] ?? 0)
        );
    }

    $goals = $snapshot['goals'] ?? [];
    if ($goals) {
        $lines[] = sprintf(
            'Goals: %d active, %d completed. Total saved %s %s out of %s %s (progress %.1f%%).',
            (int)($goals['active_count'] ?? 0),
            (int)($goals['completed_count'] ?? 0),
            number_format((float)($goals['current_main'] ?? 0), 2, '.', ''),
            $main,
            number_format((float)($goals['target_main'] ?? 0), 2, '.', ''),
            $main,
            (float)($goals['progress_pct'] ?? 0)
        );
    }

    $loans = $snapshot['loans'] ?? [];
    if ($loans) {
        $lines[] = sprintf(
            'Loans: %d active, %d finished. Outstanding balance %s %s with average interest %.2f%%.',
            (int)($loans['active_count'] ?? 0),
            (int)($loans['finished_count'] ?? 0),
            number_format((float)($loans['balance_main'] ?? 0), 2, '.', ''),
            $main,
            (float)($loans['average_rate_pct'] ?? 0)
        );
    }

    $recent = $snapshot['recent_activity'] ?? [];
    if ($recent) {
        $lines[] = 'Recent transactions (most recent first):';
        foreach ($recent as $item) {
            $lines[] = sprintf(
                '- %s %s %s in %s',
                $item['date'] ?? '',
                strtoupper((string)($item['kind'] ?? '')),
                number_format((float)($item['amount_main'] ?? 0), 2, '.', ''),
                $item['category'] ?? 'General'
            );
        }
    }

    $lines[] = 'Provide 3-5 personalised, practical suggestions focusing on budgeting, savings, goals, or debt payoff. Keep the tone encouraging and reference the data points where helpful.';

    return implode("\n", $lines);
}
