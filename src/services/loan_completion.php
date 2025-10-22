<?php
// src/services/loan_completion.php

require_once __DIR__ . '/email_notifications.php';

/**
 * Disable a loan's linked schedule and send completion emails when the balance
 * crosses from a positive value to paid-off (<= 0.01 in loan currency).
 */
function loan_maybe_handle_completion(PDO $pdo, int $userId, int $loanId, float $previousBalance): void
{
    $loanStmt = $pdo->prepare('SELECT balance, scheduled_payment_id, finished_at, archived_at FROM loans WHERE id = ? AND user_id = ?');
    $loanStmt->execute([$loanId, $userId]);
    $loan = $loanStmt->fetch(PDO::FETCH_ASSOC);

    if (!$loan) {
        return;
    }

    $currentBalance = max(0.0, (float)($loan['balance'] ?? 0.0));
    $finishedAt = !empty($loan['finished_at']);
    $archivedAt = !empty($loan['archived_at']);
    $isPaidOff = $currentBalance <= 0.01;

    // Only take action when the balance is effectively paid off or the loan was already
    // flagged as finished previously.
    if ($currentBalance > 0.01 && !$finishedAt) {
        return;
    }

    $scheduleId = (int)($loan['scheduled_payment_id'] ?? 0);
    if (($finishedAt || $isPaidOff) && $scheduleId > 0) {
        $pdo->prepare('UPDATE scheduled_payments
                           SET next_due = NULL,
                               archived_at = COALESCE(archived_at, CURRENT_TIMESTAMP)
                         WHERE id = ? AND user_id = ?')
            ->execute([$scheduleId, $userId]);
    }

    if ($isPaidOff) {
        $pdo->prepare('UPDATE loans
                           SET finished_at = COALESCE(finished_at, CURRENT_TIMESTAMP),
                               archived_at = COALESCE(archived_at, CURRENT_TIMESTAMP)
                         WHERE id = ? AND user_id = ?')
            ->execute([$loanId, $userId]);

        if (!$finishedAt) {
            email_maybe_send_loan_completion($pdo, $userId, $loanId, $previousBalance);
        }
    } elseif ($finishedAt && !$archivedAt) {
        $pdo->prepare('UPDATE loans
                           SET archived_at = COALESCE(archived_at, finished_at, CURRENT_TIMESTAMP)
                         WHERE id = ? AND user_id = ?')
            ->execute([$loanId, $userId]);
    }
}
