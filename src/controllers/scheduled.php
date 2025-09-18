<?php
require_once __DIR__ . '/../helpers.php';

function scheduled_index(PDO $pdo){
  require_login(); $u=uid();

  // rows with category info
  $q = $pdo->prepare("
    SELECT s.*,
           c.label AS cat_label,
           COALESCE(NULLIF(c.color,''),'#6B7280') AS cat_color
      FROM scheduled_payments s
      LEFT JOIN categories c ON c.id=s.category_id AND c.user_id=s.user_id
     WHERE s.user_id=?
     ORDER BY s.next_due NULLS LAST, lower(s.title)
  ");
  $q->execute([$u]);
  $rows = $q->fetchAll();

  // category selector
  $cs = $pdo->prepare("SELECT id,label,COALESCE(NULLIF(color,''),'#6B7280') AS color
                         FROM categories WHERE user_id=? ORDER BY lower(label)");
  $cs->execute([$u]);
  $categories = $cs->fetchAll();

  // user currencies (for selects)
  $uc = $pdo->prepare("SELECT code,is_main FROM user_currencies WHERE user_id=? ORDER BY is_main DESC, code");
  $uc->execute([$u]);
  $userCurrencies = $uc->fetchAll(PDO::FETCH_ASSOC) ?: [];

  // Fallback to user's main (or HUF) so the view always has one option
  $main = function_exists('fx_user_main') ? fx_user_main($pdo, $u) : 'HUF';
  if (empty($userCurrencies)) {
      $userCurrencies = [['code' => $main ?: 'HUF', 'is_main' => true]];
  } else {
      // normalize rows and drop any null/invalid entries
      $norm = [];
      foreach ($userCurrencies as $row) {
          if (!is_array($row) || empty($row['code'])) continue;
          $norm[] = ['code' => strtoupper($row['code']), 'is_main' => !empty($row['is_main'])];
      }
      $userCurrencies = $norm ?: [['code' => $main ?: 'HUF', 'is_main' => true]];
  }
  
  view('scheduled/index', compact('rows','categories','userCurrencies'));
}

function scheduled_add(PDO $pdo){
  verify_csrf(); require_login(); $u=uid();
  $title = trim($_POST['title'] ?? '');
  $amount = (float)($_POST['amount'] ?? 0);
  $currency = strtoupper(trim($_POST['currency'] ?? ''));
  $next_due = $_POST['next_due'] ?: null;
  $rrule = trim($_POST['rrule'] ?? '');
  $catId = ($_POST['category_id'] ?? '') !== '' ? (int)$_POST['category_id'] : null;

  if ($title==='' || !$next_due) return;
  if ($currency==='') $currency='HUF';

  if ($catId !== null){
    $chk=$pdo->prepare("SELECT 1 FROM categories WHERE id=? AND user_id=?");
    $chk->execute([$catId,$u]);
    if (!$chk->fetch()) $catId=null;
  }

  $pdo->prepare("
    INSERT INTO scheduled_payments(user_id,title,amount,currency,next_due,rrule,category_id)
    VALUES (?,?,?,?,?,?,?)
  ")->execute([$u,$title,$amount,$currency,$next_due,$rrule,$catId]);
}

function scheduled_edit(PDO $pdo){
  verify_csrf(); require_login(); $u=uid();
  $id = (int)($_POST['id'] ?? 0); if(!$id) return;

  $title = trim($_POST['title'] ?? '');
  $amount = (float)($_POST['amount'] ?? 0);
  $currency = strtoupper(trim($_POST['currency'] ?? 'HUF'));
  $next_due = $_POST['next_due'] ?: null;
  $rrule = trim($_POST['rrule'] ?? '');
  $catId = ($_POST['category_id'] ?? '') !== '' ? (int)$_POST['category_id'] : null;

  if ($catId !== null){
    $chk=$pdo->prepare("SELECT 1 FROM categories WHERE id=? AND user_id=?");
    $chk->execute([$catId,$u]);
    if (!$chk->fetch()) $catId=null;
  }

  $pdo->prepare("
    UPDATE scheduled_payments
       SET title=?, amount=?, currency=?, next_due=?, rrule=?, category_id=?
     WHERE id=? AND user_id=?
  ")->execute([$title,$amount,$currency,$next_due,$rrule,$catId,$id,$u]);
}



function scheduled_delete(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $pdo->prepare('DELETE FROM scheduled_payments WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$u]);
}