<?php

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../fx.php';

function advanced_planner_show(PDO $pdo): void
{
    require_login();
    $userId = uid();
    $mainCurrency = fx_user_main($pdo, $userId) ?: 'HUF';

    $startParam = trim($_GET['start'] ?? '');
    $startMonth = $startParam !== ''
        ? DateTimeImmutable::createFromFormat('Y-m', $startParam) ?: new DateTimeImmutable('first day of next month')
        : new DateTimeImmutable('first day of next month');
    $startMonth = $startMonth->setDate((int)$startMonth->format('Y'), (int)$startMonth->format('n'), 1);

    $defaultHorizon = 3;

    $incomeData = advanced_planner_collect_incomes($pdo, $userId, $mainCurrency, $startMonth);
    $spendingCategories = advanced_planner_fetch_spending_categories($pdo, $userId);
    $averages = advanced_planner_average_spending($pdo, $userId, $mainCurrency, $startMonth, $spendingCategories, 3);

    $resources = advanced_planner_collect_resources($pdo, $userId, $mainCurrency);

    $plansStmt = $pdo->prepare(
        'SELECT * FROM advanced_plans WHERE user_id = ? ORDER BY (status = \'active\') DESC, plan_start DESC, id DESC'
    );
    $plansStmt->execute([$userId]);
    $plans = $plansStmt->fetchAll(PDO::FETCH_ASSOC);

    $requestedPlanId = isset($_GET['plan']) ? (int)$_GET['plan'] : null;
    $currentPlan = null;
    foreach ($plans as $plan) {
        if ($requestedPlanId && (int)$plan['id'] === $requestedPlanId) {
            $currentPlan = $plan;
            break;
        }
        if ($currentPlan === null && $plan['status'] === 'active') {
            $currentPlan = $plan;
        }
    }
    if ($currentPlan === null && $plans) {
        $currentPlan = $plans[0];
    }

    $planItems = [];
    $planCategoryLimits = [];
    if ($currentPlan) {
        $itemStmt = $pdo->prepare(
            'SELECT * FROM advanced_plan_items WHERE plan_id = ? ORDER BY priority ASC, sort_order ASC, id ASC'
        );
        $itemStmt->execute([(int)$currentPlan['id']]);
        $planItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        $limitStmt = $pdo->prepare(
            'SELECT * FROM advanced_plan_category_limits WHERE plan_id = ? ORDER BY category_label ASC'
        );
        $limitStmt->execute([(int)$currentPlan['id']]);
        $planCategoryLimits = $limitStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    view('advanced_planner/index', [
        'plans' => $plans,
        'currentPlan' => $currentPlan,
        'planItems' => $planItems,
        'planCategoryLimits' => $planCategoryLimits,
        'mainCurrency' => $mainCurrency,
        'startSuggestion' => $startMonth->format('Y-m'),
        'defaultHorizon' => $defaultHorizon,
        'incomeData' => $incomeData,
        'spendingCategories' => $spendingCategories,
        'averages' => $averages,
        'resources' => $resources,
    ]);
}

function advanced_planner_store(PDO $pdo): void
{
    verify_csrf();
    require_login();
    $userId = uid();
    $mainCurrency = fx_user_main($pdo, $userId) ?: 'HUF';

    $title = trim($_POST['title'] ?? '');
    if ($title === '') {
        $title = __('Advanced plan');
    }

    $horizon = (int)($_POST['horizon_months'] ?? 3);
    if (!in_array($horizon, [3, 6, 12], true)) {
        $horizon = 3;
    }

    $startMonthRaw = trim($_POST['start_month'] ?? '');
    $startMonth = $startMonthRaw !== ''
        ? DateTimeImmutable::createFromFormat('Y-m', $startMonthRaw) ?: new DateTimeImmutable('first day of next month')
        : new DateTimeImmutable('first day of next month');
    $startMonth = $startMonth->setDate((int)$startMonth->format('Y'), (int)$startMonth->format('n'), 1);
    $planStart = $startMonth->format('Y-m-01');
    $planEnd = $startMonth->modify('+' . $horizon . ' months')->modify('-1 day')->format('Y-m-d');

    $notes = trim($_POST['notes'] ?? '');
    $activate = !empty($_POST['activate']);

    $labels = $_POST['item_label'] ?? [];
    $types = $_POST['item_type'] ?? [];
    $targets = $_POST['item_target'] ?? [];
    $currents = $_POST['item_current'] ?? [];
    $priorities = $_POST['item_priority'] ?? [];
    $dueDates = $_POST['item_due'] ?? [];
    $references = $_POST['item_reference'] ?? [];
    $itemNotes = $_POST['item_notes'] ?? [];

    $items = [];
    $sortIndex = 0;
    foreach ($labels as $idx => $labelRaw) {
        $label = trim((string)$labelRaw);
        $kind = isset($types[$idx]) ? strtolower((string)$types[$idx]) : 'custom';
        if (!in_array($kind, ['emergency', 'investment', 'loan', 'goal', 'custom'], true)) {
            $kind = 'custom';
        }

        $target = isset($targets[$idx]) ? max(0.0, (float)$targets[$idx]) : 0.0;
        $current = isset($currents[$idx]) ? max(0.0, (float)$currents[$idx]) : 0.0;
        $required = max(0.0, $target - $current);
        if ($label === '' && $required <= 0) {
            continue;
        }
        $dueRaw = isset($dueDates[$idx]) ? trim((string)$dueDates[$idx]) : '';
        $normalizedDue = advanced_planner_normalize_due_month($dueRaw, $startMonth, $horizon);
        $fundingMonth = $normalizedDue ?? $startMonth->modify('+' . max(0, $horizon - 1) . ' months');
        $monthsToFund = advanced_planner_months_between($startMonth, $fundingMonth);
        $monthly = $monthsToFund > 0 ? round($required / $monthsToFund, 2) : 0.0;

        $priority = isset($priorities[$idx]) ? (int)$priorities[$idx] : ($sortIndex + 1);
        $referenceId = isset($references[$idx]) && $references[$idx] !== '' ? (int)$references[$idx] : null;
        $note = trim($itemNotes[$idx] ?? '');

        $items[] = [
            'label' => $label !== '' ? $label : __('Milestone :num', ['num' => $sortIndex + 1]),
            'kind' => $kind,
            'reference_id' => $referenceId,
            'target' => round($target, 2),
            'current' => round($current, 2),
            'required' => round($required, 2),
            'monthly' => $monthly,
            'priority' => $priority,
            'sort' => $sortIndex,
            'has_due' => $normalizedDue !== null,
            'due' => $normalizedDue ? $normalizedDue->format('Y-m-01') : null,
            'notes' => $note,
        ];
        $sortIndex++;
    }

    $hasDueDates = false;
    foreach ($items as $item) {
        if (!empty($item['has_due'])) {
            $hasDueDates = true;
            break;
        }
    }

    if ($items) {
        if ($hasDueDates) {
            usort($items, static function (array $a, array $b): int {
                if (!empty($a['has_due']) && !empty($b['has_due'])) {
                    $comparison = strcmp((string)$a['due'], (string)$b['due']);
                    if ($comparison !== 0) {
                        return $comparison;
                    }
                }
                if (!empty($a['has_due']) && empty($b['has_due'])) {
                    return -1;
                }
                if (empty($a['has_due']) && !empty($b['has_due'])) {
                    return 1;
                }
                $monthlyDiff = $a['monthly'] <=> $b['monthly'];
                if ($monthlyDiff !== 0) {
                    return $monthlyDiff;
                }
                return $a['sort'] <=> $b['sort'];
            });
        } else {
            usort($items, static function (array $a, array $b): int {
                $monthlyDiff = $a['monthly'] <=> $b['monthly'];
                if ($monthlyDiff !== 0) {
                    return $monthlyDiff;
                }
                $requiredDiff = $a['required'] <=> $b['required'];
                if ($requiredDiff !== 0) {
                    return $requiredDiff;
                }
                return $a['sort'] <=> $b['sort'];
            });
        }

        foreach ($items as $idx => &$item) {
            $item['priority'] = $idx + 1;
            $item['sort'] = $idx;
        }
        unset($item);
    }

    $incomeData = advanced_planner_collect_incomes($pdo, $userId, $mainCurrency, $startMonth);
    $monthlyIncome = (float)($incomeData['total'] ?? 0);

    $spendingCategories = advanced_planner_fetch_spending_categories($pdo, $userId);
    $averages = advanced_planner_average_spending($pdo, $userId, $mainCurrency, $startMonth, $spendingCategories, 3);

    $totalCommitments = 0.0;
    foreach ($items as $item) {
        $totalCommitments += $item['monthly'];
    }
    $totalCommitments = round($totalCommitments, 2);

    $monthlyDiscretionary = max(0.0, $monthlyIncome - $totalCommitments);

    $categorySuggestions = [];
    $totalAverage = 0.0;
    foreach ($averages['categories'] as $cat) {
        $totalAverage += (float)$cat['average'];
    }
    $scale = ($totalAverage > 0) ? ($monthlyDiscretionary / $totalAverage) : 0;
    foreach ($averages['categories'] as $cat) {
        $suggested = $totalAverage > 0
            ? round($cat['average'] * min(max($scale, 0), 5), 2)
            : (count($averages['categories']) ? round($monthlyDiscretionary / count($averages['categories']), 2) : 0.0);
        $categorySuggestions[] = [
            'category_id' => $cat['id'],
            'label' => $cat['label'],
            'average' => round($cat['average'], 2),
            'suggested' => $suggested,
        ];
    }

    $status = $activate ? 'active' : 'draft';

    $pdo->beginTransaction();
    try {
        if ($activate) {
            $pdo->prepare('UPDATE advanced_plans SET status = \'archived\', updated_at = NOW() WHERE user_id = ? AND status = \'active\'')
                ->execute([$userId]);
        }

        $planStmt = $pdo->prepare(
            'INSERT INTO advanced_plans (user_id, title, horizon_months, plan_start, plan_end, main_currency, total_budget, monthly_income, monthly_commitments, monthly_discretionary, status, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?) RETURNING id'
        );
        $totalBudget = round($monthlyIncome * $horizon, 2);
        $planStmt->execute([
            $userId,
            $title,
            $horizon,
            $planStart,
            $planEnd,
            $mainCurrency,
            $totalBudget,
            $monthlyIncome,
            $totalCommitments,
            $monthlyDiscretionary,
            $status,
            $notes,
        ]);
        $planId = (int)$planStmt->fetchColumn();

        if ($items) {
            $itemStmt = $pdo->prepare(
                'INSERT INTO advanced_plan_items (plan_id, kind, reference_id, reference_label, target_amount, current_amount, required_amount, monthly_allocation, priority, sort_order, target_due_date, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            foreach ($items as $item) {
                $itemStmt->execute([
                    $planId,
                    $item['kind'],
                    $item['reference_id'],
                    $item['label'],
                    $item['target'],
                    $item['current'],
                    $item['required'],
                    $item['monthly'],
                    $item['priority'],
                    $item['sort'],
                    $item['due'],
                    $item['notes'],
                ]);
            }
        }

        if ($categorySuggestions) {
            $catStmt = $pdo->prepare(
                'INSERT INTO advanced_plan_category_limits (plan_id, category_id, category_label, suggested_limit, average_spent) VALUES (?,?,?,?,?)'
            );
            foreach ($categorySuggestions as $cat) {
                $catStmt->execute([
                    $planId,
                    $cat['category_id'],
                    $cat['label'],
                    $cat['suggested'],
                    $cat['average'],
                ]);
            }
        }

        $pdo->commit();
        $_SESSION['flash'] = $activate
            ? __('Advanced plan activated.')
            : __('Advanced plan saved as draft.');
        redirect('/advanced-planner?plan=' . $planId);
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('Advanced planner save failed: ' . $e->getMessage());
        $_SESSION['flash'] = __('Could not save the advanced plan.');
        redirect('/advanced-planner');
    }
}

function advanced_planner_activate(PDO $pdo): void
{
    verify_csrf();
    require_login();
    $userId = uid();
    $planId = (int)($_POST['plan_id'] ?? 0);
    if ($planId <= 0) {
        redirect('/advanced-planner');
    }

    $pdo->beginTransaction();
    try {
        $planCheck = $pdo->prepare('SELECT id FROM advanced_plans WHERE id = ? AND user_id = ?');
        $planCheck->execute([$planId, $userId]);
        if (!$planCheck->fetch()) {
            $pdo->rollBack();
            $_SESSION['flash'] = __('Plan not found.');
            redirect('/advanced-planner');
        }

        $pdo->prepare('UPDATE advanced_plans SET status = \'archived\', updated_at = NOW() WHERE user_id = ? AND status = \'active\'')
            ->execute([$userId]);
        $pdo->prepare('UPDATE advanced_plans SET status = \'active\', updated_at = NOW() WHERE id = ? AND user_id = ?')
            ->execute([$planId, $userId]);
        $pdo->commit();
        $_SESSION['flash'] = __('Advanced plan is now live.');
        redirect('/advanced-planner?plan=' . $planId);
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('Advanced planner activate failed: ' . $e->getMessage());
        $_SESSION['flash'] = __('Could not activate the plan.');
        redirect('/advanced-planner');
    }
}

function advanced_planner_delete(PDO $pdo): void
{
    verify_csrf();
    require_login();
    $userId = uid();
    $planId = (int)($_POST['plan_id'] ?? 0);
    if ($planId <= 0) {
        redirect('/advanced-planner');
    }

    $stmt = $pdo->prepare('DELETE FROM advanced_plans WHERE id = ? AND user_id = ?');
    $stmt->execute([$planId, $userId]);
    $_SESSION['flash'] = __('Plan deleted.');
    redirect('/advanced-planner');
}

function advanced_planner_collect_incomes(PDO $pdo, int $userId, string $mainCurrency, DateTimeImmutable $startMonth): array
{
    $firstOfMonth = $startMonth->format('Y-m-01');
    $year = (int)$startMonth->format('Y');
    $month = (int)$startMonth->format('n');
    $stmt = $pdo->prepare(
        'SELECT label, amount, currency FROM basic_incomes WHERE user_id = ? AND valid_from <= ?::date AND (valid_to IS NULL OR valid_to >= ?::date)'
    );
    $stmt->execute([$userId, $firstOfMonth, $firstOfMonth]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = 0.0;
    $sources = [];
    foreach ($rows as $row) {
        $amount = (float)($row['amount'] ?? 0);
        $currency = strtoupper($row['currency'] ?: $mainCurrency);
        $converted = fx_convert_basic_income($pdo, $amount, $currency, $mainCurrency, $year, $month);
        $total += $converted;
        $sources[] = [
            'label' => $row['label'] ?: __('Income'),
            'amount' => round($amount, 2),
            'currency' => $currency,
            'converted' => round($converted, 2),
        ];
    }

    return [
        'total' => round($total, 2),
        'sources' => $sources,
        'month' => $firstOfMonth,
    ];
}

function advanced_planner_fetch_spending_categories(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        "SELECT id, label, COALESCE(NULLIF(color,''),'#6B7280') AS color FROM categories WHERE user_id = ? AND kind = 'spending' ORDER BY lower(label)"
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function advanced_planner_average_spending(
    PDO $pdo,
    int $userId,
    string $mainCurrency,
    DateTimeImmutable $startMonth,
    array $categories,
    int $monthsBack
): array {
    $monthsBack = max(1, $monthsBack);
    $windowStart = $startMonth->modify('-' . $monthsBack . ' months');
    $windowStart = $windowStart->setDate((int)$windowStart->format('Y'), (int)$windowStart->format('n'), 1);
    $windowEnd = $startMonth->modify('-1 day');

    if ($windowEnd < $windowStart) {
        $windowEnd = $windowStart;
    }

    $stmt = $pdo->prepare(
        "SELECT t.category_id, t.amount, t.currency, t.occurred_on, t.amount_main, t.main_currency,
                c.label AS cat_label, COALESCE(NULLIF(c.color,''),'#6B7280') AS cat_color
           FROM transactions t
           LEFT JOIN categories c ON c.id = t.category_id AND c.user_id = t.user_id
          WHERE t.user_id = ? AND t.kind = 'spending' AND t.occurred_on BETWEEN ?::date AND ?::date"
    );
    $stmt->execute([$userId, $windowStart->format('Y-m-d'), $windowEnd->format('Y-m-d')]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totals = [];
    foreach ($categories as $cat) {
        $catId = (int)$cat['id'];
        $totals[$catId] = [
            'id' => $catId,
            'label' => $cat['label'],
            'color' => $cat['color'],
            'total' => 0.0,
        ];
    }

    foreach ($rows as $row) {
        $catId = $row['category_id'] !== null ? (int)$row['category_id'] : null;
        if ($catId === null || !isset($totals[$catId])) {
            continue;
        }
        $amount = (float)$row['amount'];
        $currency = strtoupper($row['currency'] ?: $mainCurrency);
        $occurred = $row['occurred_on'];
        $amtMain = null;
        if ($row['amount_main'] !== null && $row['main_currency']) {
            $storedMain = strtoupper((string)$row['main_currency']);
            if ($storedMain === strtoupper($mainCurrency)) {
                $amtMain = (float)$row['amount_main'];
            } else {
                $rate = fx_rate_from_to($pdo, $storedMain, $mainCurrency, $occurred);
                if ($rate !== null) {
                    $amtMain = round((float)$row['amount_main'] * $rate, 2);
                }
            }
        }
        if ($amtMain === null) {
            $rate = fx_rate_from_to($pdo, $currency, $mainCurrency, $occurred);
            $amtMain = $rate !== null ? round($amount * $rate, 2) : $amount;
        }
        $totals[$catId]['total'] += $amtMain;
    }

    $period = new DatePeriod(
        $windowStart,
        new DateInterval('P1M'),
        $startMonth
    );
    $monthsCount = 0;
    foreach ($period as $_) {
        $monthsCount++;
    }
    $monthsCount = max(1, $monthsCount);

    foreach ($totals as &$cat) {
        $cat['total'] = round($cat['total'], 2);
        $cat['average'] = round($cat['total'] / $monthsCount, 2);
    }
    unset($cat);

    return [
        'categories' => array_values($totals),
        'months' => $monthsCount,
        'window_start' => $windowStart->format('Y-m-d'),
        'window_end' => $windowEnd->format('Y-m-d'),
    ];
}

function advanced_planner_normalize_due_month(string $rawDue, DateTimeImmutable $startMonth, int $horizon): ?DateTimeImmutable
{
    if ($rawDue === '') {
        return null;
    }

    $due = DateTimeImmutable::createFromFormat('Y-m', $rawDue);
    if (!$due) {
        return null;
    }

    $due = $due->setDate((int)$due->format('Y'), (int)$due->format('n'), 1);
    $planEndMonth = $startMonth->modify('+' . max(0, $horizon - 1) . ' months');

    if ($due < $startMonth) {
        $due = $startMonth;
    }

    if ($due > $planEndMonth) {
        $due = $planEndMonth;
    }

    return $due;
}

function advanced_planner_months_between(DateTimeImmutable $start, DateTimeImmutable $end): int
{
    if ($end < $start) {
        return 1;
    }

    $diff = $start->diff($end);
    $months = ($diff->y * 12) + $diff->m + 1;

    return max(1, $months);
}

function advanced_planner_collect_resources(PDO $pdo, int $userId, string $mainCurrency): array
{
    $today = (new DateTimeImmutable('today'))->format('Y-m-d');

    $emergencyStmt = $pdo->prepare('SELECT target_amount, total, currency FROM emergency_fund WHERE user_id = ?');
    $emergencyStmt->execute([$userId]);
    $emergency = $emergencyStmt->fetch(PDO::FETCH_ASSOC);
    $emergencyData = null;
    if ($emergency) {
        $target = (float)($emergency['target_amount'] ?? 0);
        $current = (float)($emergency['total'] ?? 0);
        $currency = strtoupper($emergency['currency'] ?: $mainCurrency);
        $remaining = max(0.0, $target - $current);
        $remainingMain = advanced_planner_convert_to_main($pdo, $remaining, $currency, $mainCurrency, $today);
        $emergencyData = [
            'label' => __('Emergency fund'),
            'target' => round($target, 2),
            'current' => round($current, 2),
            'currency' => $currency,
            'remaining' => round($remaining, 2),
            'remaining_main' => round($remainingMain, 2),
        ];
    }

    $goalStmt = $pdo->prepare('SELECT id, title, target_amount, current_amount, currency, status FROM goals WHERE user_id = ? ORDER BY lower(title)');
    $goalStmt->execute([$userId]);
    $goals = [];
    foreach ($goalStmt as $row) {
        $target = (float)($row['target_amount'] ?? 0);
        $current = (float)($row['current_amount'] ?? 0);
        $currency = strtoupper($row['currency'] ?: $mainCurrency);
        $remaining = max(0.0, $target - $current);
        if ($remaining <= 0) {
            continue;
        }
        $remainingMain = advanced_planner_convert_to_main($pdo, $remaining, $currency, $mainCurrency, $today);
        $goals[] = [
            'id' => (int)$row['id'],
            'label' => $row['title'] ?: __('Goal'),
            'currency' => $currency,
            'remaining' => round($remaining, 2),
            'remaining_main' => round($remainingMain, 2),
            'status' => $row['status'],
        ];
    }

    $loanStmt = $pdo->prepare('SELECT id, name, balance, currency FROM loans WHERE user_id = ? ORDER BY lower(name)');
    $loanStmt->execute([$userId]);
    $loans = [];
    foreach ($loanStmt as $row) {
        $balance = (float)($row['balance'] ?? 0);
        if ($balance <= 0) {
            continue;
        }
        $currency = strtoupper($row['currency'] ?: $mainCurrency);
        $balanceMain = advanced_planner_convert_to_main($pdo, $balance, $currency, $mainCurrency, $today);
        $loans[] = [
            'id' => (int)$row['id'],
            'label' => $row['name'] ?: __('Loan'),
            'currency' => $currency,
            'balance' => round($balance, 2),
            'balance_main' => round($balanceMain, 2),
        ];
    }

    return [
        'emergency' => $emergencyData,
        'goals' => $goals,
        'loans' => $loans,
    ];
}

function advanced_planner_convert_to_main(
    PDO $pdo,
    float $amount,
    string $fromCurrency,
    string $mainCurrency,
    string $date
): float {
    $fromCurrency = strtoupper($fromCurrency ?: $mainCurrency);
    $mainCurrency = strtoupper($mainCurrency ?: $fromCurrency);
    if ($fromCurrency === $mainCurrency) {
        return round($amount, 2);
    }
    $rate = fx_rate_from_to($pdo, $fromCurrency, $mainCurrency, $date);
    if ($rate === null) {
        return round($amount, 2);
    }
    return round($amount * $rate, 2);
}
