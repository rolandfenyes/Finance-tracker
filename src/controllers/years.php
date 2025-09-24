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
  $y = (int)($_POST['y'] ?? date('Y'));
  $m = (int)($_POST['m'] ?? date('n'));
  $ym = sprintf('%04d-%02d', $y, $m);
  redirect('/current-month?ym=' . $ym);
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

  $y = (int)($_POST['y'] ?? date('Y'));
  $m = (int)($_POST['m'] ?? date('n'));
  $ym = sprintf('%04d-%02d', $y, $m);
  redirect('/current-month?ym=' . $ym);
}

function month_tx_delete(PDO $pdo){ verify_csrf(); require_login();
  $y=(int)$_POST['y']; $m=(int)$_POST['m']; $u=uid();
  $pdo->prepare('DELETE FROM transactions WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$u]);
  $ym = sprintf('%04d-%02d', $y, $m);
  redirect('/current-month?ym=' . $ym);
}

function month_read_filters(): array {
  // Read GET filters; normalize
  return [
    'q'           => trim($_GET['q'] ?? ''),                 // text search (note)
    'category_id' => $_GET['category_id'] !== '' ? (int)$_GET['category_id'] : null,
    'kind'        => in_array($_GET['kind'] ?? '', ['income','spending'], true) ? $_GET['kind'] : null,
    'date_from'   => $_GET['date_from'] ?? null,
    'date_to'     => $_GET['date_to']   ?? null,
    'amt_min'     => ($_GET['amt_min'] ?? '') !== '' ? (float)$_GET['amt_min'] : null,
    'amt_max'     => ($_GET['amt_max'] ?? '') !== '' ? (float)$_GET['amt_max'] : null,
    'currency'    => $_GET['currency'] ?? null,
  ];
}

function month_where_clause(array $flt, int $userId, string $first, string $last): array {
  // Build WHERE + params for REAL transactions; keep them inside month, then apply filters.
  $w = ['t.user_id = ?','t.occurred_on BETWEEN ?::date AND ?::date'];
  $p = [$userId, $first, $last];

  if ($flt['q'] !== '') { $w[] = ' (t.note ILIKE ? OR c.label ILIKE ?) '; $p[] = '%'.$flt['q'].'%'; $p[] = '%'.$flt['q'].'%'; }
  if ($flt['category_id']) { $w[] = ' t.category_id = ? '; $p[] = $flt['category_id']; }
  if ($flt['kind'])        { $w[] = ' t.kind = ? '; $p[] = $flt['kind']; }
  if ($flt['date_from'])   { $w[] = ' t.occurred_on >= ?::date '; $p[] = $flt['date_from']; }
  if ($flt['date_to'])     { $w[] = ' t.occurred_on <= ?::date '; $p[] = $flt['date_to']; }
  if ($flt['amt_min']!==null){ $w[] = ' t.amount >= ? '; $p[] = $flt['amt_min']; }
  if ($flt['amt_max']!==null){ $w[] = ' t.amount <= ? '; $p[] = $flt['amt_max']; }
  if ($flt['currency'])    { $w[] = ' t.currency = ? '; $p[] = $flt['currency']; }

  return ['('.implode(') AND (',$w).')', $p];
}

function month_tx_list(PDO $pdo){ // mobile lazy-load fragment
  require_login(); $u=uid();
  $y=(int)($_GET['y'] ?? date('Y')); $m=(int)($_GET['m'] ?? date('n'));
  $first = sprintf('%04d-%02d-01',$y,$m); $last = date('Y-m-t',strtotime($first));
  $page = max(1,(int)($_GET['page'] ?? 1));
  $per  = 15;
  $flt  = month_read_filters();
  [$where,$params] = month_where_clause($flt,$u,$first,$last);

  $sql = "SELECT t.*, c.label AS cat_label, c.color AS cat_color
          FROM transactions t
          LEFT JOIN categories c ON c.id=t.category_id AND c.user_id=t.user_id
          WHERE $where
          ORDER BY t.occurred_on DESC, t.id DESC
          LIMIT $per OFFSET ".(($page-1)*$per);
  $stmt=$pdo->prepare($sql); $stmt->execute($params); $rows=$stmt->fetchAll();

  // render the same mobile cards HTML only (no shell)
  $main = fx_user_main($pdo,$u);
  ob_start();
  foreach($rows as $row){
    $nativeCur = $row['currency'] ?: $main;
    if (isset($row['amount_main']) && $row['amount_main']!==null && !empty($row['main_currency'])){
      $amtMain=(float)$row['amount_main']; $mainCur=$row['main_currency'];
    } else {
      $amtMain=fx_convert($pdo,(float)$row['amount'],$nativeCur,$main,$row['occurred_on']); $mainCur=$main;
    }
    $dot = $row['cat_color'] ?? '#6B7280';
    $sameCur = strtoupper($nativeCur)===strtoupper($mainCur);
    ?>
    <div class="rounded-xl border p-3">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <div class="flex items-center gap-2 text-sm">
            <span class="font-medium"><?= htmlspecialchars($row['occurred_on']) ?></span>
          </div>
          <div class="mt-1 flex items-center gap-2">
            <span class="capitalize text-xs px-2 py-0.5 rounded-full border"><?= htmlspecialchars($row['kind']) ?></span>
            <span class="inline-flex items-center gap-2 text-sm">
              <span class="inline-block h-2.5 w-2.5 rounded-full" style="background-color: <?= htmlspecialchars($dot) ?>;"></span>
              <?= htmlspecialchars($row['cat_label'] ?? '—') ?>
            </span>
          </div>
        </div>
        <div class="text-right shrink-0">
          <div class="text-[13px] text-gray-500">Native</div>
          <div class="font-medium"><?= moneyfmt($row['amount'],$nativeCur) ?></div>
          <?php if(!$sameCur): ?>
            <div class="text-[13px] text-gray-500 mt-1"><?= htmlspecialchars($mainCur) ?></div>
            <div class="font-medium"><?= moneyfmt($amtMain,$mainCur) ?></div>
          <?php endif; ?>
        </div>
      </div>
      <?php if (!empty($row['note'])): ?>
        <div class="mt-2 text-xs text-gray-500"><?= htmlspecialchars($row['note']) ?></div>
      <?php endif; ?>
    </div>
    <?php
  }
  $html = ob_get_clean();
  header('Content-Type: text/html; charset=utf-8'); echo $html; exit;
}