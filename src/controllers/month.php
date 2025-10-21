<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../fx.php';
require_once __DIR__ . '/../recurrence.php';

function month_show(PDO $pdo, ?int $year = null, ?int $month = null) {
  require_login();

  // Parse selected month from query (?ym=YYYY-MM) or fall back to current
  $ymParam = trim($_GET['ym'] ?? '');
  if (preg_match('/^\d{4}-\d{2}$/', $ymParam)) {
    [$y, $m] = array_map('intval', explode('-', $ymParam));
  } else {
    $y = $year  ?? (int)date('Y');
    $m = $month ?? (int)date('n');
  }

  // Clamp month/year just in case
  $m = max(1, min(12, $m));
  $y = max(1970, min(9999, $y));

  // Helpers for first/last day, prev/next month (as YYYY-MM)
  $first = sprintf('%04d-%02d-01', $y, $m);
  $last  = date('Y-m-t', strtotime($first));

  $prevFirst = date('Y-m-01', strtotime('-1 month', strtotime($first)));
  $nextFirst = date('Y-m-01', strtotime('+1 month', strtotime($first)));
  $ymPrev = date('Y-m', strtotime($prevFirst));
  $ymNext = date('Y-m', strtotime($nextFirst));
  $ymThis = date('Y-m'); // “This month” button

  $u = uid();

  // default = current month
  // $y = $year  ?? (int)date('Y');
  // $m = $month ?? (int)date('n');

  // $first = sprintf('%04d-%02d-01', $y, $m);
  // $last  = date('Y-m-t', strtotime($first));
  $main  = fx_user_main($pdo, $u);

  // Totals (initialize before using in any loop)
  $sumIn_native_by_cur = [];
  $sumOut_native_by_cur = [];
  $sumIn_main = 0.0; $sumOut_main = 0.0;

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
    if ($flt['category_id'] && (int)($row['category_id'] ?? 0) !== (int)$flt['category_id']) return false;
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

  // (A) Basic incomes active this month (income on 1st) — build virtual rows only
  $bi = $pdo->prepare("
    SELECT b.label, b.amount, b.currency, b.category_id,
          c.label AS cat_label, COALESCE(NULLIF(c.color,''),'#6B7280') AS cat_color
    FROM basic_incomes b
    LEFT JOIN categories c
          ON c.id = b.category_id AND c.user_id = b.user_id
    WHERE b.user_id=? AND b.valid_from<=?::date AND (b.valid_to IS NULL OR b.valid_to>=?::date)
  ");
  $bi->execute([$u,$last,$first]);

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
      'category_id'   => $b['category_id'] ?? null,
      'cat_label'     => ($b['cat_label'] ?: 'Basic Income'),
      'cat_color'     => ($b['cat_color'] ?: '#2563EB'),
      'amount'        => $amt,          // native
      'currency'      => $cur,          // native
      'amount_main'   => $amt_main,     // precomputed main
      'main_currency' => $main,
      'note'          => $b['label'] ? ('Label: '.$b['label']) : null,
    ];

    // respect filters for virtuals
    if ($matchVirtual($rowV)) $virtualTx[] = $rowV;
  }


  // (B) Scheduled payments — expand occurrences for the viewed month
  $allSched = $pdo->prepare("
    SELECT s.id, s.title, s.amount, s.currency, s.next_due, s.rrule, s.category_id,
          c.label AS cat_label,
          COALESCE(NULLIF(c.color,''),'#6B7280') AS cat_color
      FROM scheduled_payments s
      LEFT JOIN categories c
            ON c.id = s.category_id AND c.user_id = s.user_id
    WHERE s.user_id = ?
    ORDER BY s.next_due NULLS LAST, lower(s.title)
  ");
  $allSched->execute([$u]);

  foreach ($allSched as $s) {
    $amt = (float)$s['amount'];
    $cur = strtoupper($s['currency'] ?: $main);
    $title = $s['title'];
    $dtstart = $s['next_due'];             // DTSTART
    $rr = trim($s['rrule'] ?? '');

    // Skip malformed schedules (no next_due or rule)
    if (!$dtstart || !is_string($dtstart)) {
      continue;
    }

    // Expand occurrences for current month window
    $dates = rrule_expand($dtstart, $rr, $first, $last); // returns array of 'Y-m-d'
    if (!$dates) continue;

    foreach ($dates as $due) {
      $amt_main = fx_convert($pdo, $amt, $cur, $main, $due);

      $rowV = [
        'id'            => null,
        'is_virtual'    => true,
        'virtual_type'  => 'scheduled',
        'occurred_on'   => $due,
        'kind'          => 'spending',
        'category_id'   => $s['category_id'] ?? null,
        'cat_label'     => ($s['cat_label'] ?: 'Scheduled'),
        'cat_color'     => ($s['cat_color'] ?: '#F59E0B'),
        'amount'        => $amt,
        'currency'      => $cur,
        'amount_main'   => $amt_main,
        'main_currency' => $main,
        'note'          => $title,
      ];
      if ($matchVirtual($rowV)) $virtualTx[] = $rowV;
    }
  }

  // (C) Investment manual adjustments (deposits/withdrawals)
  $schedMetaStmt = $pdo->prepare("SELECT sp.investment_id, sp.category_id, c.label AS cat_label, COALESCE(NULLIF(c.color,''),'#2563EB') AS cat_color
    FROM scheduled_payments sp
    LEFT JOIN categories c ON c.id = sp.category_id AND c.user_id = sp.user_id
    WHERE sp.user_id = ? AND sp.investment_id IS NOT NULL");
  $schedMetaStmt->execute([$u]);
  $scheduleByInvestment = [];
  foreach ($schedMetaStmt as $meta) {
    $invId = (int)($meta['investment_id'] ?? 0);
    if (!$invId || isset($scheduleByInvestment[$invId])) continue;
    $scheduleByInvestment[$invId] = [
      'category_id' => isset($meta['category_id']) ? (int)$meta['category_id'] : null,
      'cat_label' => $meta['cat_label'] ?? null,
      'cat_color' => $meta['cat_color'] ?? '#2563EB',
    ];
  }

  $invTxStmt = $pdo->prepare(
    "SELECT it.id, it.investment_id, it.amount, it.note, it.created_at, i.name AS investment_name, i.currency
       FROM investment_transactions it
       JOIN investments i ON i.id = it.investment_id AND i.user_id = it.user_id
      WHERE it.user_id = ?
        AND it.created_at::date BETWEEN ?::date AND ?::date
      ORDER BY it.created_at ASC, it.id ASC"
  );
  $invTxStmt->execute([$u, $df, $dt]);

  foreach ($invTxStmt as $tx) {
    $invId = (int)($tx['investment_id'] ?? 0);
    $meta = $scheduleByInvestment[$invId] ?? null;
    $occurred = substr((string)($tx['created_at'] ?? ''), 0, 10) ?: $df;
    $amountRaw = (float)($tx['amount'] ?? 0);
    $kind = $amountRaw >= 0 ? 'spending' : 'income';
    $nativeAmount = abs($amountRaw);
    $currency = strtoupper((string)($tx['currency'] ?? $main));
    if ($currency === '') {
      $currency = $main;
    }

    $noteParts = [];
    $noteValue = trim((string)($tx['note'] ?? ''));
    if ($noteValue !== '') {
      $noteParts[] = $noteValue;
    }
    $investmentName = trim((string)($tx['investment_name'] ?? ''));
    if ($investmentName !== '') {
      $noteParts[] = $investmentName;
    }
    $note = implode(' · ', $noteParts);

    $rowV = [
      'id' => null,
      'is_virtual' => true,
      'virtual_type' => 'investment_adjustment',
      'occurred_on' => $occurred,
      'kind' => $kind,
      'category_id' => $meta['category_id'] ?? null,
      'cat_label' => $meta['cat_label'] ?? __('Investments'),
      'cat_color' => $meta['cat_color'] ?? '#2563EB',
      'amount' => $nativeAmount,
      'currency' => $currency,
      'amount_main' => fx_convert($pdo, $nativeAmount, $currency, $main, $occurred),
      'main_currency' => $main,
      'note' => $note,
    ];

    if ($matchVirtual($rowV)) {
      $virtualTx[] = $rowV;
    }
  }

  // (D) Goal contributions (manual)
  $q = $pdo->prepare("
    SELECT
        gc.id,
        gc.user_id,
        gc.goal_id,
        gc.amount,
        gc.currency,
        gc.occurred_on,
        g.title AS goal_title
    FROM goal_contributions gc
    JOIN goals g
      ON g.id = gc.goal_id
    AND g.user_id = ?
    WHERE gc.user_id = ?
      AND gc.occurred_on BETWEEN ?::date AND ?::date
    ORDER BY gc.occurred_on, gc.id
  ");
  $q->execute([$u, $u, $first, $last]);
  $goalTx = $q->fetchAll();

  foreach ($goalTx as $gc) {
    $rowV = [
      'id'            => null,
      'is_virtual'    => true,
      'virtual_type'  => 'goal_contribution',
      'occurred_on'   => $gc['occurred_on'],
      'kind'          => 'spending',
      'category_id'   => null,
      'cat_label'     => 'Goal',
      'cat_color'     => '#10B981',
      'amount'        => (float)$gc['amount'],
      'currency'      => strtoupper($gc['currency'] ?: $main),
      'note'          => 'Goal: ' . ($gc['goal_title'] ?: 'Contribution'),
    ];
    if ($matchVirtual($rowV)) $virtualTx[] = $rowV;
  }


  // -------------------- TOTALS (from FULL filtered real + filtered virtual) --------------------

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

  // -------------------- MERGE (virtual + reals) --------------------
  $sortTx = function($a, $b) {
    $da = strtotime($a['occurred_on']);
    $db = strtotime($b['occurred_on']);
    if ($da === $db) {
      $av = !empty($a['is_virtual']);
      $bv = !empty($b['is_virtual']);
      if ($av !== $bv) return $av ? 1 : -1; // real first
      return ($b['id'] ?? 0) <=> ($a['id'] ?? 0);
    }
    return $db <=> $da;
  };

  // For table display we only keep the current page of real tx
  $txDisplay = array_merge($virtualTx, $realTx_page);
  usort($txDisplay, $sortTx);

  // For calculations/charts we need the full month selection
  $allTx = array_merge($virtualTx, $realTx_full);
  usort($allTx, $sortTx);

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


  // ---- Cashflow Guidance (rules & per-category remaining) ----

  // 1) Load rules
  $rulesStmt = $pdo->prepare("
    SELECT id, label, percent
    FROM cashflow_rules
    WHERE user_id = ?
    ORDER BY id
  ");
  $rulesStmt->execute([$u]);
  $rules = $rulesStmt->fetchAll(PDO::FETCH_ASSOC);

  // 2) Load spending categories + rule links
  $catsCFStmt = $pdo->prepare("
    SELECT id, label, kind, COALESCE(NULLIF(color,''),'#6B7280') AS color, cashflow_rule_id
    FROM categories
    WHERE user_id = ? AND kind='spending'
  ");
  $catsCFStmt->execute([$u]);
  $catsCF = $catsCFStmt->fetchAll(PDO::FETCH_ASSOC);

  // Map categories by id and by rule
  $catById = [];
  $catsByRule = [];
  foreach ($catsCF as $c) {
    $catById[(int)$c['id']] = $c;
    $rid = (int)($c['cashflow_rule_id'] ?? 0);
    if ($rid) $catsByRule[$rid][] = (int)$c['id'];
  }

  // 3) Compute spent per category (ALL tx: real + virtual)
  $spentByCatMain = [];
  foreach ($allTx as $r) {
    if (($r['kind'] ?? '') !== 'spending') continue;
    $cid = (int)($r['category_id'] ?? 0);
    if (!$cid) continue;

    if (isset($r['amount_main']) && $r['amount_main'] !== null && !empty($r['main_currency'])) {
      $amtMain = (float)$r['amount_main'];
    } else {
      $from = $r['currency'] ?: $main;
      $amtMain = fx_convert($pdo, (float)$r['amount'], $from, $main, $r['occurred_on']);
    }

    $spentByCatMain[$cid] = ($spentByCatMain[$cid] ?? 0) + max(0, $amtMain);
  }


  // 4) Build rule guides (budget = percent% of this month’s income)
  $ruleGuides = [];          // rule_id => ['label','percent','budget','spent','remaining','color']
  $catGuides  = [];          // category_id => ['cap','spent','remaining','rule_id','rule_label']

  foreach ($rules as $rul) {
    $rid      = (int)$rul['id'];
    $label    = (string)$rul['label'];
    $percent  = (float)($rul['percent'] ?? 0.0);
    $budget   = max(0.0, round(($percent / 100.0) * (float)$sumIn_main, 2));

    $catIds = $catsByRule[$rid] ?? [];
    $spentRule = 0.0;
    foreach ($catIds as $cid) {
      $spentRule += (float)($spentByCatMain[$cid] ?? 0.0);
    }
    $remainingRule = max(0.0, $budget - $spentRule);

    $ruleGuides[$rid] = [
      'label'     => $label,
      'percent'   => $percent,
      'budget'    => $budget,
      'spent'     => $spentRule,
      'remaining' => $remainingRule,
      'color'     => '#111827', // progress color; tweak if you want per-rule colors
    ];

    // 5) Equal-split rule budget into category-level “caps” (fallback approach)
    $nCats = max(1, count($catIds));
    $capPerCat = $budget / $nCats;

    foreach ($catIds as $cid) {
      $spentCat = (float)($spentByCatMain[$cid] ?? 0.0);
      $catGuides[$cid] = [
        'cap'        => $capPerCat,
        'spent'      => $spentCat,
        'remaining'  => max(0.0, $capPerCat - $spentCat),
        'rule_id'    => $rid,
        'rule_label' => $label,
      ];
    }
  }


  // -------------------- VIEW --------------------
  view('month/index', compact(
    'txDisplay','allTx','y','m','first','last','main',
    'sumIn_main','sumOut_main','sumIn_native_by_cur','sumOut_native_by_cur',
    'cats','g','e','userCurrencies',
    'page','totalPages','flt', // ← for filters + pagination UI
    'ruleGuides','catGuides',
    'ymPrev','ymNext','ymThis'
  ));
}
