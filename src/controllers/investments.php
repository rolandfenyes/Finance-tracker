<?php

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../fx.php';
require_once __DIR__ . '/../recurrence.php';

function investments_allowed_frequencies(): array
{
    return ['daily', 'weekly', 'monthly', 'annual'];
}

function investments_frequency_periods(string $frequency): int
{
    return match ($frequency) {
        'daily' => 365,
        'weekly' => 52,
        'monthly' => 12,
        'annual' => 1,
        default => 12,
    };
}

function investments_parse_datetime(?string $value): DateTimeImmutable
{
    if ($value) {
        try {
            return new DateTimeImmutable($value);
        } catch (Throwable $e) {
            // ignore invalid date strings
        }
    }

    return new DateTimeImmutable('now');
}

function investments_accrued_interest(
    float $balance,
    DateTimeImmutable $start,
    DateTimeImmutable $end,
    int $periodsPerYear,
    float $rateDecimal
): float {
    if ($balance <= 0 || $rateDecimal <= 0 || $periodsPerYear <= 0) {
        return 0.0;
    }

    $seconds = max(0, $end->getTimestamp() - $start->getTimestamp());
    if ($seconds <= 0) {
        return 0.0;
    }

    $years = $seconds / 31557600; // approx seconds in a year (365.25 days)
    if ($years <= 0) {
        return 0.0;
    }

    $totalPeriods = $years * $periodsPerYear;
    if ($totalPeriods <= 0) {
        return 0.0;
    }

    $periodRate = $rateDecimal / $periodsPerYear;
    if ($periodRate <= -1) {
        return 0.0;
    }

    return $balance * (pow(1 + $periodRate, $totalPeriods) - 1);
}

function investments_performance_snapshot(array $investment, array $transactions, ?array $schedule = null): array
{
    $balance = (float)($investment['balance'] ?? 0);
    $rateRaw = (float)($investment['interest_rate'] ?? 0);
    $frequency = strtolower((string)($investment['interest_frequency'] ?? ''));
    if (!in_array($frequency, investments_allowed_frequencies(), true)) {
        $frequency = 'monthly';
    }

    $periodsPerYear = investments_frequency_periods($frequency);
    $rateDecimal = $rateRaw > 0 ? $rateRaw / 100 : 0.0;
    $createdAt = investments_parse_datetime($investment['created_at'] ?? null);
    $now = new DateTimeImmutable('now');

    $interestEarned = 0.0;
    $runningBalance = 0.0;
    $previousPoint = $createdAt;

    if ($rateDecimal > 0 && $periodsPerYear > 0) {
        foreach ($transactions as $tx) {
            $txTime = investments_parse_datetime($tx['created_at'] ?? null);
            $interestEarned += investments_accrued_interest(
                $runningBalance,
                $previousPoint,
                $txTime,
                $periodsPerYear,
                $rateDecimal
            );
            $runningBalance += (float)($tx['amount'] ?? 0);
            $previousPoint = $txTime;
        }

        $interestEarned += investments_accrued_interest(
            $runningBalance,
            $previousPoint,
            $now,
            $periodsPerYear,
            $rateDecimal
        );
    }

    $interestEarned = max(0.0, $interestEarned);

    $result = [
        'estimated_interest' => $interestEarned,
        'chart_labels' => [],
        'chart_values' => [],
        'has_rate' => $rateDecimal > 0 && $periodsPerYear > 0,
        'frequency' => $frequency,
        'milestones' => [],
    ];

    if (!$result['has_rate']) {
        return $result;
    }

    $scheduleAmount = null;
    $scheduleCurrency = null;
    $scheduleNextDue = null;
    $scheduleRrule = '';
    $investmentCurrency = strtoupper((string)($investment['currency'] ?? ''));

    if (is_array($schedule)) {
        $scheduleAmount = isset($schedule['amount']) ? (float)$schedule['amount'] : null;
        $scheduleCurrency = strtoupper((string)($schedule['currency'] ?? ''));
        $scheduleNextDue = $schedule['next_due'] ?? null;
        $scheduleRrule = trim((string)($schedule['rrule'] ?? ''));

        if ($scheduleAmount !== null && $scheduleAmount <= 0) {
            $scheduleAmount = null;
        }

        if ($scheduleAmount !== null && $investmentCurrency !== '' && $scheduleCurrency !== '' && $scheduleCurrency !== $investmentCurrency) {
            // Skip contribution projection if currencies differ to avoid unreliable FX forecasting.
            $scheduleAmount = null;
        }
    }

    if ($scheduleAmount !== null) {
        $scheduleHasEnd = false;
        $horizon = null;
        $scheduleStart = null;

        if ($scheduleNextDue) {
            try {
                $scheduleStart = new DateTimeImmutable($scheduleNextDue);
            } catch (Throwable $e) {
                $scheduleStart = null;
            }
        }

        if ($scheduleStart && $scheduleRrule === '') {
            $scheduleHasEnd = true;
            $horizon = $scheduleStart;
        } elseif ($scheduleStart && $scheduleRrule !== '') {
            $parsed = rrule_parse($scheduleRrule);
            $until = isset($parsed['UNTIL']) ? rrule_until_to_date($parsed['UNTIL']) : null;
            if ($until) {
                try {
                    $horizon = new DateTimeImmutable($until);
                    $scheduleHasEnd = true;
                } catch (Throwable $e) {
                    $horizon = null;
                }
            }

            if (!$horizon && !empty($parsed['COUNT'])) {
                $rangeStart = $scheduleStart->format('Y-m-d');
                $rangeEnd = $scheduleStart->modify('+100 years')->format('Y-m-d');
                $occ = rrule_expand($rangeStart, $scheduleRrule, $rangeStart, $rangeEnd);
                if ($occ) {
                    $last = end($occ);
                    try {
                        $horizon = new DateTimeImmutable($last);
                        $scheduleHasEnd = true;
                    } catch (Throwable $e) {
                        $horizon = null;
                    }
                }
            }
        }

        if (!$horizon || $horizon < $now) {
            if ($scheduleHasEnd) {
                $horizon = $now;
            } else {
                $horizon = $now->modify('+10 years');
            }
        }

        $chartDates = [$now];
        if ($horizon > $now) {
            $diffSeconds = max(0, $horizon->getTimestamp() - $now->getTimestamp());
            $approxMonths = $diffSeconds > 0 ? (int)ceil($diffSeconds / 2629800) : 0; // approx seconds in month
            $approxMonths = max($approxMonths, 1);
            $approxMonths = min($approxMonths, 240);
            for ($i = 1; $i <= $approxMonths; $i++) {
                $candidate = $now->modify('+' . $i . ' month');
                if ($candidate >= $horizon) {
                    break;
                }
                $chartDates[] = $candidate;
            }
            if (end($chartDates) < $horizon) {
                $chartDates[] = $horizon;
            }
        }

        $occurrences = [];
        if ($scheduleStart) {
            if ($scheduleRrule === '') {
                if ($scheduleStart >= $now && $scheduleStart <= $horizon) {
                    $occurrences[] = $scheduleStart;
                }
            } else {
                $rangeStart = $now->format('Y-m-d');
                $rangeEnd = $horizon->format('Y-m-d');
                $dates = rrule_expand($scheduleStart->format('Y-m-d'), $scheduleRrule, $rangeStart, $rangeEnd);
                foreach ($dates as $dateStr) {
                    try {
                        $d = new DateTimeImmutable($dateStr);
                        if ($d >= $now && $d <= $horizon) {
                            $occurrences[] = $d;
                        }
                    } catch (Throwable $e) {
                        // ignore invalid dates
                    }
                }
            }
        }
        usort($occurrences, static fn(DateTimeImmutable $a, DateTimeImmutable $b) => $a <=> $b);

        $chartLabels = [];
        $chartValues = [];
        $currentValue = max(0.0, $balance);
        $lastPoint = $now;
        $futureInterest = 0.0;
        $futureContrib = 0.0;
        $occIndex = 0;
        $occCount = count($occurrences);

        foreach ($chartDates as $datePoint) {
            while ($occIndex < $occCount && $occurrences[$occIndex] <= $datePoint) {
                $occDate = $occurrences[$occIndex];
                $interestDelta = investments_accrued_interest(
                    $currentValue,
                    $lastPoint,
                    $occDate,
                    $periodsPerYear,
                    $rateDecimal
                );
                if ($interestDelta > 0) {
                    $currentValue += $interestDelta;
                    $futureInterest += $interestDelta;
                }
                $currentValue += $scheduleAmount;
                $futureContrib += $scheduleAmount;
                $lastPoint = $occDate;
                $occIndex++;
            }

            if ($datePoint > $lastPoint) {
                $interestDelta = investments_accrued_interest(
                    $currentValue,
                    $lastPoint,
                    $datePoint,
                    $periodsPerYear,
                    $rateDecimal
                );
                if ($interestDelta > 0) {
                    $currentValue += $interestDelta;
                    $futureInterest += $interestDelta;
                }
                $lastPoint = $datePoint;
            }

            $chartLabels[] = $datePoint->format('M Y');
            $chartValues[] = round($currentValue, 2);
        }

        $targetValue = $currentValue;
        $targetGain = max(0.0, $futureInterest);

        $label = $scheduleHasEnd
            ? __('Projected at schedule completion (:date)', ['date' => $horizon->format('Y-m-d')])
            : __('Projected in 10 years');

        $result['chart_labels'] = $chartLabels;
        $result['chart_values'] = $chartValues;
        $result['milestones'] = [[
            'label' => $label,
            'value' => $targetValue,
            'gain' => $targetGain,
            'contribution_total' => $futureContrib,
        ]];

        return $result;
    }

    // No linked schedule with contributions — fall back to simple compounding milestones
    $chartLabels = [];
    $chartValues = [];
    $chartDate = $now;
    $projectionMonths = 12;

    for ($month = 0; $month <= $projectionMonths; $month++) {
        $chartLabels[] = $chartDate->format('M Y');

        $yearsAhead = max(0.0, ($chartDate->getTimestamp() - $now->getTimestamp()) / 31557600);
        $periodsAhead = $yearsAhead * $periodsPerYear;
        $value = max(0.0, $balance);
        if ($rateDecimal > 0 && $periodsAhead > 0) {
            $value *= pow(1 + $rateDecimal / $periodsPerYear, $periodsAhead);
        }
        $chartValues[] = round($value, 2);

        $chartDate = $chartDate->modify('+1 month');
    }

    $oneYearValue = max(0.0, $balance);
    if ($rateDecimal > 0 && $periodsPerYear > 0) {
        $oneYearValue *= pow(1 + $rateDecimal / $periodsPerYear, $periodsPerYear);
    }

    $fiveYearValue = max(0.0, $balance);
    if ($rateDecimal > 0 && $periodsPerYear > 0) {
        $fiveYearValue *= pow(1 + $rateDecimal / $periodsPerYear, $periodsPerYear * 5);
    }

    $result['chart_labels'] = $chartLabels;
    $result['chart_values'] = $chartValues;
    $result['milestones'] = [
        [
            'label' => __('Projected in 12 months'),
            'value' => $oneYearValue,
            'gain' => max(0.0, $oneYearValue - max(0.0, $balance)),
            'contribution_total' => 0.0,
        ],
        [
            'label' => __('Projected in 5 years'),
            'value' => $fiveYearValue,
            'gain' => max(0.0, $fiveYearValue - max(0.0, $balance)),
            'contribution_total' => 0.0,
        ],
    ];

    return $result;
}

function investments_schedule_summary(?string $rrule): ?string
{
    $rrule = trim((string)$rrule);
    if ($rrule === '') {
        return null;
    }

    $parts = [];
    foreach (explode(';', strtoupper($rrule)) as $piece) {
        $piece = trim($piece);
        if ($piece === '') {
            continue;
        }
        $segments = explode('=', $piece, 2);
        $key = strtoupper(trim($segments[0] ?? ''));
        $value = trim($segments[1] ?? '');
        if ($key !== '') {
            $parts[$key] = $value;
        }
    }

    if (empty($parts['FREQ'])) {
        return null;
    }

    $freqLabels = [
        'DAILY' => __('Daily'),
        'WEEKLY' => __('Weekly'),
        'MONTHLY' => __('Monthly'),
        'YEARLY' => __('Annual'),
    ];

    $freq = strtoupper($parts['FREQ']);
    $interval = isset($parts['INTERVAL']) ? max(1, (int)$parts['INTERVAL']) : 1;
    $label = $freqLabels[$freq] ?? ucfirst(strtolower($freq));

    if ($interval > 1) {
        return __('Every :count :period', [
            'count' => $interval,
            'period' => strtolower($label),
        ]);
    }

    return $label;
}

function investments_index(PDO $pdo): void
{
    require_login();
    $userId = uid();

    $stmt = $pdo->prepare("SELECT i.*, sp.id AS sched_id, sp.title AS sched_title, sp.amount AS sched_amount, sp.currency AS sched_currency, sp.next_due AS sched_next_due, sp.rrule AS sched_rrule, sp.category_id AS sched_category_id
        FROM investments i
        LEFT JOIN scheduled_payments sp ON sp.investment_id = i.id AND sp.user_id = i.user_id
        WHERE i.user_id = ?
        ORDER BY i.created_at DESC, lower(i.name)");
    $stmt->execute([$userId]);
    $investments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $investmentIds = array_map(static fn ($row) => (int)($row['id'] ?? 0), $investments);
    $transactionsByInvestment = [];
    if ($investmentIds) {
        $placeholders = implode(',', array_fill(0, count($investmentIds), '?'));
        $txStmt = $pdo->prepare(
            "SELECT investment_id, amount, note, created_at
            FROM investment_transactions
            WHERE investment_id IN ($placeholders) AND user_id = ?
            ORDER BY created_at ASC"
        );
        $txStmt->execute(array_merge($investmentIds, [$userId]));
        while ($row = $txStmt->fetch(PDO::FETCH_ASSOC)) {
            $invId = (int)($row['investment_id'] ?? 0);
            if ($invId) {
                $transactionsByInvestment[$invId][] = $row;
            }
        }
    }

    $performanceByInvestment = [];
    foreach ($investments as $index => $investment) {
        $investmentId = (int)($investment['id'] ?? 0);
        $txList = $transactionsByInvestment[$investmentId] ?? [];
        $schedulePayload = [
            'amount' => $investment['sched_amount'] ?? null,
            'currency' => $investment['sched_currency'] ?? null,
            'next_due' => $investment['sched_next_due'] ?? null,
            'rrule' => $investment['sched_rrule'] ?? null,
        ];
        $performanceByInvestment[$investmentId] = investments_performance_snapshot($investment, $txList, $schedulePayload);
        $transactionsByInvestment[$investmentId] = array_reverse($txList);
        $investments[$index]['interest_frequency'] = $performanceByInvestment[$investmentId]['frequency'] ?? ($investment['interest_frequency'] ?? 'monthly');
        $investments[$index]['sched_summary'] = investments_schedule_summary($investment['sched_rrule'] ?? null);
    }

    $scheduleStmt = $pdo->prepare("SELECT id, title, amount, currency, next_due
        FROM scheduled_payments
        WHERE user_id = ?
          AND loan_id IS NULL
          AND goal_id IS NULL
          AND investment_id IS NULL
        ORDER BY lower(title)");
    $scheduleStmt->execute([$userId]);
    $availableSchedules = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $categoryStmt = $pdo->prepare("SELECT id, label FROM categories WHERE user_id = ? ORDER BY lower(label)");
    $categoryStmt->execute([$userId]);
    $categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $uc = $pdo->prepare('SELECT code, is_main FROM user_currencies WHERE user_id=? ORDER BY is_main DESC, code');
    $uc->execute([$userId]);
    $userCurrencies = $uc->fetchAll(PDO::FETCH_ASSOC);
    $mainCurrency = fx_user_main($pdo, $userId);
    if ($mainCurrency !== '') {
        $mainCurrency = strtoupper($mainCurrency);
    }
    if (!$userCurrencies) {
        $fallback = $mainCurrency !== '' ? strtoupper($mainCurrency) : 'HUF';
        $userCurrencies = [['code' => $fallback, 'is_main' => true]];
    }
    if ($mainCurrency === '') {
        $mainCurrency = strtoupper($userCurrencies[0]['code'] ?? 'HUF');
    }

    $emergencyStmt = $pdo->prepare('SELECT investment_id FROM emergency_fund WHERE user_id=?');
    $emergencyStmt->execute([$userId]);
    $emergencyInvestmentId = (int)($emergencyStmt->fetchColumn() ?: 0);

    view('investments/index', [
        'investments' => $investments,
        'availableSchedules' => $availableSchedules,
        'transactionsByInvestment' => $transactionsByInvestment,
        'userCurrencies' => $userCurrencies,
        'mainCurrency' => $mainCurrency,
        'performanceByInvestment' => $performanceByInvestment,
        'categories' => $categories,
        'emergencyInvestmentId' => $emergencyInvestmentId,
    ]);
}

function investments_add(PDO $pdo): void
{
    verify_csrf();
    require_login();
    $userId = uid();

    $type = strtolower(trim((string)($_POST['type'] ?? '')));
    $validTypes = ['savings', 'etf', 'stock'];
    if (!in_array($type, $validTypes, true)) {
        $type = 'savings';
    }

    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') {
        $_SESSION['flash'] = __('Name is required.');
        redirect('/investments');
    }

    $provider = trim((string)($_POST['provider'] ?? '')) ?: null;
    $identifier = trim((string)($_POST['identifier'] ?? '')) ?: null;
    $interestRateInput = trim((string)($_POST['interest_rate'] ?? ''));
    $interestRate = $interestRateInput === '' ? null : (float)str_replace(',', '.', $interestRateInput);
    $notes = trim((string)($_POST['notes'] ?? '')) ?: null;
    $frequencyInput = strtolower(trim((string)($_POST['interest_frequency'] ?? '')));
    if (!in_array($frequencyInput, investments_allowed_frequencies(), true)) {
        $frequencyInput = 'monthly';
    }
    $currencyInput = strtoupper(trim((string)($_POST['currency'] ?? '')));
    if ($currencyInput === '') {
        $mainCurrency = fx_user_main($pdo, $userId);
        $currencyInput = $mainCurrency !== '' ? strtoupper($mainCurrency) : 'HUF';
    }
    $initialAmountRaw = trim((string)($_POST['initial_amount'] ?? ''));
    $initialAmount = $initialAmountRaw === '' ? 0.0 : (float)str_replace(',', '.', $initialAmountRaw);
    if (!is_finite($initialAmount)) {
        $initialAmount = 0.0;
    }
    if ($initialAmount < 0) {
        $initialAmount = 0.0;
    }
    $scheduleMode = strtolower(trim((string)($_POST['scheduled_mode'] ?? 'existing')));
    if (!in_array($scheduleMode, ['existing', 'new'], true)) {
        $scheduleMode = 'existing';
    }

    $scheduleId = null;
    $newSchedule = null;

    if ($scheduleMode === 'existing') {
        $scheduleIdRaw = $_POST['scheduled_payment_id'] ?? '';
        $scheduleId = ($scheduleIdRaw !== '' && $scheduleIdRaw !== null) ? (int)$scheduleIdRaw : null;
    } else {
        $newTitle = trim((string)($_POST['scheduled_new_title'] ?? ''));
        $newAmountRaw = trim((string)($_POST['scheduled_new_amount'] ?? ''));
        $newAmount = $newAmountRaw === '' ? 0.0 : (float)str_replace(',', '.', $newAmountRaw);
        if (!is_finite($newAmount)) {
            $newAmount = 0.0;
        }
        $newCurrency = strtoupper(trim((string)($_POST['scheduled_new_currency'] ?? '')));
        $newNextDue = trim((string)($_POST['scheduled_new_next_due'] ?? ''));
        $newRrule = trim((string)($_POST['scheduled_new_rrule'] ?? ''));
        $newCategoryId = ($_POST['scheduled_new_category_id'] ?? '') !== '' ? (int)$_POST['scheduled_new_category_id'] : null;

        if ($newTitle === '' || $newAmount <= 0 || $newNextDue === '') {
            $_SESSION['flash'] = __('Provide a title, amount, and first due date for the schedule.');
            redirect('/investments');
        }

        if ($newCurrency === '') {
            $newCurrency = $currencyInput;
        }

        if ($newCategoryId !== null) {
            $catCheck = $pdo->prepare('SELECT id FROM categories WHERE id=? AND user_id=?');
            $catCheck->execute([$newCategoryId, $userId]);
            if (!$catCheck->fetchColumn()) {
                $newCategoryId = null;
            }
        }

        $newSchedule = [
            'title' => $newTitle,
            'amount' => $newAmount,
            'currency' => $newCurrency,
            'next_due' => $newNextDue,
            'rrule' => $newRrule,
            'category_id' => $newCategoryId,
        ];
    }

    $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare("INSERT INTO investments (user_id, type, name, provider, identifier, interest_rate, interest_frequency, notes, currency, balance, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW()) RETURNING id");
        $insert->execute([$userId, $type, $name, $provider, $identifier, $interestRate, $frequencyInput, $notes, $currencyInput, $initialAmount]);
        $investmentId = (int)$insert->fetchColumn();

        if (abs($initialAmount) > 0.00001) {
            $txInsert = $pdo->prepare('INSERT INTO investment_transactions (investment_id, user_id, amount, note) VALUES (?,?,?,?)');
            $txInsert->execute([$investmentId, $userId, $initialAmount, __('Initial balance')]);
        }

        if ($scheduleId) {
            $check = $pdo->prepare('SELECT id FROM scheduled_payments WHERE id=? AND user_id=? AND loan_id IS NULL AND goal_id IS NULL AND investment_id IS NULL');
            $check->execute([$scheduleId, $userId]);
            if ($check->fetchColumn()) {
                $link = $pdo->prepare('UPDATE scheduled_payments SET investment_id=? WHERE id=? AND user_id=?');
                $link->execute([$investmentId, $scheduleId, $userId]);
            }
        }

        if ($newSchedule) {
            $insertSchedule = $pdo->prepare('INSERT INTO scheduled_payments (user_id, title, amount, currency, next_due, rrule, category_id, investment_id) VALUES (?,?,?,?,?,?,?,?)');
            $insertSchedule->execute([
                $userId,
                $newSchedule['title'],
                $newSchedule['amount'],
                $newSchedule['currency'],
                $newSchedule['next_due'],
                $newSchedule['rrule'],
                $newSchedule['category_id'],
                $investmentId,
            ]);
        }

        $pdo->commit();
        $_SESSION['flash'] = __('Investment added.');
    } catch (Throwable $e) {
        $pdo->rollBack();
        $_SESSION['flash'] = __('Could not add investment.');
    }

    redirect('/investments');
}

function investments_update(PDO $pdo): void
{
    verify_csrf();
    require_login();
    $userId = uid();

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        redirect('/investments');
    }

    $currentStmt = $pdo->prepare('SELECT id FROM investments WHERE id=? AND user_id=?');
    $currentStmt->execute([$id, $userId]);
    if (!$currentStmt->fetch(PDO::FETCH_ASSOC)) {
        redirect('/investments');
    }

    $type = strtolower(trim((string)($_POST['type'] ?? '')));
    $validTypes = ['savings', 'etf', 'stock'];
    if (!in_array($type, $validTypes, true)) {
        $type = 'savings';
    }

    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') {
        $_SESSION['flash'] = __('Name is required.');
        redirect('/investments');
    }

    $provider = trim((string)($_POST['provider'] ?? '')) ?: null;
    $identifier = trim((string)($_POST['identifier'] ?? '')) ?: null;
    $interestRateInput = trim((string)($_POST['interest_rate'] ?? ''));
    $interestRate = $interestRateInput === '' ? null : (float)str_replace(',', '.', $interestRateInput);
    $notes = trim((string)($_POST['notes'] ?? '')) ?: null;
    $frequencyInput = strtolower(trim((string)($_POST['interest_frequency'] ?? '')));
    if (!in_array($frequencyInput, investments_allowed_frequencies(), true)) {
        $frequencyInput = 'monthly';
    }
    $currencyInput = strtoupper(trim((string)($_POST['currency'] ?? '')));
    if ($currencyInput === '') {
        $mainCurrency = fx_user_main($pdo, $userId);
        $currencyInput = $mainCurrency !== '' ? strtoupper($mainCurrency) : 'HUF';
    }
    $scheduleIdRaw = $_POST['scheduled_payment_id'] ?? '';
    $newScheduleId = ($scheduleIdRaw !== '' && $scheduleIdRaw !== null) ? (int)$scheduleIdRaw : null;

    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare('UPDATE investments SET type=?, name=?, provider=?, identifier=?, interest_rate=?, interest_frequency=?, notes=?, currency=?, updated_at=NOW() WHERE id=? AND user_id=?');
        $update->execute([$type, $name, $provider, $identifier, $interestRate, $frequencyInput, $notes, $currencyInput, $id, $userId]);

        $currentScheduleStmt = $pdo->prepare('SELECT id FROM scheduled_payments WHERE investment_id=? AND user_id=?');
        $currentScheduleStmt->execute([$id, $userId]);
        $currentScheduleId = $currentScheduleStmt->fetchColumn();

        if ($currentScheduleId && (!$newScheduleId || $newScheduleId !== (int)$currentScheduleId)) {
            $clear = $pdo->prepare('UPDATE scheduled_payments SET investment_id=NULL WHERE id=? AND user_id=?');
            $clear->execute([(int)$currentScheduleId, $userId]);
        }

        if ($newScheduleId) {
            $check = $pdo->prepare('SELECT id FROM scheduled_payments WHERE id=? AND user_id=? AND loan_id IS NULL AND goal_id IS NULL AND (investment_id IS NULL OR investment_id=?)');
            $check->execute([$newScheduleId, $userId, $id]);
            if ($check->fetchColumn()) {
                $link = $pdo->prepare('UPDATE scheduled_payments SET investment_id=? WHERE id=? AND user_id=?');
                $link->execute([$id, $newScheduleId, $userId]);
            }
        }

        $pdo->commit();
        $_SESSION['flash'] = __('Investment updated.');
    } catch (Throwable $e) {
        $pdo->rollBack();
        $_SESSION['flash'] = __('Could not update investment.');
    }

    redirect('/investments');
}

function investments_delete(PDO $pdo): void
{
    verify_csrf();
    require_login();
    $userId = uid();

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        redirect('/investments');
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE scheduled_payments SET investment_id=NULL WHERE investment_id=? AND user_id=?')->execute([$id, $userId]);
        $pdo->prepare('UPDATE emergency_fund SET investment_id=NULL WHERE investment_id=? AND user_id=?')->execute([$id, $userId]);
        $pdo->prepare('DELETE FROM investments WHERE id=? AND user_id=?')->execute([$id, $userId]);
        $pdo->commit();
        $_SESSION['flash'] = __('Investment deleted.');
    } catch (Throwable $e) {
        $pdo->rollBack();
        $_SESSION['flash'] = __('Could not delete investment.');
    }

    redirect('/investments');
}

function investments_adjust(PDO $pdo): void
{
    verify_csrf();
    require_login();

    $userId = uid();
    $id = (int)($_POST['id'] ?? 0);
    $direction = strtolower(trim((string)($_POST['direction'] ?? 'deposit')));
    $amountRaw = trim((string)($_POST['amount'] ?? ''));
    $note = trim((string)($_POST['note'] ?? '')) ?: null;

    if ($id <= 0 || $amountRaw === '') {
        redirect('/investments');
    }

    $amount = (float)str_replace(',', '.', $amountRaw);
    if (!is_finite($amount) || $amount <= 0) {
        $_SESSION['flash'] = __('Enter an amount greater than zero.');
        redirect('/investments');
    }

    $delta = $direction === 'withdraw' ? -abs($amount) : abs($amount);

    $pdo->beginTransaction();
    try {
        $currentStmt = $pdo->prepare('SELECT balance FROM investments WHERE id=? AND user_id=? FOR UPDATE');
        $currentStmt->execute([$id, $userId]);
        $current = $currentStmt->fetch(PDO::FETCH_ASSOC);
        if (!$current) {
            $pdo->rollBack();
            redirect('/investments');
        }

        $newBalance = (float)$current['balance'] + $delta;
        if ($newBalance < 0) {
            $pdo->rollBack();
            $_SESSION['flash'] = __('Cannot withdraw more than the current balance.');
            redirect('/investments');
        }

        $update = $pdo->prepare('UPDATE investments SET balance=?, updated_at=NOW() WHERE id=? AND user_id=?');
        $update->execute([$newBalance, $id, $userId]);

        $txInsert = $pdo->prepare('INSERT INTO investment_transactions (investment_id, user_id, amount, note) VALUES (?,?,?,?)');
        $txInsert->execute([$id, $userId, $delta, $note]);

        $pdo->commit();

        $_SESSION['flash'] = $delta >= 0 ? __('Deposit recorded.') : __('Withdrawal recorded.');
    } catch (Throwable $e) {
        $pdo->rollBack();
        $_SESSION['flash'] = __('Could not update balance.');
    }

    redirect('/investments');
}

function investments_schedule_create(PDO $pdo): void
{
    verify_csrf();
    require_login();

    $userId = uid();
    $investmentId = (int)($_POST['investment_id'] ?? 0);
    if ($investmentId <= 0) {
        redirect('/investments');
    }

    $stmt = $pdo->prepare('SELECT id, currency FROM investments WHERE id=? AND user_id=?');
    $stmt->execute([$investmentId, $userId]);
    $investment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$investment) {
        redirect('/investments');
    }

    $existingStmt = $pdo->prepare('SELECT id FROM scheduled_payments WHERE investment_id=? AND user_id=? LIMIT 1');
    $existingStmt->execute([$investmentId, $userId]);
    if ($existingStmt->fetchColumn()) {
        $_SESSION['flash'] = __('Unlink the existing schedule before creating a new one.');
        redirect('/investments');
    }

    $title = trim((string)($_POST['title'] ?? ''));
    $amountRaw = trim((string)($_POST['amount'] ?? ''));
    $amount = $amountRaw === '' ? 0.0 : (float)str_replace(',', '.', $amountRaw);
    $currency = strtoupper(trim((string)($_POST['currency'] ?? '')));
    $nextDue = trim((string)($_POST['next_due'] ?? ''));
    $rrule = trim((string)($_POST['rrule'] ?? ''));
    $categoryId = ($_POST['category_id'] ?? '') !== '' ? (int)$_POST['category_id'] : null;

    if ($title === '' || $amount <= 0 || $nextDue === '') {
        $_SESSION['flash'] = __('Provide a title, amount, and first due date for the schedule.');
        redirect('/investments');
    }

    if ($currency === '') {
        $currency = strtoupper((string)($investment['currency'] ?? 'HUF')) ?: 'HUF';
    }

    if ($categoryId !== null) {
        $catCheck = $pdo->prepare('SELECT id FROM categories WHERE id=? AND user_id=?');
        $catCheck->execute([$categoryId, $userId]);
        if (!$catCheck->fetchColumn()) {
            $categoryId = null;
        }
    }

    try {
        $insert = $pdo->prepare('INSERT INTO scheduled_payments (user_id, title, amount, currency, next_due, rrule, category_id, investment_id) VALUES (?,?,?,?,?,?,?,?)');
        $insert->execute([$userId, $title, $amount, $currency, $nextDue, $rrule, $categoryId, $investmentId]);
        $_SESSION['flash'] = __('Scheduled payment created and linked.');
    } catch (Throwable $e) {
        $_SESSION['flash'] = __('Could not create scheduled payment.');
    }

    redirect('/investments');
}
