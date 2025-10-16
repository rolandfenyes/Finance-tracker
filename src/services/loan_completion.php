<?php
// src/services/loan_completion.php

require_once __DIR__ . '/email_notifications.php';

/**
 * Disable a loan's linked schedule and send completion emails when the balance
 * crosses from a positive value to paid-off (<= 0.01 in loan currency).
 */
function loan_maybe_handle_completion(PDO $pdo, int $userId, int $loanId, float $previousBalance): void
{
    $loanStmt = $pdo->prepare('SELECT balance, scheduled_payment_id, finished_at FROM loans WHERE id = ? AND user_id = ?');
    $loanStmt->execute([$loanId, $userId]);
    $loan = $loanStmt->fetch(PDO::FETCH_ASSOC);

    if (!$loan) {
        return;
    }

    $currentBalance = max(0.0, (float)($loan['balance'] ?? 0.0));
    $finishedAt = !empty($loan['finished_at']);

    // Only take action when the balance is effectively paid off or the loan was already
    // flagged as finished previously.
    if ($currentBalance > 0.01 && !$finishedAt) {
        return;
    }

    $scheduleId = (int)($loan['scheduled_payment_id'] ?? 0);
    if ($scheduleId > 0) {
        $pdo->prepare('UPDATE scheduled_payments SET next_due = NULL WHERE id = ? AND user_id = ?')
            ->execute([$scheduleId, $userId]);
    }

    if (!$finishedAt && $currentBalance <= 0.01) {
        $pdo->prepare('UPDATE loans SET finished_at = COALESCE(finished_at, CURRENT_TIMESTAMP) WHERE id = ? AND user_id = ?')
            ->execute([$loanId, $userId]);

        email_maybe_send_loan_completion($pdo, $userId, $loanId, $previousBalance);
    }
}
