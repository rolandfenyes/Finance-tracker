<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../fx.php';

function current_month_controller(PDO $pdo) {
  require_login();
  $u = uid();
  $y = (int)date('Y');
  $m = (int)date('n');
  $first = date('Y-m-01');
  $last  = date('Y-m-t');
  $main  = fx_user_main($pdo, $u);

  // Load transactions for the month
  $stmt = $pdo->prepare("
    SELECT t.*, c.label AS cat_label
    FROM transactions t
    LEFT JOIN categories c
      ON c.id = t.category_id
     AND c.user_id = t.user_id
    WHERE t.user_id = ?
      AND t.occurred_on BETWEEN ?::date AND ?::date
    ORDER BY t.occurred_on DESC, t.id DESC
  ");
  $stmt->execute([$u, $first, $last]);
  $tx = $stmt->fetchAll();

  // Totals: native grouped by currency + main-currency (converted)
  $sumIn_native_by_cur = [];
  $sumOut_native_by_cur = [];
  $sumIn_main = 0.0;
  $sumOut_main = 0.0;

  foreach ($tx as $r) {
    $amt  = (float)$r['amount'];
    $from = strtoupper($r['currency'] ?: $main);
    $amt_main = fx_convert($pdo, $amt, $from, $main, $r['occurred_on']);

    if ($r['kind'] === 'income') {
      $sumIn_native_by_cur[$from] = ($sumIn_native_by_cur[$from] ?? 0) + $amt;
      $sumIn_main += $amt_main;
    } else {
      $sumOut_native_by_cur[$from] = ($sumOut_native_by_cur[$from] ?? 0) + $amt;
      $sumOut_main += $amt_main;
    }
  }

  // Basic incomes active this month — convert using 1st-of-month (or latest prior)
  $bi = $pdo->prepare("
    SELECT amount, currency
    FROM basic_incomes
    WHERE user_id = ?
      AND valid_from <= ?::date
      AND (valid_to IS NULL OR valid_to >= ?::date)
  ");
  $bi->execute([$u, $last, $first]);

  foreach ($bi as $b) {
    $amt = (float)$b['amount'];
    $cur = strtoupper($b['currency'] ?? $main);
    $sumIn_native_by_cur[$cur] = ($sumIn_native_by_cur[$cur] ?? 0) + $amt;
    $sumIn_main += fx_convert_basic_income($pdo, $amt, $cur, $main, $y, $m);
  }

  // Categories for quick add
  $cats = $pdo->prepare('SELECT id, label, kind FROM categories WHERE user_id = ? ORDER BY kind, label');
  $cats->execute([$u]);
  $cats = $cats->fetchAll();

  view(
    'current_month',
    compact(
      'tx',
      'sumIn_main',
      'sumOut_main',
      'sumIn_native_by_cur',
      'sumOut_native_by_cur',
      'cats',
      'y',
      'm',
      'main'
    )
  );
}

