<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../fx.php';

function month_show(PDO $pdo, ?int $year = null, ?int $month = null) {
  require_login();
  $u = uid();

  // default = current month
  $y = $year  ?? (int)date('Y');
  $m = $month ?? (int)date('n');

  $first = sprintf('%04d-%02d-01', $y, $m);
  $last  = date('Y-m-t', strtotime($first));
  $main  = fx_user_main($pdo, $u);

  // Real transactions
  $stmt = $pdo->prepare("
    SELECT t.*, c.label AS cat_label, c.color AS cat_color
    FROM transactions t
    LEFT JOIN categories c ON c.id=t.category_id AND c.user_id=t.user_id
    WHERE t.user_id=? AND t.occurred_on BETWEEN ?::date AND ?::date
    ORDER BY t.occurred_on DESC, t.id DESC
  ");
  $stmt->execute([$u,$first,$last]);
  $realTx = $stmt->fetchAll();

  // Totals (native by currency + main-currency)
  $sumIn_native_by_cur = [];
  $sumOut_native_by_cur = [];
  $sumIn_main = 0.0; $sumOut_main = 0.0;

  // Sum reals
  foreach ($realTx as $r) {
    $amt  = (float)$r['amount'];
    $from = strtoupper($r['currency'] ?: $main);
    // prefer stored amount_main if present (new schema), else compute
    if (array_key_exists('amount_main',$r) && $r['amount_main'] !== null && !empty($r['main_currency'])) {
      $amt_main = (float)$r['amount_main'];
    } else {
      $amt_main = fx_convert($pdo, $amt, $from, $main, $r['occurred_on']);
    }
    if ($r['kind']==='income') {
      $sumIn_native_by_cur[$from] = ($sumIn_native_by_cur[$from] ?? 0) + $amt;
      $sumIn_main += $amt_main;
    } else {
      $sumOut_native_by_cur[$from] = ($sumOut_native_by_cur[$from] ?? 0) + $amt;
      $sumOut_main += $amt_main;
    }
  }

  /* -------------------- VIRTUAL ROWS -------------------- */

  $virtualTx = [];

  // (A) Basic incomes active this month (one per row; income on 1st)
  $bi = $pdo->prepare("
    SELECT label, amount, currency
    FROM basic_incomes
    WHERE user_id=? AND valid_from<=?::date AND (valid_to IS NULL OR valid_to>=?::date)
  ");
  $bi->execute([$u,$last,$first]);
  foreach ($bi as $b) {
    $amt = (float)$b['amount'];
    $cur = strtoupper($b['currency'] ?: $main);
    $amt_main = fx_convert_basic_income($pdo, $amt, $cur, $main, $y, $m);

    // contribute to totals
    $sumIn_native_by_cur[$cur] = ($sumIn_native_by_cur[$cur] ?? 0) + $amt;
    $sumIn_main += $amt_main;

    // synthetic row
    $virtualTx[] = [
      'id'          => null,
      'is_virtual'  => true,
      'virtual_type'=> 'basic_income',
      'occurred_on' => $first,
      'kind'        => 'income',
      'cat_label'   => 'Basic Income',
      'cat_color' => '#2563EB',  // blue
      'amount'      => $amt,
      'currency'    => $cur,
      'amount_main' => $amt_main,
      'main_currency' => $main,
      'note'        => $b['label'] ? ('Label: '.$b['label']) : null,
    ];
  }

  // (B) Scheduled payments falling inside this month (spending on due date)
  // Adjust this query if you have recurring rules; here we include the ones due this month.
  $sp = $pdo->prepare("
    SELECT id, title, amount, currency, next_due
    FROM scheduled_payments
    WHERE user_id=? AND next_due BETWEEN ?::date AND ?::date
    ORDER BY next_due
  ");
  $sp->execute([$u,$first,$last]);
  foreach ($sp as $s) {
    $amt = (float)$s['amount'];
    $cur = strtoupper($s['currency'] ?: $main);
    $due = $s['next_due'];
    $amt_main = fx_convert($pdo, $amt, $cur, $main, $due);

    // contribute to totals as spending
    $sumOut_native_by_cur[$cur] = ($sumOut_native_by_cur[$cur] ?? 0) + $amt;
    $sumOut_main += $amt_main;

    $virtualTx[] = [
      'id'          => null,
      'is_virtual'  => true,
      'virtual_type'=> 'scheduled',
      'occurred_on' => $due,
      'kind'        => 'spending',
      'cat_label'   => 'Scheduled: '.$s['title'],
      'cat_color' => '#F59E0B',  // amber
      'amount'      => $amt,
      'currency'    => $cur,
      'amount_main' => $amt_main,
      'main_currency' => $main,
      'note'        => null,
    ];
  }

  // Merge + sort (newest first, then real tx id desc)
  $allTx = array_merge($virtualTx, $realTx);
  usort($allTx, function($a,$b){
    $da = strtotime($a['occurred_on']); $db = strtotime($b['occurred_on']);
    if ($da === $db) {
      // virtuals come after real ones for same day; then id desc
      $av = !empty($a['is_virtual']); $bv = !empty($b['is_virtual']);
      if ($av !== $bv) return $av ? 1 : -1;
      return ($b['id'] ?? 0) <=> ($a['id'] ?? 0);
    }
    return $db <=> $da;
  });

  // Aux
  // In month_show(), where you load categories for the legend / selects:
  // Load categories WITH color (defaulting to gray if null/empty)
  $cats = $pdo->prepare("SELECT id, label, kind, COALESCE(NULLIF(color, ''), '#6B7280') AS color
    FROM categories
    WHERE user_id = ?
    ORDER BY kind, label
  ");
  $cats->execute([ $u ]);
  $cats = $cats->fetchAll(PDO::FETCH_ASSOC);



  // (optional) other cards as before
  $g=$pdo->prepare("SELECT SUM(current_amount) c, SUM(target_amount) t FROM goals WHERE user_id=? AND status='active'");
  $g->execute([$u]); $g=$g->fetch();

  $e=$pdo->prepare('SELECT total,target_amount,currency FROM emergency_fund WHERE user_id=?');
  $e->execute([$u]); $e=$e->fetch();

  // user currencies (for selectors)
  $uc = $pdo->prepare('SELECT code, is_main FROM user_currencies WHERE user_id=? ORDER BY is_main DESC, code');
  $uc->execute([$u]); $userCurrencies = $uc->fetchAll();

  view('month/index', compact(
    'allTx','y','m','first','last','main',
    'sumIn_main','sumOut_main','sumIn_native_by_cur','sumOut_native_by_cur',
    'cats','g','e','userCurrencies'
  ));
}
