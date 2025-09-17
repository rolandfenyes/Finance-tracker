<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../fx.php';

function month_show(PDO $pdo, ?int $year = null, ?int $month = null) {
  require_login();
  $u = uid();

  // default = current month
  $y = $year  ?? (int)date('Y');
  $m = $month ?? (int)date('n');

  // bounds + main currency
  $first = sprintf('%04d-%02d-01', $y, $m);
  $last  = date('Y-m-t', strtotime($first));
  $main  = fx_user_main($pdo, $u);

  // Transactions with category
  $stmt = $pdo->prepare("
    SELECT t.*, c.label AS cat_label
    FROM transactions t
    LEFT JOIN categories c ON c.id=t.category_id AND c.user_id=t.user_id
    WHERE t.user_id=? AND t.occurred_on BETWEEN ?::date AND ?::date
    ORDER BY t.occurred_on DESC, t.id DESC
  ");
  $stmt->execute([$u,$first,$last]);
  $tx = $stmt->fetchAll();

  // Totals (native grouped by currency) + converted to main
  $sumIn_native_by_cur = [];
  $sumOut_native_by_cur = [];
  $sumIn_main = 0.0; $sumOut_main = 0.0;

  foreach ($tx as $r) {
    $amt  = (float)$r['amount'];
    $from = strtoupper($r['currency'] ?: $main);
    $amt_main = fx_convert($pdo, $amt, $from, $main, $r['occurred_on']);
    if ($r['kind']==='income') {
      $sumIn_native_by_cur[$from] = ($sumIn_native_by_cur[$from] ?? 0) + $amt;
      $sumIn_main += $amt_main;
    } else {
      $sumOut_native_by_cur[$from] = ($sumOut_native_by_cur[$from] ?? 0) + $amt;
      $sumOut_main += $amt_main;
    }
  }

  // Basic incomes for this month (use 1st-of-month rate)
  $bi = $pdo->prepare("
    SELECT amount, currency
    FROM basic_incomes
    WHERE user_id=? AND valid_from<=?::date AND (valid_to IS NULL OR valid_to>=?::date)
  ");
  $bi->execute([$u,$last,$first]);
  foreach ($bi as $b) {
    $amt = (float)$b['amount'];
    $cur = strtoupper($b['currency'] ?: $main);
    $sumIn_native_by_cur[$cur] = ($sumIn_native_by_cur[$cur] ?? 0) + $amt;
    $sumIn_main += fx_convert_basic_income($pdo, $amt, $cur, $main, $y, $m);
  }

  // Aux data (same for both pages)
  $cats = $pdo->prepare('SELECT id,label,kind FROM categories WHERE user_id=? ORDER BY kind,label');
  $cats->execute([$u]); $cats = $cats->fetchAll();

  // Optional: scheduled payments / goals / emergency snapshots like before
  // (keep if you used them on years/month)
  $sp=$pdo->prepare("SELECT id,title,amount,currency,next_due FROM scheduled_payments
                     WHERE user_id=? AND next_due>=?::date AND next_due<?::date ORDER BY next_due");
  $sp->execute([$u,$first,date('Y-m-d',strtotime("$first +1 month"))]); $scheduled=$sp->fetchAll();

  $g=$pdo->prepare("SELECT SUM(current_amount) c, SUM(target_amount) t FROM goals WHERE user_id=? AND status='active'");
  $g->execute([$u]); $g=$g->fetch();

  $e=$pdo->prepare('SELECT total,target_amount,currency FROM emergency_fund WHERE user_id=?');
  $e->execute([$u]); $e=$e->fetch();

  // User currencies (for selectors), main first
  $uc = $pdo->prepare('SELECT code, is_main FROM user_currencies WHERE user_id=? ORDER BY is_main DESC, code');
  $uc->execute([$u]);
  $userCurrencies = $uc->fetchAll();

  view('month/index', compact(
    'tx','y','m','first','last','main',
    'sumIn_main','sumOut_main','sumIn_native_by_cur','sumOut_native_by_cur',
    'cats','scheduled','g','e', 'userCurrencies'
  ));
}
