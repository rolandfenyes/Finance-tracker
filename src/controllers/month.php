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

  // -------------------- FILTERS & PAGINATION --------------------
  $flt = [
    'q'           => trim($_GET['q'] ?? ''), // note/category search
    'category_id' => ($_GET['category_id'] ?? '') !== '' ? (int)$_GET['category_id'] : null,
    'kind'        => in_array(($_GET['kind'] ?? ''), ['income','spending'], true) ? $_GET['kind'] : null,
    'date_from'   => $_GET['date_from'] ?? null,
    'date_to'     => $_GET['date_to'] ?? null,
    'amt_min'     => ($_GET['amt_min'] ?? '') !== '' ? (float)$_GET['amt_min'] : null,
    'amt_max'     => ($_GET['amt_max'] ?? '') !== '' ? (float)$_GET['amt_max'] : null,
    'currency'    => $_GET['currency'] ?? null,
  ];

  // constrain filter date range to inside month (still allow user-specified range within it)
  $df = $flt['date_from'] ? max($flt['date_from'], $first) : $first;
  $dt = $flt['date_to']   ? min($flt['date_to'],   $last)  : $last;

  $page = max(1, (int)($_GET['page'] ?? 1));
  $per  = 25; // desktop page size

  // WHERE for REAL tx
  $where = ['t.user_id = ?', 't.occurred_on BETWEEN ?::date AND ?::date'];
  $params = [$u, $df, $dt];

  if ($flt['q'] !== '') {
    $where[] = '(t.note ILIKE ? OR c.label ILIKE ?)';
    $params[] = '%'.$flt['q'].'%';
    $params[] = '%'.$flt['q'].'%';
  }
  if ($flt['category_id']) { $where[] = 't.category_id = ?'; $params[] = $flt['category_id']; }
  if ($flt['kind'])        { $where[] = 't.kind = ?';        $params[] = $flt['kind']; }
  if ($flt['amt_min']!==null){ $where[] = 't.amount >= ?';   $params[] = $flt['amt_min']; }
  if ($flt['amt_max']!==null){ $where[] = 't.amount <= ?';   $params[] = $flt['amt_max']; }
  if ($flt['currency'])    { $where[] = 't.currency = ?';    $params[] = $flt['currency']; }

  $whereSql = '('.implode(') AND (', $where).')';

  // -------------------- REAL TX: COUNT (for pagination) --------------------
  $cnt = $pdo->prepare("
    SELECT COUNT(*)
    FROM transactions t
    LEFT JOIN categories c ON c.id=t.category_id AND c.user_id=t.user_id
    WHERE $whereSql
  ");
  $cnt->execute($params);
  $totalReal   = (int)$cnt->fetchColumn();
  $totalPages  = max(1, (int)ceil($totalReal / $per));
  $page        = min($page, $totalPages);
  $offset      = ($page - 1) * $per;

  // -------------------- REAL TX: PAGE (for display) --------------------
  $stmt = $pdo->prepare("
    SELECT t.*, c.label AS cat_label, c.color AS cat_color
    FROM transactions t
    LEFT JOIN categories c ON c.id=t.category_id AND c.user_id=t.user_id
    WHERE $whereSql
    ORDER BY t.occurred_on DESC, t.id DESC
    LIMIT $per OFFSET $offset
  ");
  $stmt->execute($params);
  $realTx_page = $stmt->fetchAll();

  // -------------------- REAL TX: FULL (for totals only) --------------------
  $stmtTotals = $pdo->prepare("
    SELECT t.*, c.label AS cat_label, c.color AS cat_color
    FROM transactions t
    LEFT JOIN categories c ON c.id=t.category_id AND c.user_id=t.user_id
    WHERE $whereSql
    ORDER BY t.occurred_on DESC, t.id DESC
  ");
  $stmtTotals->execute($params);
  $realTx_full = $stmtTotals->fetchAll();

  // -------------------- VIRTUAL ROWS --------------------
  $virtualTx = [];

  // Helper to check if a virtual row matches filters
  $matchVirtual = function(array $row) use ($flt, $df, $dt): bool {
    // date range
    if ($row['occurred_on'] < $df || $row['occurred_on'] > $dt) return false;
    // kind
    if ($flt['kind'] && $row['kind'] !== $flt['kind']) return false;
    // category filter: virtuals have no category_id → exclude when filter present
    if ($flt['category_id']) return false;
    // currency filter (native)
    if ($flt['currency'] && strtoupper($row['currency'] ?? '') !== strtoupper($flt['currency'])) return false;
    // amount range (native)
    if ($flt['amt_min'] !== null && $row['amount'] < $flt['amt_min']) return false;
    if ($flt['amt_max'] !== null && $row['amount'] > $flt['amt_max']) return false;
    // q search (note / cat_label)
    if ($flt['q'] !== '') {
      $needle = mb_strtolower($flt['q']);
      $hay = mb_strtolower(trim(($row['note'] ?? '').' '.($row['cat_label'] ?? '')));
      if (mb_strpos($hay, $needle) === false) return false;
    }
    return true;
  };

  // (A) Basic incomes (income on 1st)
  $bi = $pdo->prepare("
    SELECT label, amount, currency
    FROM basic_incomes
    WHERE user_id=? AND valid_from<=?::date AND (valid_to IS NULL OR valid_to>=?::date)
  ");
  $bi->execute([$u, $last, $first]);
  foreach ($bi as $b) {
    $amt = (float)$b['amount'];
    $cur = strtoupper($b['currency'] ?: $main);
    $amt_main = fx_convert_basic_income($pdo, $amt, $cur, $main, $y, $m);
    $rowV = [
      'id'            => null,
      'is_virtual'    => true,
      'virtual_type'  => 'basic_income',
      'occurred_on'   => $first,
      'kind'          => 'income',
      'cat_label'     => 'Basic Income',
      'cat_color'     => '#2563EB', // blue
      'amount'        => $amt,
      'currency'      => $cur,
      'amount_main'   => $amt_main,
      'main_currency' => $main,
      'note'          => $b['label'] ? ('Label: '.$b['label']) : null,
    ];
    if ($matchVirtual($rowV)) $virtualTx[] = $rowV;
  }

  // (B) Scheduled payments due inside month (spending on due date)
  $sp = $pdo->prepare("
    SELECT id, title, amount, currency, next_due
    FROM scheduled_payments
    WHERE user_id=? AND next_due BETWEEN ?::date AND ?::date
    ORDER BY next_due
  ");
  $sp->execute([$u, $first, $last]);
  foreach ($sp as $s) {
    $amt = (float)$s['amount'];
    $cur = strtoupper($s['currency'] ?: $main);
    $due = $s['next_due'];
    $amt_main = fx_convert($pdo, $amt, $cur, $main, $due);

    $rowV = [
      'id'            => null,
      'is_virtual'    => true,
      'virtual_type'  => 'scheduled',
      'occurred_on'   => $due,
      'kind'          => 'spending',
      'cat_label'     => 'Scheduled: '.$s['title'],
      'cat_color'     => '#F59E0B', // amber
      'amount'        => $amt,
      'currency'      => $cur,
      'amount_main'   => $amt_main,
      'main_currency' => $main,
      'note'          => null,
    ];
    if ($matchVirtual($rowV)) $virtualTx[] = $rowV;
  }

  // -------------------- TOTALS (from FULL filtered real + filtered virtual) --------------------
  $sumIn_native_by_cur = [];
  $sumOut_native_by_cur = [];
  $sumIn_main = 0.0; $sumOut_main = 0.0;

  $adder = function($r) use (&$sumIn_native_by_cur,&$sumOut_native_by_cur,&$sumIn_main,&$sumOut_main,$main,$pdo) {
    $amt  = (float)$r['amount'];
    $from = strtoupper($r['currency'] ?: $main);
    // prefer stored main amount if present
    if (array_key_exists('amount_main', $r) && $r['amount_main'] !== null && !empty($r['main_currency'])) {
      $amt_main = (float)$r['amount_main'];
    } else {
      $amt_main = fx_convert($pdo, $amt, $from, $r['main_currency'] ?? $main, $r['occurred_on']);
    }
    if ($r['kind'] === 'income') {
      $sumIn_native_by_cur[$from] = ($sumIn_native_by_cur[$from] ?? 0) + $amt;
      $sumIn_main += $amt_main;
    } else {
      $sumOut_native_by_cur[$from] = ($sumOut_native_by_cur[$from] ?? 0) + $amt;
      $sumOut_main += $amt_main;
    }
  };

  foreach ($realTx_full as $r) { $adder($r); }
  foreach ($virtualTx   as $r) { $adder($r); }

  // -------------------- MERGE (virtual + paged reals) FOR DISPLAY --------------------
  $allTx = array_merge($virtualTx, $realTx_page);
  usort($allTx, function($a,$b){
    $da = strtotime($a['occurred_on']); $db = strtotime($b['occurred_on']);
    if ($da === $db) {
      $av = !empty($a['is_virtual']); $bv = !empty($b['is_virtual']);
      if ($av !== $bv) return $av ? 1 : -1; // real first
      return ($b['id'] ?? 0) <=> ($a['id'] ?? 0);
    }
    return $db <=> $da;
  });

  // -------------------- AUX --------------------
  // Categories (with color) for legend & filters
  $catsStmt = $pdo->prepare("
    SELECT id, label, kind, COALESCE(NULLIF(color, ''), '#6B7280') AS color
    FROM categories
    WHERE user_id = ?
    ORDER BY kind, label
  ");
  $catsStmt->execute([$u]);
  $cats = $catsStmt->fetchAll(PDO::FETCH_ASSOC);

  // Goals/Emergency (unchanged)
  $g=$pdo->prepare("SELECT SUM(current_amount) c, SUM(target_amount) t FROM goals WHERE user_id=? AND status='active'");
  $g->execute([$u]); $g=$g->fetch();

  $e=$pdo->prepare('SELECT total,target_amount,currency FROM emergency_fund WHERE user_id=?');
  $e->execute([$u]); $e=$e->fetch();

  // user currencies (for selectors)
  $uc = $pdo->prepare('SELECT code, is_main FROM user_currencies WHERE user_id=? ORDER BY is_main DESC, code');
  $uc->execute([$u]); $userCurrencies = $uc->fetchAll();

  // -------------------- VIEW --------------------
  view('month/index', compact(
    'allTx','y','m','first','last','main',
    'sumIn_main','sumOut_main','sumIn_native_by_cur','sumOut_native_by_cur',
    'cats','g','e','userCurrencies',
    'page','totalPages','flt' // ← for filters + pagination UI
  ));
}
