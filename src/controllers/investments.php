<?php

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../fx.php';

function investments_index(PDO $pdo): void
{
    require_login();
    $userId = uid();

    $stmt = $pdo->prepare("SELECT i.*, sp.id AS sched_id, sp.title AS sched_title, sp.amount AS sched_amount, sp.currency AS sched_currency, sp.next_due AS sched_next_due
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
            ORDER BY created_at DESC"
        );
        $txStmt->execute(array_merge($investmentIds, [$userId]));
        while ($row = $txStmt->fetch(PDO::FETCH_ASSOC)) {
            $invId = (int)($row['investment_id'] ?? 0);
            if ($invId) {
                $transactionsByInvestment[$invId][] = $row;
            }
        }
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

    view('investments/index', [
        'investments' => $investments,
        'availableSchedules' => $availableSchedules,
        'transactionsByInvestment' => $transactionsByInvestment,
        'userCurrencies' => $userCurrencies,
        'mainCurrency' => $mainCurrency,
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
    $scheduleIdRaw = $_POST['scheduled_payment_id'] ?? '';
    $scheduleId = ($scheduleIdRaw !== '' && $scheduleIdRaw !== null) ? (int)$scheduleIdRaw : null;

    $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare("INSERT INTO investments (user_id, type, name, provider, identifier, interest_rate, notes, currency, balance, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW()) RETURNING id");
        $insert->execute([$userId, $type, $name, $provider, $identifier, $interestRate, $notes, $currencyInput, $initialAmount]);
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
    $currencyInput = strtoupper(trim((string)($_POST['currency'] ?? '')));
    if ($currencyInput === '') {
        $mainCurrency = fx_user_main($pdo, $userId);
        $currencyInput = $mainCurrency !== '' ? strtoupper($mainCurrency) : 'HUF';
    }
    $scheduleIdRaw = $_POST['scheduled_payment_id'] ?? '';
    $newScheduleId = ($scheduleIdRaw !== '' && $scheduleIdRaw !== null) ? (int)$scheduleIdRaw : null;

    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare('UPDATE investments SET type=?, name=?, provider=?, identifier=?, interest_rate=?, notes=?, currency=?, updated_at=NOW() WHERE id=? AND user_id=?');
        $update->execute([$type, $name, $provider, $identifier, $interestRate, $notes, $currencyInput, $id, $userId]);

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
