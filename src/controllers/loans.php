<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../services/loan_completion.php';

function loans_index(PDO $pdo){
  require_login(); $u=uid();

  $q = $pdo->prepare("
    SELECT l.*,
          sp.title    AS sched_title,
          sp.amount   AS sched_amount,     -- NEW
          sp.currency AS sched_currency,   -- NEW
          sp.rrule    AS sched_rrule,
          sp.next_due AS sched_next_due
    FROM loans l
    LEFT JOIN scheduled_payments sp
          ON sp.id = l.scheduled_payment_id AND sp.user_id = l.user_id
    WHERE l.user_id = ?
    ORDER BY COALESCE(l.start_date, CURRENT_DATE) DESC, l.id DESC
  ");
  $q->execute([$u]);
  $rows = $q->fetchAll(PDO::FETCH_ASSOC);

  // Sum of recorded components for each loan (used to reconcile against amortization)
  $sumStmt = $pdo->prepare("
    SELECT
      COALESCE(SUM(principal_component), 0) AS principal_total,
      COALESCE(SUM(interest_component), 0) AS interest_total
    FROM loan_payments
    WHERE loan_id = ?
  ");

  // Enrich rows with computed progress using amortization
  foreach ($rows as &$l) {
    $principal  = (float)($l['principal'] ?? 0);
    $ratePct    = (float)($l['interest_rate'] ?? 0);
    $start      = $l['start_date'] ?: date('Y-m-d');
    $end        = $l['end_date'] ?: null;
    $pday       = !empty($l['payment_day']) ? (int)$l['payment_day'] : null;
    $extra      = (float)($l['extra_payment'] ?? 0);
    $insurance  = (float)($l['insurance_monthly'] ?? 0);
    $currency   = $l['currency'] ?: 'HUF';

    // Monthly TOTAL (P&I + insurance)
    $monthly_total = 0.0;
    if (!empty($l['scheduled_payment_id'])) {
      $spq = $pdo->prepare("SELECT amount FROM scheduled_payments WHERE id=? AND user_id=?");
      $spq->execute([(int)$l['scheduled_payment_id'], uid()]);
      $monthly_total = (float)($spq->fetchColumn() ?: 0.0); // this is TOTAL
    }
    if ($monthly_total <= 0) {
      // Estimate annuity P&I from term; then add insurance to get TOTAL
      $months = 0;
      if ($end) {
        $a = new DateTime($start); $b = new DateTime($end);
        $d = $a->diff($b);
        $months = $d->y * 12 + $d->m + ($d->d > 0 ? 1 : 0);
      }
      $PI = $months > 0 ? loan_monthly_payment($principal, $ratePct, $months) : 0.0;
      $monthly_total = $PI + $insurance;
    }

    $sumStmt->execute([(int)$l['id']]);
    $components = $sumStmt->fetch(PDO::FETCH_ASSOC) ?: ['principal_total' => 0.0, 'interest_total' => 0.0];
    $actualPrincipal = (float)$components['principal_total'];
    $actualInterest  = (float)$components['interest_total'];

    if (!empty($l['history_confirmed'])) {
      // Compute expected position up to today with bank-like rules
      $am = amortization_to_date_precise(
        $principal,
        $ratePct,
        $start,
        $end,
        $pday,
        $monthly_total,   // TOTAL
        $insurance,       // carve-out
        $extra,
        date('Y-m-d')
      );
      $l['_principal_paid'] = $am['principal_paid'];
      $l['_interest_paid']  = $am['interest_paid'];
      $l['_est_balance']    = $am['balance'];

      // If there are recorded payments, prefer the actual ledger figures.
      if ($actualPrincipal > 0.0 || $actualInterest > 0.0) {
        $l['_principal_paid'] = max($l['_principal_paid'], min($principal, $actualPrincipal));
        $l['_interest_paid']  = max($l['_interest_paid'], $actualInterest);

        $recordedBalance = $principal - $actualPrincipal;
        if (isset($l['balance']) && $l['balance'] !== null) {
          $recordedBalance = min($recordedBalance, (float)$l['balance']);
        }
        $l['_est_balance'] = max(0.0, min($l['_est_balance'], $recordedBalance));
      }
    } else {
      // Actual recorded payments drive progress when history is not confirmed.
      $l['_principal_paid'] = $actualPrincipal;
      $l['_interest_paid']  = $actualInterest > 0.0 ? $actualInterest : null;
      $l['_est_balance']    = max(0.0, $principal - $actualPrincipal);
    }

    $l['_progress_pct'] = $principal > 0 ? max(0, min(100, ($l['_principal_paid'] / $principal) * 100)) : 0;
    $l['_currency']     = $currency;

    $finishedAt = $l['finished_at'] ?? null;
    $archivedAt = $l['archived_at'] ?? null;
    $isPaidOff  = $l['_progress_pct'] >= 99.9 && $l['_est_balance'] <= 0.01;
    $isArchived = !empty($archivedAt) || (!empty($finishedAt) && $isPaidOff);

    if (!empty($finishedAt) || $isArchived) {
      // Once finished/archived, keep the loan locked in a completed state regardless of later edits.
      $l['_progress_pct'] = 100.0;
      $l['_est_balance']  = 0.0;
      $l['_is_paid_off']  = true;
      $l['_is_locked']    = true;
    } else {
      $l['_is_paid_off']  = $isPaidOff;
      $l['_is_locked']    = false;
    }

    $l['_is_archived'] = $isArchived;
  }
  unset($l);


  // currencies & schedules for form selects
  $uc = $pdo->prepare("SELECT code,is_main FROM user_currencies WHERE user_id=? ORDER BY is_main DESC, code");
  $uc->execute([$u]); $userCurrencies = $uc->fetchAll(PDO::FETCH_ASSOC) ?: [['code'=>'HUF','is_main'=>true]];
  $sp = $pdo->prepare("SELECT id,title,amount,currency,next_due,rrule FROM scheduled_payments WHERE user_id=? ORDER BY lower(title)");
  $sp->execute([$u]); 
  
  // Only schedules that are free (not linked to any loan or goal) OR already linked to this record
  $sp = $pdo->prepare("
    SELECT id, title, amount, currency, next_due, rrule, loan_id, goal_id
    FROM scheduled_payments
    WHERE user_id = ?
      AND archived_at IS NULL
      AND (loan_id IS NULL AND goal_id IS NULL) -- free ones
    ORDER BY lower(title)
  ");
  $sp->execute([$u]);
  $scheduledList = $sp->fetchAll(PDO::FETCH_ASSOC);

  // Recorded payments per loan (newest first)
  $lp = $pdo->prepare("SELECT lp.id, lp.loan_id, lp.paid_on, lp.amount, lp.principal_component,
                              lp.interest_component, lp.currency
                         FROM loan_payments lp
                         JOIN loans l ON l.id = lp.loan_id AND l.user_id = ?
                        ORDER BY lp.paid_on DESC, lp.id DESC");
  $lp->execute([$u]);
  $loanPayments = [];
  foreach ($lp->fetchAll(PDO::FETCH_ASSOC) as $pay) {
    $loanId = (int)($pay['loan_id'] ?? 0);
    if (!$loanId) { continue; }
    $loanPayments[$loanId][] = $pay;
  }


  $activeLoans = [];
  $archivedLoans = [];
  foreach ($rows as $loanRow) {
    if (!empty($loanRow['_is_archived']) || !empty($loanRow['_is_locked'])) {
      $archivedLoans[] = $loanRow;
    } else {
      $activeLoans[] = $loanRow;
    }
  }

  $allLoans = $rows; // preserve original order for modal generation/history access

  view('loans/index', compact('activeLoans','archivedLoans','allLoans','userCurrencies','scheduledList','loanPayments'));
}


/**
 * Months between two dates (ceil to include a partial last month when end > start).
 */
function months_between(string $start, ?string $end): int {
  if (!$end) return 0;
  $a = new DateTime($start); $b = new DateTime($end);
  $d = $a->diff($b);
  $m = $d->y * 12 + $d->m;
  if ($d->d > 0) $m += 1;
  return max(0, $m);
}

function loans_add(PDO $pdo){
  verify_csrf(); require_login(); $u=uid();

  $name        = trim($_POST['name'] ?? '');
  $principal   = (float)($_POST['principal'] ?? 0);
  $interest    = (float)($_POST['interest_rate'] ?? 0);
  $start_date  = $_POST['start_date'] ?? date('Y-m-d');
  $end_date    = $_POST['end_date']   ?: null;
  $payment_day = ($_POST['payment_day'] ?? '') !== '' ? (int)$_POST['payment_day'] : null;
  $extra       = (float)($_POST['extra_payment'] ?? 0);

  $loan_currency     = strtoupper(trim($_POST['loan_currency'] ?? 'HUF'));
  $insurance_monthly = (float)($_POST['insurance_monthly'] ?? 0);
  $history_confirmed = !empty($_POST['history_confirmed']);

  // schedule choices…
  $scheduled_payment_id = ($_POST['scheduled_payment_id'] ?? '') !== '' ? (int)$_POST['scheduled_payment_id'] : null;
  $autoCreateSched      = !empty($_POST['create_schedule']);
  $first_due            = $_POST['first_due'] ?: $start_date;
  $due_day              = (int)($_POST['due_day'] ?? ($payment_day ?: (int)date('j', strtotime($first_due))));
  $sched_currency       = strtoupper(trim($_POST['currency'] ?? $loan_currency));

  $months = months_between($start_date, $end_date);
  $monthly_amount = isset($_POST['monthly_amount']) && $_POST['monthly_amount'] !== ''
    ? (float)$_POST['monthly_amount']
    : ($months > 0 ? loan_monthly_payment($principal, $interest, $months) + $insurance_monthly : 0.0);

  if ($name === '' || $principal <= 0) { $_SESSION['flash']='Name & principal required.'; redirect('/loans'); }

  $pdo->beginTransaction();
  try {
    $ins = $pdo->prepare("
      INSERT INTO loans(user_id,name,principal,currency,interest_rate,start_date,end_date,payment_day,extra_payment,insurance_monthly,history_confirmed,balance)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?) RETURNING id
    ");
    $ins->execute([$u,$name,$principal,$loan_currency,$interest,$start_date,$end_date,$payment_day,$extra,$insurance_monthly,$history_confirmed,$principal]);
    $loanId = (int)$ins->fetchColumn();

    if ($scheduled_payment_id) {
      $pdo->prepare("UPDATE loans SET scheduled_payment_id=? WHERE id=? AND user_id=?")->execute([$scheduled_payment_id,$loanId,$u]);
      $pdo->prepare("UPDATE scheduled_payments SET loan_id=? WHERE id=? AND user_id=?")->execute([$loanId,$scheduled_payment_id,$u]);
    } elseif ($autoCreateSched) {
      $bymd  = max(1, min(31, $due_day));
      $rrule = "FREQ=MONTHLY;BYMONTHDAY={$bymd}";
      if ($months > 0) $rrule .= ";COUNT=".$months;

      $sp = $pdo->prepare("INSERT INTO scheduled_payments(user_id,title,amount,currency,next_due,rrule,category_id,loan_id)
                           VALUES (?,?,?,?,?,?,NULL,?) RETURNING id");
      $sp->execute([$u, "Loan: ".$name, $monthly_amount, $sched_currency, $first_due, $rrule, $loanId]);
      $newSpId = (int)$sp->fetchColumn();
      $pdo->prepare("UPDATE loans SET scheduled_payment_id=? WHERE id=? AND user_id=?")->execute([$newSpId,$loanId,$u]);
    }

    $pdo->commit();
    $_SESSION['flash']='Loan saved.';
  } catch (Throwable $e) {
    $pdo->rollBack();
    $_SESSION['flash']='Failed to save loan.';
  }
  redirect('/loans');
}

// --- loans_edit ---
function loans_edit(PDO $pdo){
  verify_csrf(); require_login(); $u = uid();

  $id = (int)($_POST['id'] ?? 0);
  if (!$id) { redirect('/loans'); }

  $metaStmt = $pdo->prepare('SELECT finished_at, archived_at FROM loans WHERE id=? AND user_id=?');
  $metaStmt->execute([$id, $u]);
  $metaRow = $metaStmt->fetch(PDO::FETCH_ASSOC);
  if (!$metaRow) {
    redirect('/loans');
  }
  if (!empty($metaRow['archived_at']) || !empty($metaRow['finished_at'])) {
    $_SESSION['flash'] = 'This loan has been archived and can no longer be edited.';
    redirect('/loans');
    return;
  }

  // --- Loan fields ---
  $name        = trim($_POST['name'] ?? '');
  $principal   = (float)($_POST['principal'] ?? 0);
  $interest    = (float)($_POST['interest_rate'] ?? 0);
  $start_date  = $_POST['start_date'] ?? date('Y-m-d');
  $end_date    = $_POST['end_date']   ?: null;
  $payment_day = ($_POST['payment_day'] ?? '') !== '' ? (int)$_POST['payment_day'] : null;
  $extra       = (float)($_POST['extra_payment'] ?? 0);

  $loan_currency     = strtoupper(trim($_POST['loan_currency'] ?? 'HUF'));
  $insurance_monthly = (float)($_POST['insurance_monthly'] ?? 0);
  $history_confirmed = !empty($_POST['history_confirmed']);

  // --- Scheduling choices ---
  $scheduled_payment_id = ($_POST['scheduled_payment_id'] ?? '') !== '' ? (int)$_POST['scheduled_payment_id'] : null;
  $autoCreateSched      = !empty($_POST['create_schedule']);
  $first_due            = $_POST['first_due'] ?: $start_date;
  $due_day              = (int)($_POST['due_day'] ?? ($payment_day ?: (int)date('j', strtotime($first_due))));
  $sched_currency       = strtoupper(trim($_POST['currency'] ?? $loan_currency));
  $unlink_schedule      = !empty($_POST['unlink_schedule']);

  // Monthly amount for a *new* schedule
  $months = months_between($start_date, $end_date);
  $monthly_amount = isset($_POST['monthly_amount']) && $_POST['monthly_amount'] !== ''
    ? (float)$_POST['monthly_amount']
    : ($months > 0 ? loan_monthly_payment($principal, $interest, $months) + $insurance_monthly : 0.0);

  $pdo->beginTransaction();
  try {
    // 1) Update main loan record
    $pdo->prepare("
      UPDATE loans
         SET name               = ?,
             principal          = ?,
             currency           = ?,
             interest_rate      = ?,
             start_date         = ?,
             end_date           = ?,
             payment_day        = ?,
             extra_payment      = ?,
             insurance_monthly  = ?,
             history_confirmed  = ?
       WHERE id = ? AND user_id = ?
    ")->execute([
      $name, $principal, $loan_currency, $interest,
      $start_date, $end_date, $payment_day, $extra,
      $insurance_monthly, $history_confirmed, $id, $u
    ]);

    // 2) Handle schedule linking / unlinking / auto-create
    if ($unlink_schedule) {
      // Explicit unlink
      $pdo->prepare("UPDATE scheduled_payments SET loan_id=NULL WHERE loan_id=? AND user_id=?")
          ->execute([$id,$u]);
      $pdo->prepare("UPDATE loans SET scheduled_payment_id=NULL WHERE id=? AND user_id=?")
          ->execute([$id,$u]);
    } elseif ($scheduled_payment_id) {
      // Link existing
      $pdo->prepare("UPDATE loans SET scheduled_payment_id=? WHERE id=? AND user_id=?")
          ->execute([$scheduled_payment_id,$id,$u]);
      $pdo->prepare("UPDATE scheduled_payments SET loan_id=? WHERE id=? AND user_id=?")
          ->execute([$id,$scheduled_payment_id,$u]);
    } elseif ($autoCreateSched) {
      // Create a fresh monthly schedule for this loan
      $bymd  = max(1, min(31, $due_day));
      $rrule = "FREQ=MONTHLY;BYMONTHDAY={$bymd}";
      if ($months > 0) $rrule .= ";COUNT=".$months;

      $sp = $pdo->prepare("
        INSERT INTO scheduled_payments(user_id, title, amount, currency, next_due, rrule, category_id, loan_id)
        VALUES (?,?,?,?,?,?,NULL,?) RETURNING id
      ");
      $sp->execute([$u, "Loan: ".$name, $monthly_amount, $sched_currency, $first_due, $rrule, $id]);
      $newSpId = (int)$sp->fetchColumn();

      $pdo->prepare("UPDATE loans SET scheduled_payment_id=? WHERE id=? AND user_id=?")
          ->execute([$newSpId,$id,$u]);
    }
    // else: keep existing link as-is

    $pdo->commit();
    $_SESSION['flash'] = 'Loan updated.';
  } catch (Throwable $e) {
    $pdo->rollBack();
    $_SESSION['flash'] = 'Failed to update loan.';
    // throw $e; // uncomment while debugging locally
  }

  redirect('/loans');
}



function loans_delete(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $pdo->prepare('DELETE FROM loans WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$u]);
}

function loan_payment_add(PDO $pdo){
  verify_csrf(); require_login(); $u = uid();

  $loanId   = (int)($_POST['loan_id'] ?? 0);
  $amount   = max(0.0, (float)($_POST['amount'] ?? 0));
  $interest = max(0.0, (float)($_POST['interest_component'] ?? 0));
  $paidOn   = $_POST['paid_on'] ?: date('Y-m-d');

  if ($loanId <= 0 || $amount <= 0.0) {
    $_SESSION['flash'] = 'Payment amount is required.';
    redirect('/loans');
    return;
  }

  if ($interest > $amount) { $interest = $amount; }
  $principal = max(0.0, $amount - $interest);

  $loanMeta = $pdo->prepare('
    SELECT l.name, l.balance, l.principal, l.currency, l.scheduled_payment_id, l.finished_at, l.archived_at,
            sp.category_id AS sched_category_id
       FROM loans l
      LEFT JOIN scheduled_payments sp
             ON sp.id = l.scheduled_payment_id AND sp.user_id = l.user_id
     WHERE l.id = ? AND l.user_id = ?
  ');
  $loanMeta->execute([$loanId, $u]);
  $loanRow = $loanMeta->fetch(PDO::FETCH_ASSOC);
  if (!$loanRow) {
    $_SESSION['flash'] = 'Loan not found.';
    redirect('/loans');
    return;
  }

  if (!empty($loanRow['archived_at']) || !empty($loanRow['finished_at'])) {
    $_SESSION['flash'] = 'This loan has already been archived and cannot accept new payments.';
    redirect('/loans');
    return;
  }

  $previousBalance = max(0.0, (float)($loanRow['balance'] ?? $loanRow['principal'] ?? 0.0));
  $currency = strtoupper(trim((string)($_POST['currency'] ?? '')));
  if ($currency === '') {
    $currency = strtoupper((string)($loanRow['currency'] ?? 'HUF'));
  }

  $categoryId = (int)($loanRow['sched_category_id'] ?? 0);
  if ($categoryId <= 0) { $categoryId = null; }

  $transactionId = null;

  $pdo->beginTransaction();
  try {
    $loanName = trim((string)$loanRow['name']);
    $note = $loanName !== '' ? ('Loan payment · ' . $loanName) : 'Loan payment';
    $insTx = $pdo->prepare('INSERT INTO transactions(user_id,kind,category_id,amount,currency,occurred_on,note) VALUES(?,?,?,?,?,?,?) RETURNING id');
    $insTx->execute([$u, 'spending', $categoryId, $amount, $currency, $paidOn, $note]);
    $transactionId = (int)$insTx->fetchColumn();

    $pdo->prepare('INSERT INTO loan_payments(loan_id,paid_on,amount,principal_component,interest_component,currency,transaction_id) VALUES(?,?,?,?,?,?,?)')
        ->execute([$loanId, $paidOn, $amount, $principal, $interest, $currency, $transactionId ?: null]);

    $pdo->prepare('UPDATE loans SET balance = GREATEST(0,balance-?) WHERE id=? AND user_id=?')
        ->execute([$principal, $loanId, $u]);

    $pdo->commit();
  } catch (Throwable $e) {
    $pdo->rollBack();
    $_SESSION['flash'] = 'Could not record payment.';
    redirect('/loans');
    return;
  }

  if ($transactionId && $categoryId) {
    try {
      if (!function_exists('tx_maybe_send_cashflow_overspend')) {
        require_once __DIR__ . '/transactions.php';
      }
      tx_maybe_send_cashflow_overspend($pdo, $u, $categoryId, $amount, $currency, $paidOn, null);
    } catch (Throwable $mailError) {
      error_log('Cashflow overspend email failed after loan payment insert for user ' . $u . ': ' . $mailError->getMessage());
    }
  }

  $_SESSION['flash'] = 'Payment recorded.';
  loan_maybe_handle_completion($pdo, $u, $loanId, $previousBalance);
  redirect('/loans');
}

function loan_payment_update(PDO $pdo){ verify_csrf(); require_login(); $u = uid();
  $id = (int)($_POST['id'] ?? 0);
  $amount = (float)($_POST['amount'] ?? 0);
  $interest = max(0.0, (float)($_POST['interest_component'] ?? 0));
  $paid_on = $_POST['paid_on'] ?: date('Y-m-d');
  $currency = strtoupper(trim($_POST['currency'] ?? ''));

  if (!$id || $amount <= 0 || !$paid_on) {
    $_SESSION['flash'] = 'Payment amount and date are required.';
    redirect('/loans');
  }

  $q = $pdo->prepare('SELECT lp.*, l.user_id, l.currency AS loan_currency, l.id AS loan_id,
                         l.name AS loan_name, l.balance, l.principal, l.scheduled_payment_id,
                         l.finished_at, l.archived_at,
                         sp.category_id AS sched_category_id
                        FROM loan_payments lp
                        JOIN loans l ON l.id = lp.loan_id
                        LEFT JOIN scheduled_payments sp
                               ON sp.id = l.scheduled_payment_id AND sp.user_id = l.user_id
                       WHERE lp.id=? AND l.user_id=?');
  $q->execute([$id,$u]);
  $row = $q->fetch(PDO::FETCH_ASSOC);
  if (!$row) { redirect('/loans'); }

  if (!empty($row['archived_at']) || !empty($row['finished_at'])) {
    $_SESSION['flash'] = 'This loan has been archived and payments are locked.';
    redirect('/loans');
    return;
  }

  if ($currency === '') {
    $currency = $row['currency'] ?: ($row['loan_currency'] ?: 'HUF');
  }

  if ($interest > $amount) {
    $interest = $amount;
  }
  $principal = max(0.0, $amount - $interest);

  $previousBalance = max(0.0, (float)($row['balance'] ?? $row['principal'] ?? 0.0));
  $transactionId = (int)($row['transaction_id'] ?? 0);

  $previousTx = null;
  if ($transactionId > 0) {
    $prevStmt = $pdo->prepare('SELECT kind, category_id, amount, currency, occurred_on FROM transactions WHERE id=? AND user_id=?');
    $prevStmt->execute([$transactionId, $u]);
    $previousTx = $prevStmt->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  $categoryId = (int)($row['sched_category_id'] ?? 0);
  if ($categoryId <= 0 && $previousTx) {
    $categoryId = (int)($previousTx['category_id'] ?? 0);
  }
  if ($categoryId <= 0) { $categoryId = null; }

  $pdo->beginTransaction();
  try {
    $pdo->prepare('UPDATE loan_payments
                      SET paid_on=?, amount=?, interest_component=?, principal_component=?, currency=?
                    WHERE id=?')
        ->execute([$paid_on,$amount,$interest,$principal,$currency,$id]);

    $pdo->prepare('UPDATE loans SET balance = GREATEST(0, COALESCE(balance,0) + ? - ?)
                    WHERE id=? AND user_id=?')
        ->execute([(float)$row['principal_component'],$principal,(int)$row['loan_id'],$u]);

    if ($transactionId > 0) {
      $pdo->prepare('UPDATE transactions SET amount=?, currency=?, occurred_on=?, updated_at=NOW()
                      WHERE id=? AND user_id=?')
          ->execute([$amount, $currency, $paid_on, $transactionId, $u]);
    } else {
      $loanName = trim((string)$row['loan_name']);
      $note = $loanName !== '' ? ('Loan payment · ' . $loanName) : 'Loan payment';
      $insTx = $pdo->prepare('INSERT INTO transactions(user_id,kind,category_id,amount,currency,occurred_on,note) VALUES(?,?,?,?,?,?,?) RETURNING id');
      $insTx->execute([$u, 'spending', $categoryId, $amount, $currency, $paid_on, $note]);
      $transactionId = (int)$insTx->fetchColumn();
      $pdo->prepare('UPDATE loan_payments SET transaction_id=? WHERE id=?')
          ->execute([$transactionId, $id]);
    }

    $pdo->commit();
    $_SESSION['flash'] = 'Payment updated.';
    loan_maybe_handle_completion($pdo, $u, (int)$row['loan_id'], $previousBalance);
  } catch (Throwable $e) {
    $pdo->rollBack();
    $_SESSION['flash'] = 'Could not update payment.';
    redirect('/loans');
    return;
  }

  if ($transactionId && ($categoryId || ($previousTx && (int)($previousTx['category_id'] ?? 0) > 0))) {
    $alertCategory = $categoryId ?: (int)($previousTx['category_id'] ?? 0);
    if ($alertCategory > 0) {
      try {
        if (!function_exists('tx_maybe_send_cashflow_overspend')) {
          require_once __DIR__ . '/transactions.php';
        }
        tx_maybe_send_cashflow_overspend($pdo, $u, $alertCategory, $amount, $currency, $paid_on, $previousTx);
      } catch (Throwable $mailError) {
        error_log('Cashflow overspend email failed after loan payment update for user ' . $u . ': ' . $mailError->getMessage());
      }
    }
  }

  redirect('/loans');
}

function loan_payment_delete(PDO $pdo){ verify_csrf(); require_login(); $u = uid();
  $id = (int)($_POST['id'] ?? 0);
  if (!$id) { redirect('/loans'); }

  $q = $pdo->prepare('SELECT lp.*, l.user_id, l.id AS loan_id, l.finished_at, l.archived_at
                        FROM loan_payments lp
                        JOIN loans l ON l.id = lp.loan_id
                       WHERE lp.id=? AND l.user_id=?');
  $q->execute([$id,$u]);
  $row = $q->fetch(PDO::FETCH_ASSOC);
  if (!$row) { redirect('/loans'); }

  if (!empty($row['archived_at']) || !empty($row['finished_at'])) {
    $_SESSION['flash'] = 'This loan has been archived and payments are locked.';
    redirect('/loans');
    return;
  }

  $pdo->beginTransaction();
  try {
    $pdo->prepare('DELETE FROM loan_payments WHERE id=?')->execute([$id]);
    $pdo->prepare('UPDATE loans SET balance = GREATEST(0, COALESCE(balance,0) + ?)
                    WHERE id=? AND user_id=?')
        ->execute([(float)$row['principal_component'], (int)$row['loan_id'], $u]);
    if (!empty($row['transaction_id'])) {
      $pdo->prepare('DELETE FROM transactions WHERE id=? AND user_id=?')->execute([(int)$row['transaction_id'], $u]);
    }
    $pdo->commit();
    $_SESSION['flash'] = 'Payment removed.';
  } catch (Throwable $e) {
    $pdo->rollBack();
    $_SESSION['flash'] = 'Could not remove payment.';
    redirect('/loans');
    return;
  }

  redirect('/loans');
}

function loan_monthly_payment(float $principal, float $annualRatePct, int $months): float {
  $r = ($annualRatePct/100.0) / 12.0;           // monthly rate
  if ($months <= 0) return 0.0;
  if (abs($r) < 1e-10) return $principal / $months; // zero-rate
  return $principal * ($r * pow(1+$r, $months)) / (pow(1+$r, $months) - 1);
}

/**
 * Compute amortization up to $asOf using bank-like rules:
 * - First payment is the first cycle AFTER start_date, aligned to payment_day.
 * - Payment applied each due date where due_date <= asOf (and <= end_date if set).
 * - payment_total = (principal+interest payment) + insurance_monthly
 *   We only allocate payment_total - insurance_monthly to interest/principal.
 * - extra_payment (if any) reduces principal each month after interest allocation.
 */
function amortization_to_date_precise(
  float $principal,
  float $annualRatePct,
  string $start_date,          // loan start
  ?string $end_date,           // loan end (maturity) or null
  ?int $payment_day,           // 1..31 or null -> use start day
  float $payment_total,        // TOTAL debited per month (P&I + insurance)
  float $insurance_monthly = 0.0, // excluded from progress (not applied to P&I)
  float $extra_payment = 0.0,  // principal-only extra per month
  ?string $asOfDate = null
): array {
  $asOf = new DateTime($asOfDate ?: date('Y-m-d'));
  $start = new DateTime($start_date);
  $maturity = $end_date ? new DateTime($end_date) : null;

  // Determine FIRST DUE DATE: next cycle after start_date, aligned to payment_day
  $day = $payment_day ?: (int)$start->format('j');
  $firstMonth = (clone $start);
  // anchor to "this month - desired day"
  $firstMonth->setDate((int)$firstMonth->format('Y'), (int)$firstMonth->format('n'), min($day, (int)$firstMonth->format('t')));
  if ($firstMonth <= $start) {
    // if start is on/after that day, first due is next month same day (clamped)
    $firstMonth->modify('first day of next month');
    $firstMonth->setDate((int)$firstMonth->format('Y'), (int)$firstMonth->format('n'), min($day, (int)$firstMonth->format('t')));
  } else {
    // if start is before that day, still first due is this month’s target day
    // BUT banks typically charge first due the NEXT cycle → force next month
    $firstMonth->modify('first day of next month');
    $firstMonth->setDate((int)$firstMonth->format('Y'), (int)$firstMonth->format('n'), min($day, (int)$firstMonth->format('t')));
  }

  $bal = $principal;
  $r = ($annualRatePct / 100.0) / 12.0;       // monthly rate
  $ppaid = 0.0; $ipaid = 0.0; $n = 0;

  // Only P&I portion goes to amortization; insurance is carved out
  $pmt_PI = max(0.0, $payment_total - $insurance_monthly);

  // Iterate each due date up to asOf (and up to maturity if provided)
  $due = $firstMonth;
  while ($bal > 1e-6 && $due <= $asOf) {
    if ($maturity && $due > $maturity) break;

    $interest = $r > 0 ? $bal * $r : 0.0;

    // total available to principal this month
    $toward_principal = max(0.0, $pmt_PI + $extra_payment - $interest);

    if ($toward_principal <= 0 && $r > 0) {
      // payment too small to cover interest → avoid infinite loop
      break;
    }

    $principal_part = min($bal, $toward_principal);
    $bal -= $principal_part;

    // record paid
    $ipaid += min($interest, $pmt_PI + $extra_payment);
    $ppaid += $principal_part;
    $n++;

    // next due date: next month on the payment_day (clamp to month length)
    $due = (clone $due)->modify('first day of next month');
    $due->setDate((int)$due->format('Y'), (int)$due->format('n'), min($day, (int)$due->format('t')));
  }

  return [
    'principal_paid' => $ppaid,
    'interest_paid'  => $ipaid,
    'balance'        => max(0.0, $bal),
    'months_counted' => $n,
    'first_due'      => $firstMonth->format('Y-m-d'),
    'last_counted'   => $n ? (clone $due)->modify('-1 month')->format('Y-m-d') : null,
  ];
}

// src/controllers/goals.php
function goals_unlink_schedule(PDO $pdo){
  verify_csrf(); require_login();
  $u = uid();
  $goalId = (int)($_POST['goal_id'] ?? 0);
  if (!$goalId){ redirect('/goals'); }

  $pdo->beginTransaction();
  try {
    // Clear any scheduled payments pointing to this goal
    $pdo->prepare("UPDATE scheduled_payments SET goal_id=NULL WHERE user_id=? AND goal_id=?")
        ->execute([$u, $goalId]);
    $pdo->commit();
    $_SESSION['flash'] = 'Schedule unlinked.';
  } catch(Throwable $e){
    $pdo->rollBack();
    $_SESSION['flash'] = 'Failed to unlink schedule.';
  }
  redirect('/goals');
}
