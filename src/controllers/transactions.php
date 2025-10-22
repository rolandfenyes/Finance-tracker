<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../fx.php';
require_once __DIR__ . '/../services/email_notifications.php';

function tx_add(PDO $pdo) {
    verify_csrf(); require_login(); $u = uid();
    $kind = $_POST['kind'] === 'income' ? 'income' : 'spending';
    $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $amount = (float)$_POST['amount'];
    $currency = $_POST['currency'] ?? 'HUF';
    $date = $_POST['occurred_on'] ?? date('Y-m-d');
    $note = trim($_POST['note'] ?? '');

    $stmt = $pdo->prepare('INSERT INTO transactions(user_id,kind,category_id,amount,currency,occurred_on,note) VALUES(?,?,?,?,?,?,?)');
    $stmt->execute([$u, $kind, $categoryId, $amount, $currency, $date, $note]);

    if ($kind === 'spending' && $categoryId) {
        try {
            tx_maybe_send_cashflow_overspend($pdo, $u, $categoryId, $amount, $currency, $date, null);
        } catch (Throwable $mailError) {
            error_log('Cashflow overspend email failed after insert for user ' . $u . ': ' . $mailError->getMessage());
        }
    }
}

function tx_edit(PDO $pdo) {
    verify_csrf(); require_login(); $u = uid();
    $id = (int)$_POST['id'];

    $currentStmt = $pdo->prepare('SELECT kind, category_id, amount, currency, occurred_on, locked FROM transactions WHERE id=? AND user_id=?');
    $currentStmt->execute([$id, $u]);
    $previous = $currentStmt->fetch(PDO::FETCH_ASSOC);
    if (!$previous) {
        return;
    }

    if (!empty($previous['locked'])) {
        http_response_code(403);
        return;
    }

    $kind = $_POST['kind'] === 'income' ? 'income' : 'spending';
    $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $amount = (float)$_POST['amount'];
    $currency = $_POST['currency'] ?? 'HUF';
    $date = $_POST['occurred_on'] ?? date('Y-m-d');
    $note = trim($_POST['note'] ?? '');

    $stmt = $pdo->prepare('UPDATE transactions SET kind=?, category_id=?, amount=?, currency=?, occurred_on=?, note=?, updated_at=NOW() WHERE id=? AND user_id=?');
    $stmt->execute([
        $kind,
        $categoryId,
        $amount,
        $currency,
        $date,
        $note,
        $id,
        $u,
    ]);

    if ($kind === 'spending' && $categoryId) {
        try {
            tx_maybe_send_cashflow_overspend($pdo, $u, $categoryId, $amount, $currency, $date, $previous);
        } catch (Throwable $mailError) {
            error_log('Cashflow overspend email failed after update for user ' . $u . ': ' . $mailError->getMessage());
        }
    }
}

function tx_delete(PDO $pdo) {
    verify_csrf(); require_login(); $u = uid();
    $id = (int)$_POST['id'];
    if ($id <= 0) {
        return;
    }

    $rowStmt = $pdo->prepare('SELECT locked FROM transactions WHERE id=? AND user_id=?');
    $rowStmt->execute([$id, $u]);
    $row = $rowStmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return;
    }

    if (!empty($row['locked'])) {
        http_response_code(403);
        return;
    }

    $pdo->prepare('DELETE FROM transactions WHERE id=? AND user_id=?')->execute([$id, $u]);
}

function tx_maybe_send_cashflow_overspend(PDO $pdo, int $userId, int $categoryId, float $amount, string $currency, string $occurredOn, ?array $previous): void
{
    $categoryStmt = $pdo->prepare('SELECT cashflow_rule_id FROM categories WHERE id = ? AND user_id = ?');
    $categoryStmt->execute([$categoryId, $userId]);
    $ruleId = (int)($categoryStmt->fetchColumn() ?: 0);
    if ($ruleId <= 0) {
        return;
    }

    try {
        $occurredDate = new DateTimeImmutable($occurredOn);
    } catch (Exception $e) {
        $occurredDate = new DateTimeImmutable();
    }

    try {
        $periodStart = new DateTimeImmutable($occurredDate->format('Y-m-01'));
        $periodEnd = new DateTimeImmutable($occurredDate->format('Y-m-t'));
    } catch (Exception $e) {
        $periodStart = $occurredDate;
        $periodEnd = $occurredDate;
    }

    $mainCurrency = fx_user_main($pdo, $userId);
    if (!is_string($mainCurrency) || $mainCurrency === '') {
        $mainCurrency = strtoupper($currency) ?: 'HUF';
    }

    $transactionDate = $occurredDate->format('Y-m-d');
    $sourceCurrency = strtoupper($currency) ?: $mainCurrency;
    $newAmountMain = strtoupper($sourceCurrency) === strtoupper($mainCurrency)
        ? $amount
        : fx_convert($pdo, $amount, $sourceCurrency, $mainCurrency, $transactionDate);
    $newAmountMain = max(0.0, abs($newAmountMain));

    $status = email_collect_cashflow_rule_status($pdo, $userId, $ruleId, $periodStart, $periodEnd);
    if (!$status) {
        return;
    }

    $previousSpent = max(0.0, (float)$status['spent'] - $newAmountMain);

    if ($previous && strtolower((string)($previous['kind'] ?? '')) === 'spending') {
        $prevCategoryId = (int)($previous['category_id'] ?? 0);
        $previousRuleId = 0;
        if ($prevCategoryId > 0) {
            $prevStmt = $pdo->prepare('SELECT cashflow_rule_id FROM categories WHERE id = ? AND user_id = ?');
            $prevStmt->execute([$prevCategoryId, $userId]);
            $previousRuleId = (int)($prevStmt->fetchColumn() ?: 0);
        }

        $prevCurrency = (string)($previous['currency'] ?? $mainCurrency);
        $prevDate = (string)($previous['occurred_on'] ?? $transactionDate);
        $prevAmount = (float)($previous['amount'] ?? 0.0);
        $prevAmountMain = strtoupper($prevCurrency) === strtoupper($mainCurrency)
            ? $prevAmount
            : fx_convert($pdo, $prevAmount, $prevCurrency, $mainCurrency, $prevDate);
        $prevAmountMain = max(0.0, abs($prevAmountMain));

        if ($previousRuleId === $ruleId) {
            $previousSpent = max(0.0, $previousSpent + $prevAmountMain);
        }
    }

    email_send_cashflow_overspend($pdo, $userId, $status, $periodStart, $periodEnd, $previousSpent);
}