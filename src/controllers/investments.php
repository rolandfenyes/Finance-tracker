<?php

require_once __DIR__ . '/../helpers.php';

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

    $scheduleStmt = $pdo->prepare("SELECT id, title, amount, currency, next_due
        FROM scheduled_payments
        WHERE user_id = ?
          AND loan_id IS NULL
          AND goal_id IS NULL
          AND investment_id IS NULL
        ORDER BY lower(title)");
    $scheduleStmt->execute([$userId]);
    $availableSchedules = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    view('investments/index', [
        'investments' => $investments,
        'availableSchedules' => $availableSchedules,
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
    $scheduleIdRaw = $_POST['scheduled_payment_id'] ?? '';
    $scheduleId = ($scheduleIdRaw !== '' && $scheduleIdRaw !== null) ? (int)$scheduleIdRaw : null;

    $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare("INSERT INTO investments (user_id, type, name, provider, identifier, interest_rate, notes, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,NOW(),NOW()) RETURNING id");
        $insert->execute([$userId, $type, $name, $provider, $identifier, $interestRate, $notes]);
        $investmentId = (int)$insert->fetchColumn();

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
    $scheduleIdRaw = $_POST['scheduled_payment_id'] ?? '';
    $newScheduleId = ($scheduleIdRaw !== '' && $scheduleIdRaw !== null) ? (int)$scheduleIdRaw : null;

    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare('UPDATE investments SET type=?, name=?, provider=?, identifier=?, interest_rate=?, notes=?, updated_at=NOW() WHERE id=? AND user_id=?');
        $update->execute([$type, $name, $provider, $identifier, $interestRate, $notes, $id, $userId]);

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
