<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../fx.php';
require_once __DIR__ . '/../services/email_notifications.php';
require_once __DIR__ . '/../scheduled_runner.php';

function goal_row_is_completed(array $goal): bool {
  $statusKey = strtolower((string)($goal['status'] ?? ''));
  $targetAmount = max(0.0, (float)($goal['target_amount'] ?? 0));
  $currentAmount = max(0.0, (float)($goal['current_amount'] ?? 0));

  if (in_array($statusKey, ['done', 'completed'], true)) {
    return true;
  }

  return $targetAmount > 0.0 && $currentAmount >= $targetAmount;
}

function goal_row_is_locked(array $goal): bool {
  return !empty($goal['archived_at']);
}

function goals_index(PDO $pdo){
  require_login(); $u = uid();

  // Each goal + its linked schedule (latest by next_due/id)
  $q = $pdo->prepare("
    SELECT g.*,
          sp.id        AS sched_id,
          sp.title     AS sched_title,
          sp.amount    AS sched_amount,
          sp.currency  AS sched_currency,
          sp.next_due  AS sched_next_due,
          sp.rrule     AS sched_rrule
    FROM goals g
    LEFT JOIN LATERAL (
      SELECT id, title, amount, currency, next_due, rrule
      FROM scheduled_payments s
      WHERE s.user_id = g.user_id AND s.goal_id = g.id
      ORDER BY s.next_due DESC NULLS LAST, id DESC
      LIMIT 1
    ) sp ON TRUE
    WHERE g.user_id = ?
    ORDER BY g.id DESC
  ");

  $q->execute([$u]);
  $rows = $q->fetchAll(PDO::FETCH_ASSOC);

  $allGoals = $rows;

  // Only schedules that are free (not linked to any loan or goal) OR already linked to this record
  $sp = $pdo->prepare("
    SELECT id, title, amount, currency, next_due, rrule, loan_id, goal_id
    FROM scheduled_payments
    WHERE user_id = ?
      AND archived_at IS NULL
      AND (loan_id IS NULL AND goal_id IS NULL AND investment_id IS NULL) -- free ones
    ORDER BY lower(title)
  ");
  $sp->execute([$u]);
  $scheduledList = $sp->fetchAll(PDO::FETCH_ASSOC);

  // Manual contributions for each goal (ordered newest first)
  $gc = $pdo->prepare("SELECT gc.id, gc.goal_id, gc.amount, gc.currency, gc.occurred_on, gc.note
                         FROM goal_contributions gc
                         JOIN goals g ON g.id = gc.goal_id AND g.user_id = ?
                        ORDER BY gc.occurred_on DESC, gc.id DESC");
  $gc->execute([$u]);
  $goalTransactions = [];
  foreach ($gc->fetchAll(PDO::FETCH_ASSOC) as $tx) {
    $goalId = (int)($tx['goal_id'] ?? 0);
    if (!$goalId) { continue; }
    $goalTransactions[$goalId][] = $tx;
  }

  foreach ($allGoals as &$goalRow) {
    $isCompleted = goal_row_is_completed($goalRow);

    $goalRow['_is_completed'] = $isCompleted;
    $goalRow['_can_archive'] = $isCompleted && empty($goalRow['archived_at']);

    $goalId = (int)($goalRow['id'] ?? 0);
    $latestContribution = $goalId && !empty($goalTransactions[$goalId])
      ? $goalTransactions[$goalId][0]
      : null;

    $noteRaw = $latestContribution ? trim((string)($latestContribution['note'] ?? '')) : '';
    $completedBySchedule = $isCompleted
      && empty($goalRow['archived_at'])
      && !empty($goalRow['sched_id'])
      && $noteRaw !== ''
      && stripos($noteRaw, 'scheduled:') === 0;

    $goalRow['_completed_by_schedule'] = $completedBySchedule;
  }
  unset($goalRow);

  $activeGoals = [];
  $archivedGoals = [];
  foreach ($allGoals as $goalRow) {
    if (!empty($goalRow['archived_at'])) {
      $archivedGoals[] = $goalRow;
    } else {
      $activeGoals[] = $goalRow;
    }
  }

  // currencies (if you show currency selectors on the page)
  $uc = $pdo->prepare("SELECT code,is_main FROM user_currencies WHERE user_id=? ORDER BY is_main DESC, code");
  $uc->execute([$u]);
  $userCurrencies = $uc->fetchAll(PDO::FETCH_ASSOC) ?: [['code'=>'HUF','is_main'=>true]];

  $cs = $pdo->prepare("SELECT id,label,COALESCE(NULLIF(color,''),'#6B7280') AS color
                        FROM categories WHERE user_id=? ORDER BY lower(label)");
  $cs->execute([$u]);
  $categories = $cs->fetchAll(PDO::FETCH_ASSOC);

  view('goals/index', compact('activeGoals','archivedGoals','allGoals','scheduledList','userCurrencies','categories','goalTransactions'));
}

/** Create a goal */
function goals_add(PDO $pdo){
  verify_csrf(); require_login(); $u=uid();

  free_user_limit_guard(
    $pdo,
    'goals_active',
    '/goals',
    __('Your current plan cannot add more active goals. Update the role capabilities to increase this limit.')
  );

  $title   = trim($_POST['title'] ?? '');
  $target  = (float)($_POST['target_amount'] ?? 0);
  $current = (float)($_POST['current_amount'] ?? 0);
  $currency= strtoupper(trim($_POST['currency'] ?? 'HUF'));
  $status  = in_array($_POST['status'] ?? 'active', ['active','paused','done'], true) ? $_POST['status'] : 'active';

  if ($title==='') redirect('/goals');

  $archivedAt = $status === 'done' ? date('Y-m-d H:i:s') : null;

  $pdo->prepare("INSERT INTO goals(user_id,title,target_amount,current_amount,currency,status,archived_at)
                 VALUES (?,?,?,?,?,?,?)")
      ->execute([$u,$title,$target,$current,$currency,$status,$archivedAt]);

  $_SESSION['flash'] = 'Goal added.';
  redirect('/goals');
}

/** Edit base goal fields */
function goals_edit(PDO $pdo){
  verify_csrf(); require_login(); $u=uid();

  $id       = (int)($_POST['id'] ?? 0);
  if (!$id) redirect('/goals');

  $existing = $pdo->prepare('SELECT title, target_amount, current_amount, currency, status, archived_at FROM goals WHERE id=? AND user_id=?');
  $existing->execute([$id,$u]);
  $previous = $existing->fetch(PDO::FETCH_ASSOC);
  if (!$previous) {
    redirect('/goals');
  }

  if (!empty($previous['archived_at'])) {
    $_SESSION['flash'] = 'This goal has been archived and cannot be edited.';
    redirect('/goals');
    return;
  }

  if (goal_row_is_locked($previous)) {
    $_SESSION['flash'] = 'This goal is finished and cannot be edited.';
    redirect('/goals');
    return;
  }

  $title    = trim($_POST['title'] ?? '');
  $target   = (float)($_POST['target_amount'] ?? 0);
  $currency = strtoupper(trim($_POST['currency'] ?? 'HUF'));
  $status   = in_array($_POST['status'] ?? 'active', ['active','paused','done'], true) ? $_POST['status'] : 'active';

  $pdo->prepare("UPDATE goals
                    SET title=?,
                        target_amount=?,
                        currency=?,
                        status=?,
                        archived_at = CASE WHEN ? = 'done' THEN COALESCE(archived_at, CURRENT_TIMESTAMP) ELSE NULL END
                  WHERE id=? AND user_id=?")
      ->execute([$title,$target,$currency,$status,$status,$id,$u]);

  $_SESSION['flash'] = 'Goal updated.';
  email_maybe_send_goal_completion($pdo, $u, $id, $previous);
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

function goals_archive(PDO $pdo){
  verify_csrf(); require_login(); $u = uid();

  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) {
    redirect('/goals');
    return;
  }

  $goalStmt = $pdo->prepare('SELECT id, title, target_amount, current_amount, currency, status, archived_at FROM goals WHERE id=? AND user_id=?');
  $goalStmt->execute([$id, $u]);
  $goal = $goalStmt->fetch(PDO::FETCH_ASSOC);

  if (!$goal) {
    redirect('/goals');
    return;
  }

  if (!empty($goal['archived_at'])) {
    $_SESSION['flash'] = 'Goal already archived.';
    redirect('/goals');
    return;
  }

  $statusKey = strtolower((string)($goal['status'] ?? ''));
  $targetAmount = max(0.0, (float)($goal['target_amount'] ?? 0));
  $currentAmount = max(0.0, (float)($goal['current_amount'] ?? 0));
  $isCompleted = in_array($statusKey, ['done', 'completed'], true) || ($targetAmount > 0.0 && $currentAmount >= $targetAmount);

  if (!$isCompleted) {
    $_SESSION['flash'] = 'This goal is not finished yet.';
    redirect('/goals');
    return;
  }

  $pdo->beginTransaction();
  try {
    $today = date('Y-m-d');
    $goalCurrency = strtoupper((string)($goal['currency'] ?? ''));
    if ($goalCurrency === '') {
      $goalCurrency = function_exists('fx_user_main') ? (fx_user_main($pdo, $u) ?: 'HUF') : 'HUF';
    }

    $scheduleStmt = $pdo->prepare('SELECT * FROM scheduled_payments WHERE goal_id = ? AND user_id = ? FOR UPDATE');
    $scheduleStmt->execute([$id, $u]);
    $schedules = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);

    $checkStmt = $pdo->prepare('SELECT 1 FROM goal_contributions WHERE goal_id = ? AND user_id = ? AND occurred_on = ? AND note = ? AND amount = ? AND currency = ? LIMIT 1');
    $goalNeedsRefresh = false;

    foreach ($schedules as &$scheduleRow) {
      $due = isset($scheduleRow['next_due']) ? (string)$scheduleRow['next_due'] : '';
      if ($due === '' || $due > $today) {
        continue;
      }

      $rruleString = trim((string)($scheduleRow['rrule'] ?? ''));
      if ($rruleString === '') {
        continue;
      }

      $title = trim((string)($scheduleRow['title'] ?? 'Goal contribution'));
      $expectedNote = 'Scheduled: ' . $title;
      if ($expectedNote === 'Scheduled: ') {
        $expectedNote = 'Scheduled: ';
      }

      $expectedCurrency = strtoupper((string)($scheduleRow['currency'] ?? ''));
      if ($expectedCurrency === '') {
        $expectedCurrency = $goalCurrency;
        if ($expectedCurrency === '') {
          $expectedCurrency = function_exists('fx_user_main') ? (fx_user_main($pdo, $u) ?: 'HUF') : 'HUF';
        }
      }

      $rruleParts = rrule_parse($rruleString);
      $countRemaining = $rruleParts['COUNT'];
      $currentDue = $due;

      while ($currentDue !== null && $currentDue <= $today) {
        $checkStmt->execute([$id, $u, $currentDue, $expectedNote, (float)$scheduleRow['amount'], $expectedCurrency]);
        $alreadyExists = (bool)$checkStmt->fetchColumn();

        if (!$alreadyExists) {
          scheduled_apply_goal($pdo, $scheduleRow, $currentDue);
          $goalNeedsRefresh = true;
        }

        if ($countRemaining !== null) {
          $countRemaining--;
          if ($countRemaining <= 0) {
            $rruleParts['COUNT'] = null;
            $rruleString = scheduled_build_rrule($rruleParts);
            $scheduleRow['rrule'] = $rruleString;
            $scheduleRow['next_due'] = null;
            $currentDue = null;
            break;
          }
          $rruleParts['COUNT'] = $countRemaining;
          $rruleString = scheduled_build_rrule($rruleParts);
          $scheduleRow['rrule'] = $rruleString;
        }

        $nextDue = scheduled_next_occurrence($currentDue, $rruleString, $currentDue);
        if ($nextDue === null || $nextDue <= $currentDue) {
          $scheduleRow['next_due'] = null;
          $currentDue = null;
          break;
        }

        $scheduleRow['next_due'] = $nextDue;
        $currentDue = $nextDue;
      }
    }
    unset($scheduleRow);

    if ($goalNeedsRefresh) {
      $goalStmt->execute([$id, $u]);
      $goal = $goalStmt->fetch(PDO::FETCH_ASSOC) ?: $goal;
      $goalCurrency = strtoupper((string)($goal['currency'] ?? $goalCurrency));
      if ($goalCurrency === '') {
        $goalCurrency = function_exists('fx_user_main') ? (fx_user_main($pdo, $u) ?: 'HUF') : 'HUF';
      }
    }

    if ($goalCurrency === '') {
      $goalCurrency = function_exists('fx_user_main') ? (fx_user_main($pdo, $u) ?: 'HUF') : 'HUF';
    }

    $currentAmount = max(0.0, (float)($goal['current_amount'] ?? 0));
    $payoutAmount = round($currentAmount, 2);

    if ($payoutAmount > 0) {
      $note = trim('Goal completed · ' . (string)($goal['title'] ?? ''));
      if ($note === 'Goal completed ·') {
        $note = 'Goal completed';
      }

      $txStmt = $pdo->prepare('INSERT INTO transactions(user_id,kind,category_id,amount,currency,occurred_on,note,locked,source,source_ref_id)
                                VALUES (?,?,?,?,?,?,?,?,?,?)');
      $txStmt->execute([
        $u,
        'income',
        null,
        $payoutAmount,
        $goalCurrency,
        date('Y-m-d'),
        $note,
        true,
        'goal_archive',
        (int)$goal['id'],
      ]);
    }

    $pdo->prepare('UPDATE goals
                      SET archived_at = CURRENT_TIMESTAMP,
                          status = CASE WHEN status IN (\'done\', \'completed\') THEN status ELSE \'done\' END,
                          current_amount = CASE WHEN target_amount > 0 THEN GREATEST(target_amount, current_amount) ELSE current_amount END
                    WHERE id = ? AND user_id = ?')
        ->execute([$id, $u]);

    if (!empty($schedules)) {
      $updateSched = $pdo->prepare('UPDATE scheduled_payments SET rrule = ?, next_due = NULL, archived_at = NULL WHERE id = ? AND user_id = ?');
      foreach ($schedules as $rowToUpdate) {
        $updateSched->execute([$rowToUpdate['rrule'] ?? '', (int)$rowToUpdate['id'], $u]);
      }
    }

    $pdo->commit();
    $_SESSION['flash'] = 'Goal withdrawn and archived.';
  } catch (Throwable $e) {
    $pdo->rollBack();
    $_SESSION['flash'] = 'Failed to archive goal.';
  }

  redirect('/goals');
}

function goals_unarchive(PDO $pdo){
  verify_csrf(); require_login(); $u = uid();

  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) {
    redirect('/goals');
    return;
  }

  $goalStmt = $pdo->prepare('SELECT id, title, target_amount, current_amount, currency, status, archived_at FROM goals WHERE id=? AND user_id=?');
  $goalStmt->execute([$id, $u]);
  $goal = $goalStmt->fetch(PDO::FETCH_ASSOC);

  if (!$goal) {
    redirect('/goals');
    return;
  }

  if (empty($goal['archived_at'])) {
    $_SESSION['flash'] = 'Goal is already active.';
    redirect('/goals');
    return;
  }

  $pdo->beginTransaction();
  try {
    $payoutStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = ? AND source = 'goal_archive' AND source_ref_id = ?");
    $payoutStmt->execute([$u, $id]);
    $payoutAmount = max(0.0, (float)$payoutStmt->fetchColumn());

    if ($payoutAmount > 0) {
      $deleteTx = $pdo->prepare("DELETE FROM transactions WHERE user_id = ? AND source = 'goal_archive' AND source_ref_id = ?");
      $deleteTx->execute([$u, $id]);
    }

    $currentAmount = max(0.0, (float)($goal['current_amount'] ?? 0));
    $newAmount = $currentAmount;
    if ($payoutAmount > 0) {
      // Restore the goal's saved progress to its pre-archive amount instead of
      // wiping it out when we removed the archive payout transaction.
      $newAmount = max(0.0, $payoutAmount);
    }

    $rawStatus = (string)($goal['status'] ?? 'active');
    $statusKey = strtolower($rawStatus);
    $newStatus = in_array($statusKey, ['done', 'completed'], true) ? 'active' : $rawStatus;

    $update = $pdo->prepare('UPDATE goals SET archived_at = NULL, status = ?, current_amount = ? WHERE id = ? AND user_id = ?');
    $update->execute([$newStatus, $newAmount, $id, $u]);

    $pdo->commit();
    $_SESSION['flash'] = 'Goal unarchived. You can start saving again.';
  } catch (Throwable $e) {
    $pdo->rollBack();
    $_SESSION['flash'] = 'Failed to unarchive goal.';
  }

  redirect('/goals');
}

function goals_create_schedule(PDO $pdo){
  verify_csrf(); require_login();
  $u = uid();

  free_user_limit_guard(
    $pdo,
    'scheduled_active',
    '/scheduled',
    __('Your current plan cannot add more scheduled payments. Update the role capabilities to increase this limit.')
  );

  // Accept BOTH the new names (your form) and the older sched_* names
  $goalId   = (int)($_POST['goal_id'] ?? 0);

  // Title: use provided or fallback to "Goal: <name>"
  $title = trim($_POST['title'] ?? $_POST['sched_title'] ?? '');
  if ($title === '') {
    $t = $pdo->prepare('SELECT title FROM goals WHERE id=? AND user_id=?');
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
    $g = $pdo->prepare('SELECT archived_at, status, target_amount, current_amount FROM goals WHERE id=? AND user_id=?');
    $g->execute([$goalId,$u]);
    $goalMeta = $g->fetch(PDO::FETCH_ASSOC);
    if (!$goalMeta){ $_SESSION['flash']='Goal not found.'; redirect('/goals'); return; }
    if (!empty($goalMeta['archived_at'])) {
      $_SESSION['flash'] = 'This goal has been archived and cannot receive new schedules.';
      redirect('/goals');
      return;
    }
    if (goal_row_is_locked($goalMeta)) {
      $_SESSION['flash'] = 'This goal is finished and cannot receive new schedules.';
      redirect('/goals');
      return;
    }
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
  verify_csrf(); require_login(); $u = uid();

  $goalId = (int)($_POST['goal_id'] ?? 0);
  $spId   = (int)($_POST['scheduled_payment_id'] ?? 0);
  if (!$goalId || !$spId) { redirect('/goals'); }

  $goalMeta = $pdo->prepare('SELECT archived_at, status, target_amount, current_amount FROM goals WHERE id=? AND user_id=?');
  $goalMeta->execute([$goalId,$u]);
  $goalRow = $goalMeta->fetch(PDO::FETCH_ASSOC);
  if (!$goalRow) { redirect('/goals'); }
  if (!empty($goalRow['archived_at'])) {
    $_SESSION['flash'] = 'This goal has been archived and cannot be linked to a schedule.';
    redirect('/goals');
    return;
  }
  if (goal_row_is_locked($goalRow)) {
    $_SESSION['flash'] = 'This goal is finished and cannot be linked to a schedule.';
    redirect('/goals');
    return;
  }

  $schedMeta = $pdo->prepare('SELECT archived_at FROM scheduled_payments WHERE id=? AND user_id=?');
  $schedMeta->execute([$spId,$u]);
  $schedRow = $schedMeta->fetch(PDO::FETCH_ASSOC);
  if (!$schedRow) { redirect('/goals'); }
  if (!empty($schedRow['archived_at'])) {
    $_SESSION['flash'] = 'This scheduled payment has been archived and cannot be linked.';
    redirect('/goals');
    return;
  }

  $pdo->beginTransaction();
  try {
    // Unlink any other schedules pointing to this goal (keep a single link)
    $pdo->prepare("UPDATE scheduled_payments SET goal_id=NULL WHERE user_id=? AND goal_id=? AND id<>?")
        ->execute([$u,$goalId,$spId]);

    // Link this one
    $pdo->prepare("UPDATE scheduled_payments SET goal_id=? WHERE id=? AND user_id=?")
        ->execute([$goalId,$spId,$u]);

    $pdo->commit();
    $_SESSION['flash'] = 'Schedule linked to goal.';
  } catch(Throwable $e){
    $pdo->rollBack();
    $_SESSION['flash'] = 'Failed to link schedule.';
  }
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
  $g = $pdo->prepare('SELECT id, user_id, title, target_amount, current_amount, currency, status, archived_at
                      FROM goals WHERE id=? AND user_id=?');
  $g->execute([$goalId,$u]);
  $goal = $g->fetch(PDO::FETCH_ASSOC);
  if (!$goal){
    $_SESSION['flash'] = 'Goal not found.';
    redirect('/goals');
  }

  if (!empty($goal['archived_at'])) {
    $_SESSION['flash'] = 'This goal has been archived and cannot accept new contributions.';
    redirect('/goals');
    return;
  }

  if (goal_row_is_locked($goal)) {
    $_SESSION['flash'] = 'This goal is finished and cannot accept new contributions.';
    redirect('/goals');
    return;
  }

  // Fallback contribution currency to goal currency or user main
  if ($currency === ''){
    $currency = $goal['currency'] ?: (function_exists('fx_user_main') ? fx_user_main($pdo, $u) : 'HUF');
  }

  $goalCur = $goal['currency'] ?: $currency;
  $previousState = [
    'title' => (string)($goal['title'] ?? ''),
    'target_amount' => (float)($goal['target_amount'] ?? 0.0),
    'current_amount' => max(0.0, (float)($goal['current_amount'] ?? 0.0)),
    'currency' => (string)($goalCur ?: $currency),
    'status' => (string)($goal['status'] ?? ''),
  ];

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
    email_maybe_send_goal_completion($pdo, $u, $goalId, $previousState);
  } catch(Throwable $e){
    $pdo->rollBack();
    // throw $e; // uncomment to debug
    $_SESSION['flash'] = 'Could not add contribution.';
  }

  redirect('/goals');
}

function goals_tx_update(PDO $pdo){
  verify_csrf(); require_login();
  $u = uid();

  $id        = (int)($_POST['id'] ?? 0);
  $amount    = (float)($_POST['amount'] ?? 0);
  $currency  = strtoupper(trim($_POST['currency'] ?? ''));
  $occurred  = $_POST['occurred_on'] ?: date('Y-m-d');
  $note      = trim($_POST['note'] ?? '');

  if (!$id || $amount <= 0 || !$occurred) {
    $_SESSION['flash'] = 'Goal, amount and date are required.';
    redirect('/goals');
  }

  $q = $pdo->prepare('SELECT gc.*, g.currency AS goal_currency, g.user_id, g.id AS goal_id,
                             g.title AS goal_title, g.target_amount, g.current_amount, g.status AS goal_status, g.archived_at AS goal_archived_at
                       FROM goal_contributions gc
                       JOIN goals g ON g.id = gc.goal_id
                      WHERE gc.id=? AND g.user_id=?');
  $q->execute([$id,$u]);
  $row = $q->fetch(PDO::FETCH_ASSOC);
  if (!$row) { redirect('/goals'); }

  if (!empty($row['goal_archived_at'])) {
    $_SESSION['flash'] = 'This goal has been archived and contributions are locked.';
    redirect('/goals');
    return;
  }

  $lockCheck = [
    'status' => $row['goal_status'] ?? null,
    'target_amount' => $row['target_amount'] ?? null,
    'current_amount' => $row['current_amount'] ?? null,
    'archived_at' => $row['goal_archived_at'] ?? null,
  ];
  if (goal_row_is_locked($lockCheck)) {
    $_SESSION['flash'] = 'This goal is finished and contributions are locked.';
    redirect('/goals');
    return;
  }

  if ($currency === '') {
    $currency = $row['goal_currency']
      ?: ($row['currency'] ?: (function_exists('fx_user_main') ? fx_user_main($pdo, $u) : 'HUF'));
  }

  $goalCur = $row['goal_currency'] ?: $currency;
  $previousState = [
    'title' => (string)($row['goal_title'] ?? ''),
    'target_amount' => (float)($row['target_amount'] ?? 0.0),
    'current_amount' => max(0.0, (float)($row['current_amount'] ?? 0.0)),
    'currency' => (string)($goalCur ?: $currency),
    'status' => (string)($row['goal_status'] ?? ''),
  ];

  $pdo->beginTransaction();
  try {
    $oldDelta = ($row['currency'] === $goalCur)
      ? (float)$row['amount']
      : fx_convert($pdo, (float)$row['amount'], $row['currency'], $goalCur, $row['occurred_on']);

    $newDelta = ($currency === $goalCur)
      ? $amount
      : fx_convert($pdo, $amount, $currency, $goalCur, $occurred);

    $upd = $pdo->prepare('UPDATE goal_contributions
                             SET amount=?, currency=?, occurred_on=?, note=?
                           WHERE id=?');
    $upd->execute([$amount,$currency,$occurred,$note,$id]);

    $pdo->prepare('UPDATE goals
                      SET current_amount = GREATEST(0, COALESCE(current_amount,0) - ? + ?),
                          currency = COALESCE(currency, ?)
                    WHERE id=? AND user_id=?')
        ->execute([$oldDelta,$newDelta,$goalCur,(int)$row['goal_id'],$u]);

    $pdo->commit();
    $_SESSION['flash'] = 'Contribution updated.';
    email_maybe_send_goal_completion($pdo, $u, (int)$row['goal_id'], $previousState);
  } catch (Throwable $e) {
    $pdo->rollBack();
    $_SESSION['flash'] = 'Could not update contribution.';
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
                             g.currency AS goal_currency, g.archived_at AS goal_archived_at,
                             g.status AS goal_status, g.target_amount, g.current_amount
                      FROM goal_contributions gc
                      JOIN goals g ON g.id=gc.goal_id
                      WHERE gc.id=? AND g.user_id=?');
  $q->execute([$id,$u]);
  $row = $q->fetch(PDO::FETCH_ASSOC);
  if (!$row){ redirect('/goals'); }

  if (!empty($row['goal_archived_at'])) {
    $_SESSION['flash'] = 'This goal has been archived and contributions are locked.';
    redirect('/goals');
    return;
  }

  $lockCheck = [
    'status' => $row['goal_status'] ?? null,
    'target_amount' => $row['target_amount'] ?? null,
    'current_amount' => $row['current_amount'] ?? null,
    'archived_at' => $row['goal_archived_at'] ?? null,
  ];
  if (goal_row_is_locked($lockCheck)) {
    $_SESSION['flash'] = 'This goal is finished and contributions are locked.';
    redirect('/goals');
    return;
  }

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

function goals_unlink_schedule(PDO $pdo){
  verify_csrf(); require_login(); $u = uid();

  $goalId = (int)($_POST['goal_id'] ?? 0);
  if (!$goalId) { redirect('/goals'); }

  // Make sure the goal belongs to the user
  $chk = $pdo->prepare('SELECT archived_at, status, target_amount, current_amount FROM goals WHERE id=? AND user_id=?');
  $chk->execute([$goalId,$u]);
  $goalRow = $chk->fetch(PDO::FETCH_ASSOC);
  if (!$goalRow) { redirect('/goals'); }
  if (!empty($goalRow['archived_at'])) {
    $_SESSION['flash'] = 'This goal has been archived and schedules are locked.';
    redirect('/goals');
    return;
  }
  if (goal_row_is_locked($goalRow)) {
    $_SESSION['flash'] = 'This goal is finished and schedules are locked.';
    redirect('/goals');
    return;
  }

  // Unlink any schedule pointing at this goal (keep schedules intact)
  $pdo->prepare("UPDATE scheduled_payments SET goal_id=NULL WHERE user_id=? AND goal_id=?")
      ->execute([$u,$goalId]);

  $_SESSION['flash'] = 'Schedule unlinked.';
  redirect('/goals');
}
