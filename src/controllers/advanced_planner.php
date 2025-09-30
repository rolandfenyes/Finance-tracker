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

    $previewIncome = (float)($incomeData['total'] ?? 0);
    $reservedFreePreview = advanced_planner_reserved_free_amount($previewIncome);
    $planningCapacityPreview = max(0.0, $previewIncome - $reservedFreePreview);

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
        $previewIncome,
        $planningCapacityPreview,
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
    $planCategoryTotal = 0.0;
    $planFreeAfterLimits = 0.0;
    $planMonthlyBreakdown = [];
    $planReservedFree = 0.0;
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

        foreach ($planCategoryLimits as $limit) {
            $planCategoryTotal += (float)($limit['suggested_limit'] ?? 0);
        }
        $planCategoryTotal = round($planCategoryTotal, 2);
        $planFreeAfterLimits = max(
            0.0,
            round((float)($currentPlan['monthly_discretionary'] ?? 0) - $planCategoryTotal, 2)
        );

        $planReservedFree = advanced_planner_reserved_free_amount((float)($currentPlan['monthly_income'] ?? 0));
        $planMonthlyBreakdown = advanced_planner_plan_monthly_breakdown(
            $currentPlan,
            $planItems,
            $planCategoryLimits,
            $planReservedFree
        );
    }

    view('advanced_planner/index', [
        'plans' => $plans,
        'currentPlan' => $currentPlan,
        'planItems' => $planItems,
        'planCategoryLimits' => $planCategoryLimits,
        'planCategoryTotal' => $planCategoryTotal,
        'planFreeAfterLimits' => $planFreeAfterLimits,
        'planReservedFree' => $planReservedFree,
        'planMonthlyBreakdown' => $planMonthlyBreakdown,
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
        'reservedFreePreview' => $reservedFreePreview,
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

    $reservedFree = advanced_planner_reserved_free_amount($monthlyIncome);
    $schedule = advanced_planner_schedule_milestones($items, $startMonth, $horizon, $monthlyIncome, $reservedFree);
    $items = $schedule['items'];

    $totalCommitments = round($schedule['average_load'] ?? 0.0, 2);
    $monthlyDiscretionary = round(max(0.0, $schedule['min_available'] ?? 0.0), 2);

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

    $categoryIdsInput = $_POST['category_id'] ?? [];
    $categoryLabelsInput = $_POST['category_label'] ?? [];
    $categoryAveragesInput = $_POST['category_average'] ?? [];
    $categoryLockedInput = $_POST['category_locked_min'] ?? [];
    $categorySuggestedInput = $_POST['category_suggested'] ?? [];

    $preparedCategories = [];
    $totalLockedMinimum = 0.0;
    foreach ($categorySuggestions as $idx => $cat) {
        $categoryId = isset($categoryIdsInput[$idx]) && $categoryIdsInput[$idx] !== ''
            ? (int)$categoryIdsInput[$idx]
            : null;
        $label = isset($categoryLabelsInput[$idx])
            ? (string)$categoryLabelsInput[$idx]
            : (string)($cat['label'] ?? '');
        $average = isset($categoryAveragesInput[$idx])
            ? round(max(0.0, (float)$categoryAveragesInput[$idx]), 2)
            : round(max(0.0, (float)($cat['average'] ?? 0)), 2);
        $lockedMin = isset($categoryLockedInput[$idx])
            ? max(0.0, (float)$categoryLockedInput[$idx])
            : max(0.0, (float)($cat['locked_min'] ?? 0));
        $lockedMin = round($lockedMin, 2);
        $suggestedBase = isset($categorySuggestedInput[$idx])
            ? max($lockedMin, max(0.0, (float)$categorySuggestedInput[$idx]))
            : max($lockedMin, (float)($cat['suggested'] ?? 0));

        $preparedCategories[] = [
            'category_id' => $categoryId,
            'label' => $label,
            'average' => $average,
            'locked_min' => $lockedMin,
            'raw_suggested' => $suggestedBase,
        ];
        $totalLockedMinimum += $lockedMin;
    }

    $availableExtras = max(0.0, $monthlyDiscretionary - $totalLockedMinimum);
    foreach ($preparedCategories as &$cat) {
        $lockedMin = (float)$cat['locked_min'];
        $rawSuggested = isset($cat['raw_suggested']) ? (float)$cat['raw_suggested'] : $lockedMin;
        $extra = max(0.0, $rawSuggested - $lockedMin);
        $usableExtra = min($extra, $availableExtras);
        $cat['suggested'] = round($lockedMin + $usableExtra, 2);
        $availableExtras = max(0.0, $availableExtras - $usableExtra);
        unset($cat['raw_suggested']);
    }
    unset($cat);

    $categorySuggestions = $preparedCategories;

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

    $loanCategoryMap = [];

    $scheduledCategoryStmt = $pdo->prepare(
        "SELECT sp.category_id, sp.loan_id, sp.amount, sp.currency, c.label AS cat_label,\n"
        . "       COALESCE(NULLIF(c.color,''),'#6B7280') AS cat_color, c.cashflow_rule_id\n"
        . "  FROM scheduled_payments sp\n"
        . "  LEFT JOIN categories c ON c.id = sp.category_id AND c.user_id = sp.user_id\n"
        . " WHERE sp.user_id = ? AND sp.category_id IS NOT NULL"
    );
    $scheduledCategoryStmt->execute([$userId]);
    foreach ($scheduledCategoryStmt as $row) {
        $catId = (int)$row['category_id'];
        if (!isset($totals[$catId])) {
            $label = $row['cat_label'] ?: __('Scheduled payment');
            $totals[$catId] = [
                'id' => $catId,
                'label' => $label,
                'color' => $row['cat_color'] ?: '#6B7280',
                'cashflow_rule_id' => isset($row['cashflow_rule_id']) && $row['cashflow_rule_id'] !== null
                    ? (int)$row['cashflow_rule_id']
                    : null,
                'total' => 0.0,
            ];
        }
        $amount = max(0.0, (float)($row['amount'] ?? 0));
        if ($amount <= 0) {
            continue;
        }
        if (!empty($row['loan_id'])) {
            $loanCategoryMap[(int)$row['loan_id']] = $catId;
        }
        $currency = strtoupper($row['currency'] ?: $mainCurrency);
        $converted = advanced_planner_convert_to_main(
            $pdo,
            $amount,
            $currency,
            $mainCurrency,
            $windowEnd->format('Y-m-d')
        );
        $totals[$catId]['scheduled_average'] = ($totals[$catId]['scheduled_average'] ?? 0.0) + $converted;
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
        $scheduledAvg = isset($cat['scheduled_average']) ? max(0.0, (float)$cat['scheduled_average']) : 0.0;
        $catTotal = round((float)$cat['total'], 2);
        if ($catTotal <= 0 && $scheduledAvg > 0) {
            $catTotal = round($scheduledAvg * $monthsCount, 2);
            $cat['average'] = round($scheduledAvg, 2);
        } else {
            $average = $monthsCount > 0 ? round($catTotal / $monthsCount, 2) : 0.0;
            if ($scheduledAvg > 0) {
                $average = max($average, round($scheduledAvg, 2));
                $catTotal = round($average * $monthsCount, 2);
            }
            $cat['average'] = $average;
        }
        $cat['total'] = $catTotal;
        if ($scheduledAvg > 0) {
            $cat['scheduled_average'] = round($scheduledAvg, 2);
        } else {
            unset($cat['scheduled_average']);
        }
    }
    unset($cat);

    foreach ($loanTotals as &$loan) {
        $scheduledAverage = isset($loan['scheduled_average']) ? (float)$loan['scheduled_average'] : null;
        if (($loan['total'] ?? 0) <= 0 && $scheduledAverage !== null) {
            $loan['total'] = round($scheduledAverage * $monthsCount, 2);
            $loan['average'] = round($scheduledAverage, 2);
        } else {
            $loan['total'] = round($loan['total'], 2);
            $loan['average'] = round($loan['total'] / $monthsCount, 2);
        }
        if ($scheduledAverage !== null) {
            $loan['scheduled_average'] = round(max(0.0, $scheduledAverage), 2);
        }
    }
    unset($loan);

    foreach ($loanTotals as $loanId => $loan) {
        $loanCategoryId = $loanCategoryMap[$loanId] ?? null;
        if ($loanCategoryId === null || !isset($totals[$loanCategoryId])) {
            continue;
        }

        $cat =& $totals[$loanCategoryId];
        $loanAverage = isset($loan['average']) ? (float)$loan['average'] : 0.0;
        $catAverage = isset($cat['average']) ? (float)$cat['average'] : 0.0;
        $newAverage = max($catAverage, $loanAverage);
        $cat['average'] = round($newAverage, 2);
        $cat['total'] = round(max((float)$cat['total'], $newAverage * $monthsCount), 2);

        if (isset($loan['scheduled_average'])) {
            $cat['scheduled_average'] = round(
                max((float)($cat['scheduled_average'] ?? 0.0), (float)$loan['scheduled_average']),
                2
            );
        }

        unset($loanTotals[$loanId]);
    }

    $loanAggregate = null;
    foreach ($loanTotals as $loan) {
        $total = isset($loan['total']) ? (float)$loan['total'] : 0.0;
        $average = isset($loan['average']) ? (float)$loan['average'] : 0.0;
        $scheduledAverage = isset($loan['scheduled_average']) ? (float)$loan['scheduled_average'] : 0.0;
        if ($total <= 0 && $average <= 0 && $scheduledAverage <= 0) {
            continue;
        }
        if ($loanAggregate === null) {
            $loanAggregate = [
                'id' => null,
                'label' => __('Loan payments'),
                'color' => '#0EA5E9',
                'cashflow_rule_id' => null,
                'total' => 0.0,
                'average' => 0.0,
            ];
        }
        $loanAggregate['total'] += $total;
        $loanAggregate['average'] += $average;
        if ($scheduledAverage > 0) {
            $loanAggregate['scheduled_average'] = ($loanAggregate['scheduled_average'] ?? 0.0) + $scheduledAverage;
        }
    }

    if ($loanAggregate !== null) {
        $loanAggregate['total'] = round($loanAggregate['total'], 2);
        $loanAggregate['average'] = round($loanAggregate['average'], 2);
        if (isset($loanAggregate['scheduled_average'])) {
            $loanAggregate['scheduled_average'] = round($loanAggregate['scheduled_average'], 2);
        }
        $loanTotals = [$loanAggregate];
    } else {
        $loanTotals = [];
    }

    return [
        'categories' => array_merge(array_values($totals), $loanTotals),
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
        $scheduled = max(0.0, (float)($cat['scheduled_average'] ?? 0));
        $rawRuleId = isset($cat['cashflow_rule_id']) && $cat['cashflow_rule_id'] !== null
            ? (int)$cat['cashflow_rule_id']
            : null;
        $ruleId = ($rawRuleId !== null && isset($ruleMap[$rawRuleId]) && $ruleMap[$rawRuleId]['percent'] > 0)
            ? $rawRuleId
            : null;

        $lockedBase = $scheduled > 0 ? $scheduled : 0.0;
        $adjustableBase = max(0.0, $avg - $lockedBase);

        $categoryData[] = [
            'category_id' => $catId,
            'label' => $cat['label'],
            'average' => $avg,
            'rule_id' => $ruleId,
            'scheduled' => $scheduled,
            'locked_base' => $lockedBase,
            'adjustable_base' => $adjustableBase,
        ];
    }

    $lockedAmounts = [];
    $totalLocked = 0.0;
    foreach ($categoryData as $idx => $cat) {
        if ($cat['locked_base'] > 0) {
            $lockedAmount = $cat['locked_base'];
            $lockedAmounts[$idx] = $lockedAmount;
            $totalLocked += $lockedAmount;
        }
    }

    $availableDiscretionary = max(0.0, $monthlyDiscretionary - $totalLocked);

    $ruleGroups = [];
    foreach ($categoryData as $idx => $cat) {
        if ($cat['rule_id'] === null) {
            continue;
        }
        $ruleId = $cat['rule_id'];
        if (!isset($ruleGroups[$ruleId])) {
            $ruleGroups[$ruleId] = [
                'percent' => $ruleMap[$ruleId]['percent'],
                'adjustable_indexes' => [],
                'locked_sum' => 0.0,
            ];
        }
        if (isset($lockedAmounts[$idx])) {
            $ruleGroups[$ruleId]['locked_sum'] += $lockedAmounts[$idx];
        }
        if ($categoryData[$idx]['adjustable_base'] > 0) {
            $ruleGroups[$ruleId]['adjustable_indexes'][] = $idx;
        }
    }

    $totalRuleBudget = 0.0;
    foreach ($ruleGroups as $ruleId => &$group) {
        $baseBudget = ($group['percent'] / 100.0) * $monthlyIncome;
        $group['base_budget'] = max(0.0, round($baseBudget, 2));
        $group['adjustable_budget'] = max(0.0, $group['base_budget'] - $group['locked_sum']);
        $totalRuleBudget += $group['adjustable_budget'];
    }
    unset($group);

    $allocatedToRules = $totalRuleBudget;
    $scale = 1.0;
    if ($totalRuleBudget > 0 && $availableDiscretionary < $totalRuleBudget) {
        $scale = $availableDiscretionary / $totalRuleBudget;
        $allocatedToRules = $availableDiscretionary;
    }

    $adjustableValues = array_fill(0, count($categoryData), 0.0);

    foreach ($ruleGroups as $group) {
        $indexes = $group['adjustable_indexes'];
        if (!$indexes) {
            continue;
        }
        $ruleBudget = $group['adjustable_budget'] * $scale;
        if ($ruleBudget <= 0) {
            continue;
        }
        $totalAverage = 0.0;
        foreach ($indexes as $idx) {
            $totalAverage += $categoryData[$idx]['adjustable_base'];
        }
        foreach ($indexes as $idx) {
            $adjustableBase = $categoryData[$idx]['adjustable_base'];
            if ($totalAverage > 0) {
                $weight = $adjustableBase / $totalAverage;
                $adjustableValues[$idx] = $ruleBudget * $weight;
            } else {
                $adjustableValues[$idx] = $ruleBudget / count($indexes);
            }
        }
    }

    $unruledIndexes = [];
    foreach ($categoryData as $idx => $cat) {
        if ($cat['rule_id'] !== null) {
            continue;
        }
        if ($categoryData[$idx]['adjustable_base'] <= 0) {
            continue;
        }
        $unruledIndexes[] = $idx;
    }

    $leftoverForUnruled = max(0.0, $availableDiscretionary - $allocatedToRules);
    if ($totalRuleBudget <= 0) {
        $leftoverForUnruled = $availableDiscretionary;
    }

    if ($unruledIndexes) {
        $totalAverage = 0.0;
        foreach ($unruledIndexes as $idx) {
            $totalAverage += $categoryData[$idx]['adjustable_base'];
        }
        foreach ($unruledIndexes as $idx) {
            $adjustableBase = $categoryData[$idx]['adjustable_base'];
            if ($leftoverForUnruled <= 0) {
                $adjustableValues[$idx] = 0.0;
                continue;
            }
            if ($totalAverage > 0) {
                $weight = $adjustableBase / $totalAverage;
                $adjustableValues[$idx] = $leftoverForUnruled * $weight;
            } else {
                $adjustableValues[$idx] = $leftoverForUnruled / count($unruledIndexes);
            }
        }
    }

    foreach ($adjustableValues as $idx => &$value) {
        if ($categoryData[$idx]['adjustable_base'] <= 0) {
            continue;
        }
        $value *= $multiplier;
    }
    unset($value);

    $totalAdjustable = 0.0;
    foreach ($adjustableValues as $idx => $value) {
        if ($categoryData[$idx]['adjustable_base'] > 0) {
            $totalAdjustable += $value;
        }
    }
    if ($totalAdjustable > $availableDiscretionary && $availableDiscretionary > 0) {
        $ratio = $availableDiscretionary / $totalAdjustable;
        foreach ($adjustableValues as $idx => &$value) {
            if ($categoryData[$idx]['adjustable_base'] > 0) {
                $value *= $ratio;
            }
        }
        unset($value);
    }

    $result = [];
    foreach ($categoryData as $idx => $cat) {
        $lockedMin = isset($lockedAmounts[$idx]) ? $lockedAmounts[$idx] : 0.0;
        $adjustablePortion = $cat['adjustable_base'] > 0 ? $adjustableValues[$idx] : 0.0;
        $value = $lockedMin + $adjustablePortion;
        $result[] = [
            'category_id' => $cat['category_id'],
            'label' => $cat['label'],
            'average' => round($cat['average'], 2),
            'suggested' => round(max(0.0, $value), 2),
            'scheduled' => round(max(0.0, $cat['scheduled']), 2),
            'locked' => $lockedMin > 0,
            'locked_min' => round(max(0.0, $lockedMin), 2),
        ];
    }

    return $result;
}

function advanced_planner_reserved_free_amount(float $monthlyIncome): float
{
    if ($monthlyIncome <= 0) {
        return 0.0;
    }

    return round(max(0.0, $monthlyIncome * 0.10), 2);
}

function advanced_planner_month_index(
    DateTimeImmutable $startMonth,
    ?DateTimeImmutable $target,
    int $horizon
): int {
    if ($horizon <= 0) {
        return 0;
    }

    if ($target === null) {
        return max(0, $horizon - 1);
    }

    if ($target < $startMonth) {
        return 0;
    }

    $diff = $startMonth->diff($target);
    $index = ($diff->y * 12) + $diff->m;
    $index = max(0, $index);

    return min($index, max(0, $horizon - 1));
}

function advanced_planner_schedule_milestones(
    array $items,
    DateTimeImmutable $startMonth,
    int $horizon,
    float $monthlyIncome,
    float $reservedFree
): array {
    $horizon = max(1, $horizon);
    $capacity = max(0.0, $monthlyIncome - max(0.0, $reservedFree));

    $months = [];
    for ($i = 0; $i < $horizon; $i++) {
        $months[] = [
            'index' => $i,
            'date' => $startMonth->modify('+' . $i . ' months'),
            'load' => 0.0,
        ];
    }

    $entries = [];
    foreach ($items as $idx => $item) {
        $required = isset($item['required'])
            ? (float)$item['required']
            : (float)($item['required_amount'] ?? 0.0);
        $required = max(0.0, $required);

        $dueRaw = $item['due'] ?? $item['target_due_date'] ?? null;
        $hasDue = $dueRaw !== null && $dueRaw !== '';
        $due = null;
        if ($hasDue) {
            $due = DateTimeImmutable::createFromFormat('Y-m-d', (string)$dueRaw)
                ?: DateTimeImmutable::createFromFormat('Y-m', (string)$dueRaw);
            if ($due instanceof DateTimeImmutable) {
                $due = $due->setDate((int)$due->format('Y'), (int)$due->format('n'), 1);
            } else {
                $due = null;
                $hasDue = false;
            }
        }

        $dueIndex = advanced_planner_month_index($startMonth, $due, $horizon);

        $entries[] = [
            'key' => $idx,
            'item' => $item,
            'required' => $required,
            'due_index' => $dueIndex,
            'has_due' => $hasDue,
        ];
    }

    $dueEntries = array_filter($entries, static fn(array $entry): bool => !empty($entry['has_due']));
    $flexEntries = array_filter($entries, static fn(array $entry): bool => empty($entry['has_due']));

    usort($dueEntries, static function (array $a, array $b): int {
        $dueComparison = $a['due_index'] <=> $b['due_index'];
        if ($dueComparison !== 0) {
            return $dueComparison;
        }

        return $b['required'] <=> $a['required'];
    });

    usort($flexEntries, static function (array $a, array $b): int {
        $requiredDiff = $b['required'] <=> $a['required'];
        if ($requiredDiff !== 0) {
            return $requiredDiff;
        }

        return $a['key'] <=> $b['key'];
    });

    $orderedEntries = array_merge($dueEntries, $flexEntries);

    $allocations = [];
    $monthCount = count($months);

    foreach ($orderedEntries as $entry) {
        $key = $entry['key'];
        $required = $entry['required'];
        $dueIndex = min($entry['due_index'], max(0, $monthCount - 1));

        if ($required <= 0) {
            $allocations[$key] = [
                'start' => $dueIndex,
                'end' => $dueIndex,
                'length' => 0,
                'monthly' => 0.0,
                'schedule' => [],
            ];
            continue;
        }

        $bestStart = 0;
        $bestMonthly = $required / max(1, $dueIndex + 1);
        $bestLength = max(1, $dueIndex + 1);
        $bestOverflow = PHP_FLOAT_MAX;

        for ($start = $dueIndex; $start >= 0; $start--) {
            $length = $dueIndex - $start + 1;
            if ($length <= 0) {
                continue;
            }
            $monthlyShare = $required / $length;
            $fits = true;
            $overflow = 0.0;

            for ($i = $start; $i <= $dueIndex; $i++) {
                if (!isset($months[$i])) {
                    continue;
                }
                $projected = $months[$i]['load'] + $monthlyShare;
                if ($projected - $capacity > 1e-6) {
                    $fits = false;
                    $overflow = max($overflow, $projected - $capacity);
                }
            }

            if ($fits) {
                $bestStart = $start;
                $bestMonthly = $monthlyShare;
                $bestLength = $length;
                $bestOverflow = -1.0;
                break;
            }

            if ($overflow < $bestOverflow) {
                $bestOverflow = $overflow;
                $bestStart = $start;
                $bestMonthly = $monthlyShare;
                $bestLength = $length;
            }
        }

        $schedule = [];
        for ($i = $bestStart; $i <= $dueIndex; $i++) {
            if (!isset($months[$i])) {
                continue;
            }
            $months[$i]['load'] += $bestMonthly;
            $schedule[] = [
                'month_index' => $i,
                'amount' => $bestMonthly,
            ];
        }

        $allocations[$key] = [
            'start' => $bestStart,
            'end' => $dueIndex,
            'length' => $bestLength,
            'monthly' => $bestMonthly,
            'schedule' => $schedule,
        ];
    }

    $scheduledItems = [];
    foreach ($entries as $entry) {
        $key = $entry['key'];
        $item = $entry['item'];
        $allocation = $allocations[$key] ?? [
            'start' => $entry['due_index'],
            'end' => $entry['due_index'],
            'length' => 0,
            'monthly' => 0.0,
            'schedule' => [],
        ];

        $item['monthly'] = round($allocation['monthly'], 2);
        $item['start_offset'] = $allocation['start'];
        $item['end_offset'] = $allocation['end'];
        $item['active_months'] = max(0, $allocation['length']);
        $item['monthly_schedule'] = array_map(
            static function (array $slot): array {
                return [
                    'month_index' => $slot['month_index'],
                    'amount' => round($slot['amount'], 2),
                ];
            },
            $allocation['schedule']
        );

        $scheduledItems[] = $item;
    }

    $monthlyTotals = [];
    $minAvailable = $capacity;
    $totalLoad = 0.0;
    $peakLoad = 0.0;

    foreach ($months as &$month) {
        $load = $month['load'];
        $monthlyTotals[] = $load;
        $totalLoad += $load;
        $peakLoad = max($peakLoad, $load);
        $available = max(0.0, $capacity - $load);
        $month['available'] = $available;
        $minAvailable = min($minAvailable, $available);
    }
    unset($month);

    $averageLoad = $monthlyTotals ? ($totalLoad / count($monthlyTotals)) : 0.0;

    $monthPayload = array_map(
        static function (array $month): array {
            /** @var DateTimeImmutable $date */
            $date = $month['date'];
            return [
                'date' => $date->format('Y-m-01'),
                'load' => round($month['load'], 2),
                'available' => round($month['available'], 2),
            ];
        },
        $months
    );

    return [
        'items' => $scheduledItems,
        'months' => $monthPayload,
        'capacity' => round($capacity, 2),
        'reserved_free' => round(max(0.0, $reservedFree), 2),
        'average_load' => $averageLoad,
        'peak_load' => $peakLoad,
        'min_available' => $minAvailable,
    ];
}

function advanced_planner_plan_monthly_breakdown(
    array $plan,
    array $planItems,
    array $planCategoryLimits,
    float $reservedFree
): array {
    $horizon = max(1, (int)($plan['horizon_months'] ?? 1));
    $startRaw = $plan['plan_start'] ?? null;
    $startMonth = DateTimeImmutable::createFromFormat('Y-m-d', (string)$startRaw) ?: new DateTimeImmutable('first day of this month');
    $startMonth = $startMonth->setDate((int)$startMonth->format('Y'), (int)$startMonth->format('n'), 1);
    $monthlyIncome = (float)($plan['monthly_income'] ?? 0);

    $items = [];
    foreach ($planItems as $item) {
        $items[] = [
            'label' => $item['reference_label'] ?? '',
            'required' => (float)($item['required_amount'] ?? 0),
            'due' => $item['target_due_date'] ?? null,
        ];
    }

    $schedule = advanced_planner_schedule_milestones($items, $startMonth, $horizon, $monthlyIncome, $reservedFree);

    $categoryTotal = 0.0;
    foreach ($planCategoryLimits as $limit) {
        $categoryTotal += (float)($limit['suggested_limit'] ?? 0);
    }
    $categoryTotal = round($categoryTotal, 2);

    $capacity = $schedule['capacity'] ?? max(0.0, $monthlyIncome - $reservedFree);
    $reserved = round(max(0.0, $reservedFree), 2);
    $months = [];

    foreach ($schedule['months'] as $month) {
        $dateString = $month['date'];
        $milestones = (float)$month['load'];
        $available = (float)$month['available'];
        $freeCushion = $monthlyIncome - $reserved - $categoryTotal - $milestones;

        $months[] = [
            'date' => $dateString,
            'milestones' => round($milestones, 2),
            'categories' => $categoryTotal,
            'reserved' => $reserved,
            'free' => round($freeCushion, 2),
            'total_planned' => round($milestones + $categoryTotal + $reserved, 2),
            'capacity' => round($capacity, 2),
            'available_for_categories' => round($available, 2),
        ];
    }

    return [
        'months' => $months,
        'category_total' => $categoryTotal,
        'reserved_free' => $reserved,
        'capacity' => round($capacity, 2),
        'monthly_income' => round($monthlyIncome, 2),
        'peak_milestones' => round($schedule['peak_load'] ?? 0.0, 2),
        'average_milestones' => round($schedule['average_load'] ?? 0.0, 2),
    ];
}
