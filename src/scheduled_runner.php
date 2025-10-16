<?php
// src/scheduled_runner.php

require_once __DIR__ . '/recurrence.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/services/loan_completion.php';

/**
 * Compute the next occurrence of a schedule after a given date.
 */
function scheduled_next_occurrence(?string $dtstart, string $rrule, string $afterDate): ?string {
  if (!$dtstart) {
    return null;
  }

  $after = new DateTimeImmutable($afterDate);
  $rangeStart = $after->modify('+1 day')->format('Y-m-d');
  $rangeEnd   = $after->modify('+10 years')->format('Y-m-d');
  $dates = rrule_expand($dtstart, $rrule, $rangeStart, $rangeEnd);
  return $dates[0] ?? null;
}

function scheduled_build_rrule(array $parts): string {
  $items = [];
  if (!empty($parts['FREQ'])) {
    $items[] = 'FREQ=' . $parts['FREQ'];
  }
  if (!empty($parts['INTERVAL']) && (int)$parts['INTERVAL'] !== 1) {
    $items[] = 'INTERVAL=' . (int)$parts['INTERVAL'];
  }
  if (!empty($parts['BYDAY'])) {
    $items[] = 'BYDAY=' . implode(',', array_map('strtoupper', $parts['BYDAY']));
  }
  if (array_key_exists('BYMONTHDAY', $parts) && $parts['BYMONTHDAY'] !== null) {
    $items[] = 'BYMONTHDAY=' . (int)$parts['BYMONTHDAY'];
  }
  if (array_key_exists('BYMONTH', $parts) && $parts['BYMONTH'] !== null) {
    $items[] = 'BYMONTH=' . (int)$parts['BYMONTH'];
  }
  if (!empty($parts['UNTIL'])) {
    $items[] = 'UNTIL=' . $parts['UNTIL'];
  }
  if (array_key_exists('COUNT', $parts) && $parts['COUNT'] !== null && (int)$parts['COUNT'] > 0) {
    $items[] = 'COUNT=' . (int)$parts['COUNT'];
  }
  return implode(';', $items);
}

/**
 * Apply scheduled payments linked to goals.
 */
function scheduled_apply_goal(PDO $pdo, array $schedule, string $dueDate): void {
  $goalId = (int)($schedule['goal_id'] ?? 0);
  if (!$goalId) {
    return;
  }

  $u = (int)($schedule['user_id'] ?? 0);
  $goalStmt = $pdo->prepare('SELECT id, user_id, currency FROM goals WHERE id=? AND user_id=?');
  $goalStmt->execute([$goalId, $u]);
  $goal = $goalStmt->fetch(PDO::FETCH_ASSOC);
  if (!$goal) {
    return;
  }

  $amount = (float)($schedule['amount'] ?? 0);
  if ($amount <= 0) {
    return;
  }

  $currency = strtoupper($schedule['currency'] ?? '');
  if ($currency === '') {
    $currency = $goal['currency'] ?: 'HUF';
  }

  $note = 'Scheduled: ' . trim((string)($schedule['title'] ?? 'Goal contribution'));

  $ins = $pdo->prepare('INSERT INTO goal_contributions(goal_id,user_id,amount,currency,occurred_on,note) VALUES (?,?,?,?,?,?)');
  $ins->execute([$goalId, $u, $amount, $currency, $dueDate, $note]);

  $goalCurrency = $goal['currency'] ?: $currency;
  if ($currency === $goalCurrency) {
    $delta = $amount;
  } else {
    $converted = function_exists('fx_convert')
      ? fx_convert($pdo, $amount, $currency, $goalCurrency, $dueDate)
      : null;
    $delta = is_numeric($converted) ? (float)$converted : $amount;
  }

  $upd = $pdo->prepare('UPDATE goals SET current_amount = COALESCE(current_amount,0) + ?, currency = COALESCE(currency, ?) WHERE id=? AND user_id=?');
  $upd->execute([$delta, $goalCurrency, $goalId, $u]);
}

/**
 * Apply scheduled payments linked to loans.
 */
function scheduled_apply_loan(PDO $pdo, array $schedule, string $dueDate): void {
  $loanId = (int)($schedule['loan_id'] ?? 0);
  if (!$loanId) {
    return;
  }

  $u = (int)($schedule['user_id'] ?? 0);
  $loanStmt = $pdo->prepare('SELECT id, user_id, currency, name, balance FROM loans WHERE id=? AND user_id=?');
  $loanStmt->execute([$loanId, $u]);
  $loan = $loanStmt->fetch(PDO::FETCH_ASSOC);
  if (!$loan) {
    return;
  }

  $amount = (float)($schedule['amount'] ?? 0);
  if ($amount <= 0) {
    return;
  }

  $currency = strtoupper($schedule['currency'] ?? '');
  if ($currency === '') {
    $currency = $loan['currency'] ?: 'HUF';
  }

  $categoryId = (int)($schedule['category_id'] ?? 0);
  if ($categoryId <= 0) { $categoryId = null; }

  $transactionId = null;
  $note = 'Loan payment · ' . trim((string)($loan['name'] ?? ''));
  if ($note === 'Loan payment ·') {
    $note = 'Loan payment';
  }

  $tx = $pdo->prepare('INSERT INTO transactions(user_id,kind,category_id,amount,currency,occurred_on,note) VALUES(?,?,?,?,?,?,?) RETURNING id');
  $tx->execute([$u, 'spending', $categoryId, $amount, $currency, $dueDate, $note]);
  $transactionId = (int)$tx->fetchColumn();

  $ins = $pdo->prepare('INSERT INTO loan_payments(loan_id,paid_on,amount,principal_component,interest_component,currency,transaction_id) VALUES (?,?,?,?,?,?,?)');
  $ins->execute([$loanId, $dueDate, $amount, $amount, 0.0, $currency, $transactionId ?: null]);

  $loanCurrency = $loan['currency'] ?: $currency;
  if ($currency === $loanCurrency) {
    $principalDelta = $amount;
  } else {
    $converted = function_exists('fx_convert')
      ? fx_convert($pdo, $amount, $currency, $loanCurrency, $dueDate)
      : null;
    $principalDelta = is_numeric($converted) ? (float)$converted : $amount;
  }

  $previousBalance = max(0.0, (float)($loan['balance'] ?? 0.0));

  $pdo->prepare('UPDATE loans SET balance = GREATEST(0, balance - ?) WHERE id=? AND user_id=?')->execute([$principalDelta, $loanId, $u]);

  loan_maybe_handle_completion($pdo, $u, $loanId, $previousBalance);
}

/**
 * Process due scheduled payments linked to goals or loans for the given user.
 */
function scheduled_process_linked(PDO $pdo, int $userId, ?string $today = null): void {
  if ($userId <= 0) {
    return;
  }

  $today = $today ?: date('Y-m-d');

  $listStmt = $pdo->prepare('SELECT id FROM scheduled_payments WHERE user_id=? AND next_due IS NOT NULL AND next_due <= ? AND (goal_id IS NOT NULL OR loan_id IS NOT NULL) ORDER BY next_due, id');
  $listStmt->execute([$userId, $today]);
  $ids = $listStmt->fetchAll(PDO::FETCH_COLUMN);

  foreach ($ids as $id) {
    $pdo->beginTransaction();
    try {
      $rowStmt = $pdo->prepare('SELECT * FROM scheduled_payments WHERE id=? AND user_id=? FOR UPDATE');
      $rowStmt->execute([(int)$id, $userId]);
      $schedule = $rowStmt->fetch(PDO::FETCH_ASSOC);
      if (!$schedule) {
        $pdo->commit();
        continue;
      }

      $due = $schedule['next_due'] ?? null;
      if (!$due || $due > $today) {
        $pdo->commit();
        continue;
      }

      $rruleString = (string)($schedule['rrule'] ?? '');
      $rruleParts  = rrule_parse($rruleString);
      $countRemaining = $rruleParts['COUNT'];

      while ($due !== null && $due <= $today) {
        if (!empty($schedule['goal_id'])) {
          scheduled_apply_goal($pdo, $schedule, $due);
        }
        if (!empty($schedule['loan_id'])) {
          scheduled_apply_loan($pdo, $schedule, $due);
        }

        $processedDue = $schedule['next_due'];

        if ($countRemaining !== null) {
          $countRemaining--;
          if ($countRemaining <= 0) {
            $rruleParts['COUNT'] = null;
            $rruleString = scheduled_build_rrule($rruleParts);
            $schedule['rrule'] = $rruleString;
            $schedule['next_due'] = null;
            $due = null;
            break;
          }
          $rruleParts['COUNT'] = $countRemaining;
          $rruleString = scheduled_build_rrule($rruleParts);
          $schedule['rrule'] = $rruleString;
        }

        $next = scheduled_next_occurrence($processedDue, $rruleString, $processedDue);
        if ($next !== null && $next <= $processedDue) {
          $schedule['next_due'] = null;
          $due = null;
          break;
        }

        $schedule['next_due'] = $next;
        $due = $next;
      }

      $upd = $pdo->prepare('UPDATE scheduled_payments SET next_due=?, rrule=? WHERE id=? AND user_id=?');
      $upd->execute([$schedule['next_due'], $schedule['rrule'], (int)$id, $userId]);

      $pdo->commit();
    } catch (Throwable $e) {
      $pdo->rollBack();
      // Optionally log in the future.
    }
  }
}
