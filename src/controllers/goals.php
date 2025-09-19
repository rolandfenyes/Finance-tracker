<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../fx.php';

function goals_index(PDO $pdo){
  require_login(); $u = uid();

  // goals + linked schedule (if any)
  $q = $pdo->prepare("
    SELECT g.*,
           sp.id    AS sched_id,
           sp.title AS sched_title,
           sp.amount AS sched_amount,
           sp.currency AS sched_currency,
           sp.next_due AS sched_next_due,
           sp.rrule    AS sched_rrule
      FROM goals g
      LEFT JOIN scheduled_payments sp
             ON sp.goal_id = g.id AND sp.user_id = g.user_id
     WHERE g.user_id=?
     ORDER BY (g.status='active') DESC, lower(g.title)
  ");
  $q->execute([$u]);
  $rows = $q->fetchAll();

  // user currencies for selects
  $uc = $pdo->prepare("SELECT code,is_main FROM user_currencies WHERE user_id=? ORDER BY is_main DESC, code");
  $uc->execute([$u]);
  $userCurrencies = $uc->fetchAll(PDO::FETCH_ASSOC) ?: [['code'=>'HUF','is_main'=>true]];

  // existing schedules to pick from (not already linked to a loan/goal if you prefer)
  $sp = $pdo->prepare("
    SELECT id,title,amount,currency,next_due,rrule
      FROM scheduled_payments
     WHERE user_id=? AND goal_id IS NULL
     ORDER BY lower(title)
  ");
  $sp->execute([$u]);
  $scheduledList = $sp->fetchAll();

  // categories (optional; many people tag goal contributions)
  $cs = $pdo->prepare("SELECT id,label FROM categories WHERE user_id=? ORDER BY lower(label)");
  $cs->execute([$u]);
  $categories = $cs->fetchAll();

  view('goals/index', compact('rows','userCurrencies','scheduledList','categories'));
}

/** Create a goal */
function goals_add(PDO $pdo){
  verify_csrf(); require_login(); $u=uid();

  $title   = trim($_POST['title'] ?? '');
  $target  = (float)($_POST['target_amount'] ?? 0);
  $current = (float)($_POST['current_amount'] ?? 0);
  $currency= strtoupper(trim($_POST['currency'] ?? 'HUF'));
  $status  = in_array($_POST['status'] ?? 'active', ['active','paused','done'], true) ? $_POST['status'] : 'active';

  if ($title==='') redirect('/goals');

  $pdo->prepare("INSERT INTO goals(user_id,title,target_amount,current_amount,currency,status)
                 VALUES (?,?,?,?,?,?)")
      ->execute([$u,$title,$target,$current,$currency,$status]);

  $_SESSION['flash'] = 'Goal added.';
  redirect('/goals');
}

/** Edit base goal fields */
function goals_edit(PDO $pdo){
  verify_csrf(); require_login(); $u=uid();

  $id       = (int)($_POST['id'] ?? 0);
  if (!$id) redirect('/goals');

  $title    = trim($_POST['title'] ?? '');
  $target   = (float)($_POST['target_amount'] ?? 0);
  $currency = strtoupper(trim($_POST['currency'] ?? 'HUF'));
  $status   = in_array($_POST['status'] ?? 'active', ['active','paused','done'], true) ? $_POST['status'] : 'active';

  $pdo->prepare("UPDATE goals SET title=?, target_amount=?, currency=?, status=? WHERE id=? AND user_id=?")
      ->execute([$title,$target,$currency,$status,$id,$u]);

  $_SESSION['flash'] = 'Goal updated.';
  redirect('/goals');
}

function goals_delete(PDO $pdo){
  verify_csrf(); require_login(); $u = uid();
  $id = (int)($_POST['id'] ?? 0);
  if (!$id) redirect('/goals');

  $pdo->beginTransaction();
  try {
    // Unlink any schedule pointing at this goal (keeps schedules intact)
    $pdo->prepare("UPDATE scheduled_payments SET goal_id=NULL WHERE goal_id=? AND user_id=?")
        ->execute([$id,$u]);

    // Delete goal (goal_transactions has ON DELETE CASCADE)
    $pdo->prepare("DELETE FROM goals WHERE id=? AND user_id=?")->execute([$id,$u]);

    $pdo->commit();
    $_SESSION['flash'] = 'Goal deleted.';
  } catch(Throwable $e){
    $pdo->rollBack();
    $_SESSION['flash'] = 'Failed to delete goal.';
  }
  redirect('/goals');
}

function goals_create_schedule(PDO $pdo){
  verify_csrf(); require_login();
  $u = uid();

  // Accept BOTH the new names (your form) and the older sched_* names
  $goalId   = (int)($_POST['goal_id'] ?? 0);

  // Title: use provided or fallback to "Goal: <name>"
  $title = trim($_POST['title'] ?? $_POST['sched_title'] ?? '');
  if ($title === '') {
    $t = $pdo->prepare('SELECT name FROM goals WHERE id=? AND user_id=?');
    $t->execute([$goalId, $u]);
    $gname = $t->fetchColumn();
    $title = $gname ? ('Goal: ' . $gname) : 'Goal contribution';
  }

  // Amount / currency / first due
  $amount   = (float)($_POST['amount'] ?? $_POST['sched_amount'] ?? 0);
  $currency = strtoupper(trim($_POST['currency'] ?? $_POST['sched_currency'] ?? ''));
  $nextDue  = $_POST['next_due'] ?? $_POST['sched_next_due'] ?? '';

  // Due day → RRULE (monthly)
  $dueDayIn = $_POST['due_day'] ?? null;
  $dueDay   = $dueDayIn !== null && $dueDayIn !== '' ? (int)$dueDayIn : null;

  // Optional: category from form (if you add a select later)
  $catId = ($_POST['category_id'] ?? $_POST['sched_category_id'] ?? '') !== '' ? (int)($_POST['category_id'] ?? $_POST['sched_category_id']) : null;

  // If a raw RRULE was provided (advanced), honor it; else build a simple monthly one
  $rrule = trim($_POST['rrule'] ?? $_POST['sched_rrule'] ?? '');
  if ($rrule === '') {
    // Build FREQ=MONTHLY;BYMONTHDAY=<dueDay or day(nextDue)>
    $bymd = $dueDay ?: ( ($nextDue && preg_match('/\d{4}-\d{2}-\d{2}/',$nextDue)) ? (int)substr($nextDue,8,2) : null );
    if ($bymd === null || $bymd < 1 || $bymd > 31) $bymd = 1;
    $rrule = "FREQ=MONTHLY;BYMONTHDAY={$bymd}";
    // If you want a finite series, you could also add COUNT or UNTIL here based on goal settings.
  }

  // Fallback currency to user main
  if ($currency === '') {
    $currency = function_exists('fx_user_main') ? (fx_user_main($pdo, $u) ?: 'HUF') : 'HUF';
  }

  // Validate basics
  if ($amount <= 0 || !$nextDue) {
    $_SESSION['flash'] = 'Amount and first payment date are required.';
    redirect('/goals');
  }

  // Validate ownerships
  if ($goalId) {
    $g = $pdo->prepare('SELECT 1 FROM goals WHERE id=? AND user_id=?');
    $g->execute([$goalId,$u]);
    if (!$g->fetch()){ $_SESSION['flash']='Goal not found.'; redirect('/goals'); }
  }
  if ($catId !== null) {
    $c = $pdo->prepare('SELECT 1 FROM categories WHERE id=? AND user_id=?');
    $c->execute([$catId,$u]);
    if (!$c->fetch()) $catId = null;
  }

  // Insert schedule
  try {
    $stmt = $pdo->prepare(
      'INSERT INTO scheduled_payments(user_id,title,amount,currency,next_due,rrule,category_id,goal_id)
       VALUES (?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([$u,$title,$amount,$currency,$nextDue,$rrule,$catId,$goalId]);

    $_SESSION['flash'] = 'Schedule created.';
    redirect('/scheduled'); // or redirect('/goals') if you prefer to stay on Goals
  } catch (Throwable $e) {
    // throw $e; // uncomment locally to see why
    $_SESSION['flash'] = 'Could not create schedule.';
    redirect('/goals');
  }
}

function goals_link_schedule(PDO $pdo){
  verify_csrf(); require_login();
  $u = uid();

  $goalId = (int)($_POST['goal_id'] ?? 0);
  $schedId= (int)($_POST['scheduled_payment_id'] ?? 0);

  // Ensure both belong to user
  $g = $pdo->prepare('SELECT 1 FROM goals WHERE id=? AND user_id=?'); $g->execute([$goalId,$u]);
  $s = $pdo->prepare('SELECT 1 FROM scheduled_payments WHERE id=? AND user_id=?'); $s->execute([$schedId,$u]);
  if (!$g->fetch() || !$s->fetch()) { $_SESSION['flash'] = 'Invalid link.'; redirect('/goals'); }

  $pdo->prepare('UPDATE scheduled_payments SET goal_id=? WHERE id=? AND user_id=?')
      ->execute([$goalId,$schedId,$u]);

  $_SESSION['flash'] = 'Schedule linked to goal.';
  redirect('/goals');
}

function goals_tx_add(PDO $pdo){
  verify_csrf(); require_login();
  $u = uid();

  $goalId    = (int)($_POST['goal_id'] ?? 0);
  $amount    = (float)($_POST['amount'] ?? 0);
  $currency  = strtoupper(trim($_POST['currency'] ?? ''));     // native of this contribution (from form)
  $occurred  = $_POST['occurred_on'] ?: date('Y-m-d');
  $note      = trim($_POST['note'] ?? '');

  if (!$goalId || $amount <= 0 || !$occurred){
    $_SESSION['flash'] = 'Goal, amount and date are required.';
    redirect('/goals');
  }

  // Load goal + its currency and ownership
  $g = $pdo->prepare('SELECT id, user_id, currency, COALESCE(current_amount,0) AS cur
                      FROM goals WHERE id=? AND user_id=?');
  $g->execute([$goalId,$u]);
  $goal = $g->fetch(PDO::FETCH_ASSOC);
  if (!$goal){
    $_SESSION['flash'] = 'Goal not found.';
    redirect('/goals');
  }

  // Fallback contribution currency to goal currency or user main
  if ($currency === ''){
    $currency = $goal['currency'] ?: (function_exists('fx_user_main') ? fx_user_main($pdo, $u) : 'HUF');
  }

  $goalCur = $goal['currency'] ?: $currency;

  $pdo->beginTransaction();
  try {
    // 1) insert row (store the native currency/amount the user entered)
    $ins = $pdo->prepare('INSERT INTO goal_contributions(goal_id,user_id,amount,currency,occurred_on,note)
                          VALUES (?,?,?,?,?,?)');
    $ins->execute([$goalId,$u,$amount,$currency,$occurred,$note]);

    // 2) convert to goal currency and bump current_amount
    $delta = ($currency === $goalCur)
      ? $amount
      : fx_convert($pdo, $amount, $currency, $goalCur, $occurred);

    // Guard against nulls
    $pdo->prepare('UPDATE goals SET current_amount = COALESCE(current_amount,0) + ?,
                                   currency = COALESCE(currency, ?)
                     WHERE id=? AND user_id=?')
        ->execute([$delta, $goalCur, $goalId, $u]);

    $pdo->commit();
    $_SESSION['flash'] = 'Contribution added.';
  } catch(Throwable $e){
    $pdo->rollBack();
    // throw $e; // uncomment to debug
    $_SESSION['flash'] = 'Could not add contribution.';
  }

  redirect('/goals');
}

function goals_tx_delete(PDO $pdo){
  verify_csrf(); require_login();
  $u = uid();
  $id = (int)($_POST['id'] ?? 0);
  if (!$id){ redirect('/goals'); }

  // Fetch row + goal for reverse update
  $q = $pdo->prepare('SELECT gc.id, gc.goal_id, gc.amount, gc.currency, gc.occurred_on,
                             g.currency AS goal_currency
                      FROM goal_contributions gc
                      JOIN goals g ON g.id=gc.goal_id
                      WHERE gc.id=? AND g.user_id=?');
  $q->execute([$id,$u]);
  $row = $q->fetch(PDO::FETCH_ASSOC);
  if (!$row){ redirect('/goals'); }

  $pdo->beginTransaction();
  try {
    // reverse the bump
    $delta = ($row['currency'] === ($row['goal_currency'] ?: $row['currency']))
      ? $row['amount']
      : fx_convert($pdo, (float)$row['amount'], $row['currency'], $row['goal_currency'], $row['occurred_on']);

    $pdo->prepare('UPDATE goals SET current_amount = GREATEST(0, COALESCE(current_amount,0) - ?) WHERE id=? AND user_id=?')
        ->execute([$delta, (int)$row['goal_id'], $u]);

    $pdo->prepare('DELETE FROM goal_contributions WHERE id=?')->execute([$id]);

    $pdo->commit();
    $_SESSION['flash'] = 'Contribution removed.';
  } catch(Throwable $e){
    $pdo->rollBack();
    $_SESSION['flash'] = 'Could not remove contribution.';
  }
  redirect('/goals');
}
