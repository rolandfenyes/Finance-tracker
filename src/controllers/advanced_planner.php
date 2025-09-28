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
    $averageMonthsParam = isset($_GET['avg_months']) ? (int)$_GET['avg_months'] : 3;
    $averageMonths = max(1, min(12, $averageMonthsParam));

    $incomeData = advanced_planner_collect_incomes($pdo, $userId, $mainCurrency, $startMonth);
    $spendingCategories = advanced_planner_fetch_spending_categories($pdo, $userId);
    $averages = advanced_planner_average_spending($pdo, $userId, $mainCurrency, $startMonth, $spendingCategories, $averageMonths);

    $resources = advanced_planner_collect_resources($pdo, $userId, $mainCurrency);
    $difficultyOptions = advanced_planner_difficulty_options();
    $difficultyConfig = [];
    foreach ($difficultyOptions as $key => $option) {
        $difficultyConfig[$key] = [
            'label' => $option['label'],
            'description' => $option['description'],
            'multiplier' => $option['multiplier'],
        ];
    }
    $cashflowRules = advanced_planner_fetch_cashflow_rules($pdo, $userId);
    $defaultDifficulty = 'medium';
    $initialSuggestions = advanced_planner_calculate_category_suggestions(
        $pdo,
        $userId,
        (float)($incomeData['total'] ?? 0),
        (float)($incomeData['total'] ?? 0),
        $averages,
        $defaultDifficulty,
        $cashflowRules
    );

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
        'startParam' => $startParam,
        'defaultHorizon' => $defaultHorizon,
        'averageMonths' => $averageMonths,
        'averageMonthsOptions' => range(1, 12),
        'selectedPlanId' => $requestedPlanId,
        'incomeData' => $incomeData,
        'spendingCategories' => $spendingCategories,
        'averages' => $averages,
        'resources' => $resources,
        'difficultyOptions' => $difficultyOptions,
        'difficultyConfig' => $difficultyConfig,
        'cashflowRules' => $cashflowRules,
        'defaultDifficulty' => $defaultDifficulty,
        'initialCategorySuggestions' => $initialSuggestions,
    ]);
}

function advanced_planner_store(PDO $pdo): void
{
    verify_csrf();
    require_login();
    $userId = uid();
    $mainCurrency = fx_user_main($pdo, $userId) ?: 'HUF';

    $difficultyOptions = advanced_planner_difficulty_options();
    $difficulty = strtolower(trim($_POST['difficulty_level'] ?? ''));
    if (!array_key_exists($difficulty, $difficultyOptions)) {
        $difficulty = 'medium';
    }

    $title = trim($_POST['title'] ?? '');
    if ($title === '') {
        $title = __('Advanced plan');
    }

    $horizon = (int)($_POST['horizon_months'] ?? 3);
    if (!in_array($horizon, [3, 6, 12], true)) {
        $horizon = 3;
    }

    $avgMonths = isset($_POST['avg_months']) ? (int)$_POST['avg_months'] : 3;
    $avgMonths = max(1, min(12, $avgMonths));

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
    $averages = advanced_planner_average_spending($pdo, $userId, $mainCurrency, $startMonth, $spendingCategories, $avgMonths);

    $totalCommitments = 0.0;
    foreach ($items as $item) {
        $totalCommitments += $item['monthly'];
    }
    $totalCommitments = round($totalCommitments, 2);

    $monthlyDiscretionary = max(0.0, $monthlyIncome - $totalCommitments);

    $cashflowRules = advanced_planner_fetch_cashflow_rules($pdo, $userId);
    $categorySuggestions = advanced_planner_calculate_category_suggestions(
        $pdo,
        $userId,
        $monthlyIncome,
        $monthlyDiscretionary,
        $averages,
        $difficulty,
        $cashflowRules
    );

    $status = $activate ? 'active' : 'draft';

    $pdo->beginTransaction();
    try {
        if ($activate) {
            $pdo->prepare('UPDATE advanced_plans SET status = \'archived\', updated_at = NOW() WHERE user_id = ? AND status = \'active\'')
                ->execute([$userId]);
        }

        $planStmt = $pdo->prepare(
            'INSERT INTO advanced_plans (user_id, title, horizon_months, plan_start, plan_end, main_currency, total_budget, monthly_income, monthly_commitments, monthly_discretionary, difficulty_level, status, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?) RETURNING id'
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
            $difficulty,
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
        $query = '/advanced-planner?plan=' . $planId;
        if ($avgMonths) {
            $query .= '&avg_months=' . $avgMonths;
        }
        redirect($query);
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('Advanced planner save failed: ' . $e->getMessage());
        $_SESSION['flash'] = __('Could not save the advanced plan.');
        redirect('/advanced-planner' . ($avgMonths ? ('?avg_months=' . $avgMonths) : ''));
    }
}

function advanced_planner_activate(PDO $pdo): void
{
    verify_csrf();
    require_login();
    $userId = uid();
    $planId = (int)($_POST['plan_id'] ?? 0);
    $avgMonths = isset($_POST['avg_months']) ? (int)$_POST['avg_months'] : 0;
    $avgMonths = $avgMonths > 0 ? max(1, min(12, $avgMonths)) : 0;
    if ($planId <= 0) {
        redirect('/advanced-planner' . ($avgMonths ? ('?avg_months=' . $avgMonths) : ''));
    }

    $pdo->beginTransaction();
    try {
        $planCheck = $pdo->prepare('SELECT id FROM advanced_plans WHERE id = ? AND user_id = ?');
        $planCheck->execute([$planId, $userId]);
        if (!$planCheck->fetch()) {
            $pdo->rollBack();
            $_SESSION['flash'] = __('Plan not found.');
            $fallback = '/advanced-planner' . ($avgMonths ? ('?avg_months=' . $avgMonths) : '');
            redirect($fallback);
        }

        $pdo->prepare('UPDATE advanced_plans SET status = \'archived\', updated_at = NOW() WHERE user_id = ? AND status = \'active\'')
            ->execute([$userId]);
        $pdo->prepare('UPDATE advanced_plans SET status = \'active\', updated_at = NOW() WHERE id = ? AND user_id = ?')
            ->execute([$planId, $userId]);
        $pdo->commit();
        $_SESSION['flash'] = __('Advanced plan is now live.');
        $query = '/advanced-planner?plan=' . $planId;
        if ($avgMonths) {
            $query .= '&avg_months=' . $avgMonths;
        }
        redirect($query);
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('Advanced planner activate failed: ' . $e->getMessage());
        $_SESSION['flash'] = __('Could not activate the plan.');
        redirect('/advanced-planner' . ($avgMonths ? ('?avg_months=' . $avgMonths) : ''));
    }
}

function advanced_planner_delete(PDO $pdo): void
{
    verify_csrf();
    require_login();
    $userId = uid();
    $planId = (int)($_POST['plan_id'] ?? 0);
    $avgMonths = isset($_POST['avg_months']) ? (int)$_POST['avg_months'] : 0;
    $avgMonths = $avgMonths > 0 ? max(1, min(12, $avgMonths)) : 0;
    if ($planId <= 0) {
        redirect('/advanced-planner' . ($avgMonths ? ('?avg_months=' . $avgMonths) : ''));
    }

    $stmt = $pdo->prepare('DELETE FROM advanced_plans WHERE id = ? AND user_id = ?');
    $stmt->execute([$planId, $userId]);
    $_SESSION['flash'] = __('Plan deleted.');
    redirect('/advanced-planner' . ($avgMonths ? ('?avg_months=' . $avgMonths) : ''));
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
        "SELECT id, label, COALESCE(NULLIF(color,''),'#6B7280') AS color, cashflow_rule_id FROM categories WHERE user_id = ? AND kind = 'spending' ORDER BY lower(label)"
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
            'cashflow_rule_id' => isset($cat['cashflow_rule_id']) && $cat['cashflow_rule_id'] !== null
                ? (int)$cat['cashflow_rule_id']
                : null,
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

    $loanTotals = [];
    $loanStmt = $pdo->prepare(
        "SELECT lp.loan_id, lp.paid_on, lp.amount, lp.currency, l.name AS loan_name, l.currency AS loan_currency
           FROM loan_payments lp
           JOIN loans l ON l.id = lp.loan_id
          WHERE l.user_id = ? AND lp.paid_on BETWEEN ?::date AND ?::date"
    );
    $loanStmt->execute([$userId, $windowStart->format('Y-m-d'), $windowEnd->format('Y-m-d')]);
    foreach ($loanStmt as $row) {
        $loanId = (int)$row['loan_id'];
        if (!isset($loanTotals[$loanId])) {
            $label = $row['loan_name'] ? __('Loan payment: :name', ['name' => $row['loan_name']]) : __('Loan payment');
            $loanTotals[$loanId] = [
                'id' => null,
                'label' => $label,
                'color' => '#0EA5E9',
                'cashflow_rule_id' => null,
                'total' => 0.0,
            ];
        }
        $currency = strtoupper($row['currency'] ?: ($row['loan_currency'] ?: $mainCurrency));
        $amount = (float)($row['amount'] ?? 0);
        if ($amount === 0.0) {
            continue;
        }
        $paidOn = $row['paid_on'];
        $converted = advanced_planner_convert_to_main($pdo, $amount, $currency, $mainCurrency, $paidOn);
        $loanTotals[$loanId]['total'] += $converted;
    }

    $scheduledLoanStmt = $pdo->prepare(
        "SELECT sp.loan_id, sp.amount, sp.currency, l.name AS loan_name, l.currency AS loan_currency\n"
        . "  FROM scheduled_payments sp\n"
        . "  JOIN loans l ON l.id = sp.loan_id\n"
        . " WHERE sp.user_id = ? AND sp.loan_id IS NOT NULL"
    );
    $scheduledLoanStmt->execute([$userId]);
    foreach ($scheduledLoanStmt as $row) {
        $loanId = (int)$row['loan_id'];
        $scheduledAmount = max(0.0, (float)($row['amount'] ?? 0));
        if ($scheduledAmount <= 0) {
            continue;
        }
        if (!isset($loanTotals[$loanId])) {
            $label = $row['loan_name'] ? __('Loan payment: :name', ['name' => $row['loan_name']]) : __('Loan payment');
            $loanTotals[$loanId] = [
                'id' => null,
                'label' => $label,
                'color' => '#0EA5E9',
                'cashflow_rule_id' => null,
                'total' => 0.0,
            ];
        }
        if (($loanTotals[$loanId]['total'] ?? 0) > 0) {
            continue;
        }
        $currency = strtoupper($row['currency'] ?: ($row['loan_currency'] ?: $mainCurrency));
        $converted = advanced_planner_convert_to_main(
            $pdo,
            $scheduledAmount,
            $currency,
            $mainCurrency,
            $windowEnd->format('Y-m-d')
        );
        $loanTotals[$loanId]['scheduled_average'] = max(0.0, $converted);
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

    foreach ($loanTotals as &$loan) {
        $scheduledAverage = isset($loan['scheduled_average']) ? (float)$loan['scheduled_average'] : null;
        unset($loan['scheduled_average']);
        if (($loan['total'] ?? 0) <= 0 && $scheduledAverage !== null) {
            $loan['total'] = round($scheduledAverage * $monthsCount, 2);
            $loan['average'] = round($scheduledAverage, 2);
        } else {
            $loan['total'] = round($loan['total'], 2);
            $loan['average'] = round($loan['total'] / $monthsCount, 2);
        }
    }
    unset($loan);

    return [
        'categories' => array_merge(array_values($totals), array_values($loanTotals)),
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

function advanced_planner_fetch_cashflow_rules(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, label, percent FROM cashflow_rules WHERE user_id = ? ORDER BY id'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function advanced_planner_difficulty_options(): array
{
    return [
        'easy' => [
            'label' => __('Easy'),
            'description' => __('More relaxed limits (around +10% above rule targets).'),
            'multiplier' => 1.1,
        ],
        'medium' => [
            'label' => __('Medium'),
            'description' => __('Balanced limits that follow your Cashflow Rules.'),
            'multiplier' => 1.0,
        ],
        'hard' => [
            'label' => __('Hard'),
            'description' => __('Tighter limits (around −10% below rule targets).'),
            'multiplier' => 0.9,
        ],
    ];
}

function advanced_planner_calculate_category_suggestions(
    PDO $pdo,
    int $userId,
    float $monthlyIncome,
    float $monthlyDiscretionary,
    array $averages,
    string $difficulty,
    ?array $cashflowRules = null
): array {
    $monthlyIncome = max(0.0, $monthlyIncome);
    $monthlyDiscretionary = max(0.0, $monthlyDiscretionary);
    $difficultyOptions = advanced_planner_difficulty_options();
    $multiplier = $difficultyOptions[$difficulty]['multiplier'] ?? 1.0;

    $categories = $averages['categories'] ?? [];
    if (!$categories) {
        return [];
    }

    if ($cashflowRules === null) {
        $cashflowRules = advanced_planner_fetch_cashflow_rules($pdo, $userId);
    }

    $ruleMap = [];
    foreach ($cashflowRules as $rule) {
        $ruleId = (int)$rule['id'];
        $ruleMap[$ruleId] = [
            'label' => (string)$rule['label'],
            'percent' => max(0.0, (float)($rule['percent'] ?? 0)),
        ];
    }

    $categoryData = [];
    foreach ($categories as $cat) {
        $catId = $cat['id'] !== null ? (int)$cat['id'] : null;
        $avg = max(0.0, (float)($cat['average'] ?? 0));
        $rawRuleId = isset($cat['cashflow_rule_id']) && $cat['cashflow_rule_id'] !== null
            ? (int)$cat['cashflow_rule_id']
            : null;
        $ruleId = ($rawRuleId !== null && isset($ruleMap[$rawRuleId]) && $ruleMap[$rawRuleId]['percent'] > 0)
            ? $rawRuleId
            : null;

        $categoryData[] = [
            'category_id' => $catId,
            'label' => $cat['label'],
            'average' => $avg,
            'rule_id' => $ruleId,
        ];
    }

    $ruleGroups = [];
    foreach ($categoryData as $idx => $cat) {
        if ($cat['rule_id'] === null) {
            continue;
        }
        $ruleId = $cat['rule_id'];
        if (!isset($ruleGroups[$ruleId])) {
            $ruleGroups[$ruleId] = [
                'percent' => $ruleMap[$ruleId]['percent'],
                'indexes' => [],
            ];
        }
        $ruleGroups[$ruleId]['indexes'][] = $idx;
    }

    $totalRuleBudget = 0.0;
    foreach ($ruleGroups as $ruleId => &$group) {
        $baseBudget = ($group['percent'] / 100.0) * $monthlyIncome;
        $group['base_budget'] = max(0.0, round($baseBudget, 2));
        $totalRuleBudget += $group['base_budget'];
    }
    unset($group);

    $allocatedToRules = $totalRuleBudget;
    $scale = 1.0;
    if ($totalRuleBudget > 0 && $monthlyDiscretionary < $totalRuleBudget) {
        $scale = $monthlyDiscretionary / $totalRuleBudget;
        $allocatedToRules = $monthlyDiscretionary;
    }

    $suggestions = [];
    foreach ($ruleGroups as $group) {
        $indexes = $group['indexes'];
        if (!$indexes) {
            continue;
        }
        $ruleBudget = $group['base_budget'] * $scale;
        $totalAverage = 0.0;
        foreach ($indexes as $idx) {
            $totalAverage += $categoryData[$idx]['average'];
        }
        foreach ($indexes as $idx) {
            if ($ruleBudget <= 0) {
                $suggestions[$idx] = 0.0;
                continue;
            }
            if ($totalAverage > 0) {
                $weight = $categoryData[$idx]['average'] / $totalAverage;
                $suggestions[$idx] = $ruleBudget * $weight;
            } else {
                $suggestions[$idx] = $ruleBudget / count($indexes);
            }
        }
    }

    $unruledIndexes = [];
    foreach ($categoryData as $idx => $cat) {
        if ($cat['rule_id'] === null) {
            $unruledIndexes[] = $idx;
        }
        if (!isset($suggestions[$idx])) {
            $suggestions[$idx] = 0.0;
        }
    }

    $leftoverForUnruled = max(0.0, $monthlyDiscretionary - $allocatedToRules);
    if ($totalRuleBudget <= 0) {
        $leftoverForUnruled = $monthlyDiscretionary;
    }

    if ($unruledIndexes) {
        $totalAverage = 0.0;
        foreach ($unruledIndexes as $idx) {
            $totalAverage += $categoryData[$idx]['average'];
        }
        foreach ($unruledIndexes as $idx) {
            if ($leftoverForUnruled <= 0) {
                $suggestions[$idx] = 0.0;
                continue;
            }
            if ($totalAverage > 0) {
                $weight = $categoryData[$idx]['average'] / $totalAverage;
                $suggestions[$idx] = $leftoverForUnruled * $weight;
            } else {
                $suggestions[$idx] = $leftoverForUnruled / count($unruledIndexes);
            }
        }
    }

    foreach ($suggestions as &$value) {
        $value = $value * $multiplier;
    }
    unset($value);

    $totalSuggested = array_sum($suggestions);
    if ($totalSuggested > $monthlyDiscretionary && $monthlyDiscretionary > 0) {
        $ratio = $monthlyDiscretionary / $totalSuggested;
        foreach ($suggestions as &$value) {
            $value *= $ratio;
        }
        unset($value);
    }

    $result = [];
    foreach ($categoryData as $idx => $cat) {
        $result[] = [
            'category_id' => $cat['category_id'],
            'label' => $cat['label'],
            'average' => round($cat['average'], 2),
            'suggested' => round(max(0.0, $suggestions[$idx] ?? 0.0), 2),
        ];
    }

    return $result;
}
