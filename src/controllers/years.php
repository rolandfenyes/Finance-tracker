<?php
require_once __DIR__ . '/../helpers.php';

function years_index(PDO $pdo){
  require_login(); $u=uid();
  // Determine min/max year from transactions & loan_payments & basic_incomes
  $q = $pdo->prepare("SELECT MIN(EXTRACT(YEAR FROM d))::int AS y_min, MAX(EXTRACT(YEAR FROM d))::int AS y_max FROM (
      SELECT occurred_on AS d FROM transactions WHERE user_id=?
      UNION ALL SELECT paid_on FROM loan_payments lp JOIN loans l ON l.id=lp.loan_id AND l.user_id=?
      UNION ALL SELECT valid_from FROM basic_incomes WHERE user_id=?
  ) s");
  $q->execute([$u,$u,$u]); $row=$q->fetch();
  $ymin = $row && $row['y_min'] ? (int)$row['y_min'] : (int)date('Y');
  $ymax = $row && $row['y_max'] ? (int)$row['y_max'] : (int)date('Y');
  // Build yearly aggregates
  $agg=$pdo->prepare("SELECT EXTRACT(YEAR FROM occurred_on)::int y,
     SUM(CASE WHEN kind='income' THEN amount ELSE 0 END) income,
     SUM(CASE WHEN kind='spending' THEN amount ELSE 0 END) spending
     FROM transactions WHERE user_id=? GROUP BY y ORDER BY y DESC");
  $agg->execute([$u]); $byYear=[]; foreach($agg as $r){ $byYear[(int)$r['y']]=$r; }
  view('years/index', compact('ymin','ymax','byYear'));
}

function year_detail(PDO $pdo, int $year){
  require_login(); $u=uid();
  // Monthly sums for the year
  $q=$pdo->prepare("SELECT EXTRACT(MONTH FROM occurred_on)::int m,
     SUM(CASE WHEN kind='income' THEN amount ELSE 0 END) income,
     SUM(CASE WHEN kind='spending' THEN amount ELSE 0 END) spending
     FROM transactions WHERE user_id=? AND EXTRACT(YEAR FROM occurred_on)=? GROUP BY m ORDER BY m");
  $q->execute([$u,$year]); $rows = $q->fetchAll();
  // Map 1..12
  $byMonth = array_fill(1,12,['income'=>0,'spending'=>0]);
  foreach($rows as $r){ $byMonth[(int)$r['m']] = ['income'=>(float)$r['income'],'spending'=>(float)$r['spending']]; }
  view('years/year', compact('year','byMonth'));
}

function month_detail(PDO $pdo, int $year, int $month){
  require_once __DIR__ . '/../fx.php';
  require_login(); $u=uid();

  // Transactions for month
  $tx=$pdo->prepare("SELECT t.*, c.label AS cat_label FROM transactions t
    LEFT JOIN categories c ON c.id=t.category_id
    WHERE t.user_id=? AND EXTRACT(YEAR FROM occurred_on)=? AND EXTRACT(MONTH FROM occurred_on)=?
    ORDER BY occurred_on DESC, id DESC");
  $tx->execute([$u,$year,$month]); $tx=$tx->fetchAll();
  
    // month boundaries
  $first = sprintf('%04d-%02d-01', $year, $month);
  $last  = date('Y-m-t', strtotime($first));
  $main  = fx_user_main($pdo, $u);

  // Transactions for month
  $tx=$pdo->prepare("SELECT t.*, c.label AS cat_label
    FROM transactions t
    LEFT JOIN categories c ON c.id=t.category_id AND c.user_id=t.user_id
    WHERE t.user_id=? AND t.occurred_on BETWEEN ?::date AND ?::date
    ORDER BY t.occurred_on DESC, t.id DESC");
  $tx->execute([$u,$first,$last]); $tx=$tx->fetchAll();

  $sumIn_native=0; $sumOut_native=0; $sumIn_main=0; $sumOut_main=0;
  foreach($bi as $b){
      $amt = (float)$b['amount'];
      $cur = strtoupper($b['currency'] ?? $main);   // <— enforce
      $sumIn_native += $amt;
      $sumIn_main   += fx_convert_basic_income($pdo, $amt, $cur, $main, $y, $m);
  }
  // basic incomes
  $bi=$pdo->prepare("SELECT amount,currency FROM basic_incomes WHERE user_id=? AND valid_from<=?::date AND (valid_to IS NULL OR valid_to>=?::date)");
  $bi->execute([$u,$last,$first]); foreach($bi as $b){ $sumIn_native+=(float)$b['amount']; $sumIn_main+=fx_convert_basic_income($pdo,(float)$b['amount'],$b['currency']?:$main,$main,$year,$month); }


  // Goals snapshot
  $g=$pdo->prepare("SELECT SUM(current_amount) c, SUM(target_amount) t FROM goals WHERE user_id=? AND status='active'");
  $g->execute([$u]); $g=$g->fetch();

  // Emergency fund snapshot
  $e=$pdo->prepare('SELECT total,target_amount,currency FROM emergency_fund WHERE user_id=?');
  $e->execute([$u]); $e=$e->fetch();

  // Scheduled payments due in that month
  $sp=$pdo->prepare("SELECT id,title,amount,currency,next_due FROM scheduled_payments WHERE user_id=? AND next_due >= make_date(?, ?, 1) AND next_due < (make_date(?, ?, 1) + INTERVAL '1 month') ORDER BY next_due");
  $sp->execute([$u,$year,$month,$year,$month]); $scheduled=$sp->fetchAll();

  // Loan payments in that month
  $lp=$pdo->prepare("SELECT l.name, lp.* FROM loan_payments lp JOIN loans l ON l.id=lp.loan_id AND l.user_id=?
                     WHERE lp.paid_on >= make_date(?, ?, 1) AND lp.paid_on < (make_date(?, ?, 1) + INTERVAL '1 month')
                     ORDER BY lp.paid_on DESC");
  $lp->execute([$u,$year,$month,$year,$month]); $loanPayments=$lp->fetchAll();

  // Categories for quick add
  $cats = $pdo->prepare('SELECT id,label,kind FROM categories WHERE user_id=? ORDER BY kind,label');
  $cats->execute([$u]); $cats=$cats->fetchAll();

  view('years/month', compact(`$sumIn_main,$sumOut_main,$sumIn_native,$sumOut_native,$main`));
}

/* Month‑scoped transaction POST endpoints (redirect back to the month page) */
function month_tx_add(PDO $pdo){
  verify_csrf(); require_login(); $u = uid();

  $kind = $_POST['kind'] === 'spending' ? 'spending' : 'income';
  $category_id = ($_POST['category_id'] ?? '') !== '' ? (int)$_POST['category_id'] : null;
  $amount = (float)($_POST['amount'] ?? 0);
  $currency = strtoupper(trim($_POST['currency'] ?? ''));
  $occurred_on = $_POST['occurred_on'] ?? date('Y-m-d');
  $note = trim($_POST['note'] ?? '');

  // determine user's main currency at insert time
  $main = fx_user_main($pdo, $u) ?: $currency ?: 'HUF';

  // compute rate & main amount using available rate for that day
  $rate = fx_rate_from_to($pdo, $currency ?: $main, $main, $occurred_on);
  if ($rate === null) { $rate = 1.0; } // graceful fallback; you can throw instead
  $amount_main = round($amount * $rate, 2);

  $stmt = $pdo->prepare("
    INSERT INTO transactions (user_id, kind, category_id, amount, currency, occurred_on, note,
                              main_currency, fx_rate_to_main, amount_main)
    VALUES (?, ?, ?, ?, ?, ?::date, ?, ?, ?, ?)
  ");
  $stmt->execute([$u, $kind, $category_id, $amount, ($currency ?: $main), $occurred_on, $note,
                  $main, $rate, $amount_main]);

  // redirect back
  $y = (int)($_POST['y'] ?? date('Y')); $m = (int)($_POST['m'] ?? date('n'));
  redirect("/years/$y/$m");
}

function month_tx_edit(PDO $pdo){
  verify_csrf(); require_login(); $u=uid();

  $id = (int)($_POST['id'] ?? 0);
  if (!$id) return;

  $kind = $_POST['kind'] === 'spending' ? 'spending' : 'income';
  $amount = (float)($_POST['amount'] ?? 0);
  $currency = strtoupper(trim($_POST['currency'] ?? ''));
  $occurred_on = $_POST['occurred_on'] ?? date('Y-m-d');
  $note = trim($_POST['note'] ?? '');

  // keep main as the user's current main; or load stored main if you prefer historical
  $main = fx_user_main($pdo, $u);

  $rate = fx_rate_from_to($pdo, $currency ?: $main, $main, $occurred_on);
  if ($rate === null) { $rate = 1.0; }
  $amount_main = round($amount * $rate, 2);

  $stmt = $pdo->prepare("
    UPDATE transactions
       SET kind=?, amount=?, currency=?, occurred_on=?::date, note=?,
           fx_rate_to_main=?, amount_main=?, main_currency=?
     WHERE id=? AND user_id=?
  ");
  $stmt->execute([$kind, $amount, ($currency ?: $main), $occurred_on, $note,
                  $rate, $amount_main, $main, $id, $u]);

  $y=(int)($_POST['y'] ?? date('Y')); $m=(int)($_POST['m'] ?? date('n'));
  redirect("/years/$y/$m");
}

function month_tx_delete(PDO $pdo){ verify_csrf(); require_login();
  $y=(int)$_POST['y']; $m=(int)$_POST['m']; $u=uid();
  $pdo->prepare('DELETE FROM transactions WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$u]);
  redirect('/years/'.$y.'/'.$m);
}