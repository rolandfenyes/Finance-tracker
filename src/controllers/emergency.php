<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../fx.php';
require_once __DIR__ . '/../recurrence.php'; // for rrule_expand


function emergency_index(PDO $pdo){
  require_login(); $u = uid();

  // current EF state
  $q = $pdo->prepare('SELECT total, target_amount, currency FROM emergency_fund WHERE user_id=?');
  $q->execute([$u]);
  $ef = $q->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0, 'target_amount'=>0, 'currency'=>fx_user_main($pdo,$u) ?: 'HUF'];

  $ef_cur   = strtoupper($ef['currency'] ?: (fx_user_main($pdo,$u) ?: 'HUF'));
  $ef_total = (float)($ef['total'] ?? 0);
  $ef_target= (float)($ef['target_amount'] ?? 0);
  $main     = fx_user_main($pdo,$u) ?: $ef_cur;

  // ---- Suggestions: monthly scheduled "bills" total ----
  // We'll expand all user scheduled_payments for the NEXT full calendar month
  $u = uid();
  $firstNext = date('Y-m-01', strtotime('first day of next month'));
  $lastNext  = date('Y-m-t', strtotime($firstNext));

  $sp = $pdo->prepare("
    SELECT id, title, amount, currency, next_due, rrule
    FROM scheduled_payments
    WHERE user_id = ?
  ");
  $sp->execute([$u]);

  $scheduledMonthlyEF  = 0.0; // in EF (target) currency
  $scheduledMonthlyMain= 0.0; // also compute in main for display if needed

  foreach ($sp as $s) {
    $amt = (float)$s['amount'];
    $cur = strtoupper($s['currency'] ?: $ef_cur);
    $dtstart = $s['next_due'];       // DTSTART for the RRULE
    $rr = trim($s['rrule'] ?? '');

    // expand into the next month range
    $dates = rrule_expand($dtstart, $rr, $firstNext, $lastNext); // array of 'Y-m-d'
    if (!$dates || !count($dates)) continue;

    foreach ($dates as $due) {
      // convert each occurrence to EF currency at that due date
      $amtEF   = ($cur === $ef_cur) ? $amt : fx_convert($pdo, $amt, $cur, $ef_cur, $due);
      $amtMain = ($cur === $main)   ? $amt : fx_convert($pdo, $amt, $cur, $main,   $due);
      $scheduledMonthlyEF   += $amtEF;
      $scheduledMonthlyMain += $amtMain;
    }
  }

  // Alias scheduled totals to the names used in suggestions + view
  $monthlyNeedsEF   = (float)$scheduledMonthlyEF;
  $monthlyNeedsMain = (float)$scheduledMonthlyMain;

  // --- prerequisites already available in your action ---
  /*
  $ef_cur             = (string)$ef['currency'] or user's main;
  $main               = fx_user_main($pdo, uid());
  $ef_target          = (float)($ef['target_amount'] ?? 0);       // in EF currency
  $ef_total           = (float)($ef['total'] ?? 0);               // in EF currency
  $monthlyNeedsEF     = (float)$scheduledMonthlyEF;               // from earlier step (EF currency)
  $monthlyNeedsMain   = (float)$scheduledMonthlyMain;             // (main currency)
  */

  // Today for FX
  $today = date('Y-m-d');

  // 1) First milestone = ~1000 USD in EF currency + in main
  $usd1kEF   = fx_convert($pdo, 1000.0, 'USD', $ef_cur,  $today);
  $usd1kMain = fx_convert($pdo, 1000.0, 'USD', $main,    $today);

  // 2) Decide which suggestion to show (or none)
  $suggest = null;

  // Helper: is target “roughly” equal to 1000 USD-equivalent?
  $approx = function(float $x, float $y): bool {
    if ($x <= 0 || $y <= 0) return false;
    return (abs($x - $y) / max($x, $y)) <= 0.15; // within 15%
  };

  // Case A: No target set (or zero/negative) -> suggest $1k
  if ($ef_target <= 0) {
    $suggest = [
      'amount_ef'     => $usd1kEF,
      'amount_main'   => $usd1kMain,
      'currency_ef'   => $ef_cur,
      'currency_main' => $main,
      'label'         => 'First milestone',
      'desc'          => '≈ $1,000 starter cushion'
    ];
  }
  // Case B: Target set but not achieved -> no suggestion
  elseif ($ef_total < $ef_target) {
    $suggest = null;
  }
  // Case C: Target achieved -> compute “next milestone”
  else {
    // If first milestone (~$1k) was achieved -> suggest 3 months of needs
    if ($approx($ef_target, $usd1kEF) && $monthlyNeedsEF > 0) {
      $months = 3;
    } else {
      // Otherwise, step to the next +1 month over current months target
      // Infer current months from target:
      if ($monthlyNeedsEF > 0) {
        $asMonths = (int)floor(($ef_target / $monthlyNeedsEF) + 0.00001);
        $months = max(4, $asMonths + 1); // next month; ensure it progresses beyond 3
      } else {
        $months = null; // cannot suggest months without any scheduled “needs”
      }
    }

    if ($months !== null && $months > 0 && $monthlyNeedsEF > 0) {
      // cap EF goals at 9 months
      if ($months > 9) {
          $suggest = [
              'done' => true,
              'label' => "You're good now",
              'desc'  => "You already have at least 9 months saved. Focus on investments 🚀"
          ];
      } else {
          $amtEF   = $monthlyNeedsEF   * $months;
          $amtMain = $monthlyNeedsMain * $months;
          $suggest = [
              'amount_ef'     => $amtEF,
              'amount_main'   => $amtMain,
              'currency_ef'   => $ef_cur,
              'currency_main' => $main,
              'label'         => "{$months} months of needs",
              'desc'          => "{$months}× your scheduled bills (run-rate)"
          ];
      }
    } else {
        $suggest = null;
    }

  }

  // show main equivalents if target != main, using today’s rate for the TARGET ONLY (spec says: target uses current FX)
  $today = date('Y-m-d');
  $target_main = ($ef_cur === $main) ? $ef_target : fx_convert($pdo, $ef_target, $ef_cur, $main, $today);
  $total_main  = ($ef_cur === $main) ? $ef_total  : fx_convert($pdo, $ef_total,  $ef_cur, $main, $today);

  // transactions (latest first)
  $tx = $pdo->prepare("
    SELECT id, occurred_on, kind, amount_native, currency_native, amount_main, main_currency, note
    FROM emergency_fund_tx
    WHERE user_id = ?
    ORDER BY occurred_on DESC, id DESC
    LIMIT 200
  ");
  $tx->execute([$u]);
  $rows = $tx->fetchAll(PDO::FETCH_ASSOC);

  // currencies for selects
  $uc = $pdo->prepare("SELECT code,is_main FROM user_currencies WHERE user_id=? ORDER BY is_main DESC, code");
  $uc->execute([$u]);
  $userCurrencies = $uc->fetchAll(PDO::FETCH_ASSOC) ?: [['code'=>'HUF','is_main'=>true]];

  view('emergency/index', compact(
    'ef','ef_cur','ef_total','ef_target','main','target_main','total_main','rows','userCurrencies',
    'scheduledMonthlyEF','scheduledMonthlyMain','usd1kEF','usd1kMain','suggest','monthlyNeedsEF','monthlyNeedsMain'
  ));
}

function emergency_set_target(PDO $pdo){
  verify_csrf(); require_login(); $u = uid();

  $target   = (float)($_POST['target_amount'] ?? 0);
  $currency = strtoupper(trim($_POST['currency'] ?? ''));
  if ($currency === '') $currency = fx_user_main($pdo,$u) ?: 'HUF';

  // initialize row if missing
  $pdo->prepare("
    INSERT INTO emergency_fund (user_id, total, target_amount, currency)
    VALUES (?, 0, ?, ?)
    ON CONFLICT (user_id)
    DO UPDATE SET target_amount = EXCLUDED.target_amount, currency = EXCLUDED.currency
  ")->execute([$u,$target,$currency]);

  $_SESSION['flash'] = 'Emergency fund target saved.';
  redirect('/emergency');
}

function emergency_add(PDO $pdo){
  verify_csrf(); require_login(); $u = uid();

  $amount = (float)($_POST['amount'] ?? 0);
  $date   = $_POST['occurred_on'] ?: date('Y-m-d');
  $note   = trim($_POST['note'] ?? '');
  if ($amount <= 0) { $_SESSION['flash']='Amount must be positive.'; redirect('/emergency'); }

  // EF & main currencies
  $row = $pdo->prepare('SELECT currency FROM emergency_fund WHERE user_id=?');
  $row->execute([$u]); $efCur = $row->fetchColumn() ?: (fx_user_main($pdo,$u) ?: 'HUF');
  $main = fx_user_main($pdo,$u) ?: $efCur;

  // FX snapshot
  $amtMain = ($efCur === $main) ? $amount : fx_convert($pdo, $amount, $efCur, $main, $date);
  $one     = ($efCur === $main) ? 1.0     : fx_convert($pdo, 1, $efCur, $main, $date);
  $rate    = $one > 0 ? $one : ($amtMain / max($amount, 1e-9));

  require_once __DIR__.'/../helpers_ef.php';
  $cats = ef_ensure_categories($pdo, $u); // returns ['ef_add'=>id, 'ef_withdraw'=>id]

  $pdo->beginTransaction();
  try{
    // 1) EF ledger row
    $ins = $pdo->prepare("
      INSERT INTO emergency_fund_tx
        (user_id, occurred_on, kind, amount_native, currency_native, amount_main, main_currency, rate_used, note)
      VALUES (?,?,?,?,?,?,?,?,?)
      RETURNING id
    ");
    $ins->execute([$u,$date,'add',$amount,$efCur,$amtMain,$main,$rate,$note]);
    $efTxId = (int)$ins->fetchColumn();

    // 2) Increase EF total (in EF currency)
    $pdo->prepare("
      INSERT INTO emergency_fund(user_id,total,target_amount,currency)
      VALUES(?, ?, 0, ?)
      ON CONFLICT (user_id)
      DO UPDATE SET total = emergency_fund.total + EXCLUDED.total,
                    currency = EXCLUDED.currency
    ")->execute([$u,$amount,$efCur]);

    // 3) Mirror into transactions as SPENDING (money leaves wallet to EF)
    $txNote = $note !== '' ? $note : 'Emergency Fund contribution';
    $tins = $pdo->prepare("
      INSERT INTO transactions
        (user_id, occurred_on, kind, amount, currency, category_id, note, source, source_ref_id, locked, ef_tx_id)
      VALUES (?, ?, 'spending', ?, ?, ?, ?, 'ef', ?, TRUE, ?)
    ");
    $tins->execute([$u, $date, $amount, $efCur, (int)$cats['ef_add'], $txNote, $efTxId, $efTxId]);

    $pdo->commit();
    $_SESSION['flash']='Money added.';
  } catch(Throwable $e){
    $pdo->rollBack();
    $_SESSION['flash']='Could not add.';
  }
  redirect('/emergency');
}

function emergency_withdraw(PDO $pdo){
  verify_csrf(); require_login(); $u = uid();

  $amount = (float)($_POST['amount'] ?? 0);
  $date   = $_POST['occurred_on'] ?: date('Y-m-d');
  $note   = trim($_POST['note'] ?? '');
  if ($amount <= 0) { $_SESSION['flash']='Amount must be positive.'; redirect('/emergency'); }

  $row = $pdo->prepare('SELECT currency FROM emergency_fund WHERE user_id=?');
  $row->execute([$u]); $efCur = $row->fetchColumn() ?: (fx_user_main($pdo,$u) ?: 'HUF');
  $main = fx_user_main($pdo,$u) ?: $efCur;

  $amtMain = ($efCur === $main) ? $amount : fx_convert($pdo, $amount, $efCur, $main, $date);
  $one     = ($efCur === $main) ? 1.0     : fx_convert($pdo, 1, $efCur, $main, $date);
  $rate    = $one > 0 ? $one : ($amtMain / max($amount, 1e-9));

  require_once __DIR__.'/../helpers_ef.php';
  $cats = ef_ensure_categories($pdo, $u);

  $pdo->beginTransaction();
  try{
    // 1) EF ledger row
    $ins = $pdo->prepare("
      INSERT INTO emergency_fund_tx
        (user_id, occurred_on, kind, amount_native, currency_native, amount_main, main_currency, rate_used, note)
      VALUES (?,?,?,?,?,?,?,?,?)
      RETURNING id
    ");
    $ins->execute([$u,$date,'withdraw',$amount,$efCur,$amtMain,$main,$rate,$note]);
    $efTxId = (int)$ins->fetchColumn();

    // 2) Decrease EF total
    $pdo->prepare("UPDATE emergency_fund SET total = GREATEST(0, COALESCE(total,0) - ?) WHERE user_id=?")
        ->execute([$amount,$u]);

    // 3) Mirror into transactions as INCOME (money returns from EF)
    $txNote = $note !== '' ? $note : 'Emergency Fund withdrawal';
    $tins = $pdo->prepare("
      INSERT INTO transactions
        (user_id, occurred_on, kind, amount, currency, category_id, note, source, source_ref_id, locked, ef_tx_id)
      VALUES (?, ?, 'income', ?, ?, ?, ?, 'ef', ?, TRUE, ?)
    ");
    $tins->execute([$u, $date, $amount, $efCur, (int)$cats['ef_withdraw'], $txNote, $efTxId, $efTxId]);

    $pdo->commit();
    $_SESSION['flash']='Withdrawal recorded.';
  } catch(Throwable $e){
    $pdo->rollBack();
    $_SESSION['flash']='Could not withdraw.';
  }
  redirect('/emergency');
}

function emergency_tx_delete(PDO $pdo){
  verify_csrf(); require_login(); $u = uid();
  $id = (int)($_POST['id'] ?? 0);
  if (!$id) redirect('/emergency');

  $row = $pdo->prepare("SELECT kind, amount_native FROM emergency_fund_tx WHERE id=? AND user_id=?");
  $row->execute([$id,$u]); $tx = $row->fetch(PDO::FETCH_ASSOC);
  if (!$tx) redirect('/emergency');

  $pdo->beginTransaction();
  try{
    // Reverse EF total
    if ($tx['kind'] === 'add') {
      $pdo->prepare("UPDATE emergency_fund SET total = GREATEST(0, COALESCE(total,0) - ?) WHERE user_id=?")
          ->execute([(float)$tx['amount_native'],$u]);
    } else {
      $pdo->prepare("UPDATE emergency_fund SET total = COALESCE(total,0) + ? WHERE user_id=?")
          ->execute([(float)$tx['amount_native'],$u]);
    }

    // Delete mirrored transaction(s) by either linkage
    $pdo->prepare("
      DELETE FROM transactions
      WHERE user_id=? AND (ef_tx_id = ? OR (source='ef' AND source_ref_id = ?))
    ")->execute([$u,$id,$id]);

    // Delete EF entry
    $pdo->prepare("DELETE FROM emergency_fund_tx WHERE id=? AND user_id=?")->execute([$id,$u]);

    $pdo->commit();
    $_SESSION['flash']='Entry removed.';
  } catch(Throwable $e){
    $pdo->rollBack();
    $_SESSION['flash']='Could not delete entry.';
  }
  redirect('/emergency');
}
