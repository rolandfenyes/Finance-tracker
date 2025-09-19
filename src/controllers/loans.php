<?php
require_once __DIR__ . '/../helpers.php';

function loans_index(PDO $pdo){
  require_login(); $u=uid();

  // loans_index()
  $q = $pdo->prepare("
    SELECT l.*, sp.title AS sched_title, sp.rrule AS sched_rrule, sp.next_due AS sched_next_due
    FROM loans l
    LEFT JOIN scheduled_payments sp ON sp.id=l.scheduled_payment_id AND sp.user_id=l.user_id
    WHERE l.user_id=?
    ORDER BY l.start_date DESC, l.id DESC
  ");


  $q->execute([$u]);
  $rows = $q->fetchAll();

  // currencies for selects
  $uc = $pdo->prepare("SELECT code,is_main FROM user_currencies WHERE user_id=? ORDER BY is_main DESC, code");
  $uc->execute([$u]); $userCurrencies = $uc->fetchAll(PDO::FETCH_ASSOC) ?: [['code'=>'HUF','is_main'=>true]];

  // existing scheduled payments to pick from
  $sp = $pdo->prepare("SELECT id,title,amount,currency,next_due,rrule FROM scheduled_payments WHERE user_id=? ORDER BY lower(title)");
  $sp->execute([$u]); $scheduledList = $sp->fetchAll();

  view('loans/index', compact('rows','userCurrencies','scheduledList'));
}

// helper: months between (ceil)
function months_between(string $from, ?string $to): int {
  if (!$to) return 0;
  $a = new DateTime($from); $b = new DateTime($to);
  $inv = $a->diff($b);
  $m = $inv->y * 12 + $inv->m;
  // if there are leftover days, count it as another month
  if ($inv->d > 0) $m++;
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

  // NEW: loan currency
  $loan_currency = strtoupper(trim($_POST['loan_currency'] ?? 'HUF'));

  // schedule choices
  $scheduled_payment_id = ($_POST['scheduled_payment_id'] ?? '') !== '' ? (int)$_POST['scheduled_payment_id'] : null;
  $autoCreateSched      = !empty($_POST['create_schedule']);
  $first_due            = $_POST['first_due'] ?: $start_date;
  $due_day              = (int)($_POST['due_day'] ?? ($payment_day ?: (int)date('j', strtotime($first_due))));
  // schedule currency defaults to loan currency
  $sched_currency       = strtoupper(trim($_POST['currency'] ?? $loan_currency));

  $months = months_between($start_date, $end_date);
  $monthly_amount = isset($_POST['monthly_amount']) && $_POST['monthly_amount'] !== ''
    ? (float)$_POST['monthly_amount']
    : ($months > 0 ? loan_monthly_payment($principal, $interest, $months) : 0.0);

  if ($name === '' || $principal <= 0) { $_SESSION['flash']='Name & principal required.'; redirect('/loans'); }

  $pdo->beginTransaction();
  try {
    // INSERT includes currency now
    $ins = $pdo->prepare("
      INSERT INTO loans(user_id, name, principal, currency, interest_rate, start_date, end_date, payment_day, extra_payment, balance)
      VALUES (?,?,?,?,?,?,?,?,?,?) RETURNING id
    ");
    $ins->execute([$u, $name, $principal, $loan_currency, $interest, $start_date, $end_date, $payment_day, $extra, $principal]);
    $loanId = (int)$ins->fetchColumn();

    if ($scheduled_payment_id) {
      $pdo->prepare("UPDATE loans SET scheduled_payment_id=? WHERE id=? AND user_id=?")
          ->execute([$scheduled_payment_id,$loanId,$u]);
      $pdo->prepare("UPDATE scheduled_payments SET loan_id=? WHERE id=? AND user_id=?")
          ->execute([$loanId,$scheduled_payment_id,$u]);
    } elseif ($autoCreateSched) {
      $bymd  = max(1, min(31, $due_day));
      $rrule = "FREQ=MONTHLY;BYMONTHDAY={$bymd}";
      if ($months > 0) $rrule .= ";COUNT=".$months;

      $sp = $pdo->prepare("
        INSERT INTO scheduled_payments(user_id, title, amount, currency, next_due, rrule, category_id, loan_id)
        VALUES (?,?,?,?,?,?,NULL,?) RETURNING id
      ");
      $sp->execute([$u, "Loan: ".$name, $monthly_amount, $sched_currency, $first_due, $rrule, $loanId]);
      $newSpId = (int)$sp->fetchColumn();
      $pdo->prepare("UPDATE loans SET scheduled_payment_id=? WHERE id=? AND user_id=?")->execute([$newSpId,$loanId,$u]);
    }

    $pdo->commit();
    $_SESSION['flash'] = 'Loan saved.';
  } catch (Throwable $e) {
    $pdo->rollBack();
    $_SESSION['flash'] = 'Failed to save loan.';
  }
  redirect('/loans');
}

// --- loans_edit ---
function loans_edit(PDO $pdo){
  verify_csrf(); require_login(); $u=uid();
  $id = (int)($_POST['id'] ?? 0); if(!$id) redirect('/loans');

  $name        = trim($_POST['name'] ?? '');
  $principal   = (float)($_POST['principal'] ?? 0);
  $interest    = (float)($_POST['interest_rate'] ?? 0);
  $start_date  = $_POST['start_date'] ?? date('Y-m-d');
  $end_date    = $_POST['end_date']   ?: null;
  $payment_day = ($_POST['payment_day'] ?? '') !== '' ? (int)$_POST['payment_day'] : null;
  $extra       = (float)($_POST['extra_payment'] ?? 0);
  $loan_currency = strtoupper(trim($_POST['loan_currency'] ?? 'HUF'));

  $scheduled_payment_id = ($_POST['scheduled_payment_id'] ?? '') !== '' ? (int)$_POST['scheduled_payment_id'] : null;
  $autoCreateSched      = !empty($_POST['create_schedule']);
  $first_due            = $_POST['first_due'] ?: $start_date;
  $due_day              = (int)($_POST['due_day'] ?? ($payment_day ?: (int)date('j', strtotime($first_due))));
  $sched_currency       = strtoupper(trim($_POST['currency'] ?? $loan_currency));

  $months = months_between($start_date, $end_date);
  $monthly_amount = isset($_POST['monthly_amount']) && $_POST['monthly_amount'] !== ''
    ? (float)$_POST['monthly_amount']
    : ($months > 0 ? loan_monthly_payment($principal, $interest, $months) : 0.0);

  $pdo->beginTransaction();
  try {
    $pdo->prepare("
      UPDATE loans
         SET name=?, principal=?, currency=?, interest_rate=?, start_date=?, end_date=?, payment_day=?, extra_payment=?
       WHERE id=? AND user_id=?
    ")->execute([$name,$principal,$loan_currency,$interest,$start_date,$end_date,$payment_day,$extra,$id,$u]);

    if ($scheduled_payment_id) {
      $pdo->prepare("UPDATE loans SET scheduled_payment_id=? WHERE id=? AND user_id=?")->execute([$scheduled_payment_id,$id,$u]);
      $pdo->prepare("UPDATE scheduled_payments SET loan_id=? WHERE id=? AND user_id=?")->execute([$id,$scheduled_payment_id,$u]);
    } elseif ($autoCreateSched) {
      $bymd  = max(1, min(31, $due_day));
      $rrule = "FREQ=MONTHLY;BYMONTHDAY={$bymd}";
      if ($months > 0) $rrule .= ";COUNT=".$months;

      $sp = $pdo->prepare("
        INSERT INTO scheduled_payments(user_id, title, amount, currency, next_due, rrule, category_id, loan_id)
        VALUES (?,?,?,?,?,?,NULL,?) RETURNING id
      ");
      $sp->execute([$u, "Loan: ".$name, $monthly_amount, $sched_currency, $first_due, $rrule, $id]);
      $newSpId = (int)$sp->fetchColumn();
      $pdo->prepare("UPDATE loans SET scheduled_payment_id=? WHERE id=? AND user_id=?")->execute([$newSpId,$id,$u]);
    } else {
      if (!empty($_POST['unlink_schedule'])) {
        $pdo->prepare("UPDATE scheduled_payments SET loan_id=NULL WHERE loan_id=? AND user_id=?")->execute([$id,$u]);
        $pdo->prepare("UPDATE loans SET scheduled_payment_id=NULL WHERE id=? AND user_id=?")->execute([$id,$u]);
      }
    }

    $pdo->commit();
    $_SESSION['flash']='Loan updated.';
  } catch (Throwable $e) {
    $pdo->rollBack();
    $_SESSION['flash']='Failed to update loan.';
  }
  redirect('/loans');
}


function loans_delete(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $pdo->prepare('DELETE FROM loans WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$u]);
}

function loan_payment_add(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $loanId=(int)$_POST['loan_id'];
  $amount=(float)$_POST['amount']; $interest=(float)($_POST['interest_component']??0); $principal=max(0,$amount-$interest);
  $pdo->prepare('INSERT INTO loan_payments(loan_id,paid_on,amount,principal_component,interest_component,currency) VALUES(?,?,?,?,?,?)')
      ->execute([$loanId, $_POST['paid_on'], $amount, $principal, $interest, $_POST['currency']??'HUF']);
  $pdo->prepare('UPDATE loans SET balance = GREATEST(0,balance-?) WHERE id=? AND user_id=?')->execute([$principal,$loanId,$u]);
}

function loan_monthly_payment(float $principal, float $annualRatePct, int $months): float {
  $r = ($annualRatePct/100.0) / 12.0;           // monthly rate
  if ($months <= 0) return 0.0;
  if (abs($r) < 1e-10) return $principal / $months; // zero-rate
  return $principal * ($r * pow(1+$r, $months)) / (pow(1+$r, $months) - 1);
}
