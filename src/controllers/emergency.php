<?php
require_once __DIR__ . '/../helpers.php';

function emergency_index(PDO $pdo){ require_login(); $u=uid();
  $fund=$pdo->prepare('SELECT * FROM emergency_fund WHERE user_id=?');
  $fund->execute([$u]); $fund=$fund->fetch();

  $tx=$pdo->prepare('SELECT * FROM emergency_transactions WHERE user_id=? ORDER BY occurred_on DESC, id DESC');
  $tx->execute([$u]); $tx=$tx->fetchAll();

  view('emergency/index', compact('fund','tx'));
}

function emergency_set(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  // upsert
  $stmt=$pdo->prepare('INSERT INTO emergency_fund(user_id,target_amount,currency,total) VALUES(?,?,?,?) ON CONFLICT(user_id) DO UPDATE SET target_amount=excluded.target_amount,currency=excluded.currency');
  $stmt->execute([$u,(float)$_POST['target_amount'],$_POST['currency']?:'HUF',0]);
}

function emergency_tx_add(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $kind = $_POST['kind']==='withdraw'?'withdraw':'deposit';
  $amount=(float)$_POST['amount'];
  $pdo->prepare('INSERT INTO emergency_transactions(user_id,occurred_on,amount,kind,note) VALUES(?,?,?,?,?)')
      ->execute([$u,$_POST['occurred_on']?:date('Y-m-d'),$amount,$kind,trim($_POST['note']??'')]);
  // update fund total
  if($kind==='deposit') $pdo->prepare('UPDATE emergency_fund SET total=total+? WHERE user_id=?')->execute([$amount,$u]);
  else $pdo->prepare('UPDATE emergency_fund SET total=GREATEST(0,total-?) WHERE user_id=?')->execute([$amount,$u]);
}

function emergency_tx_delete(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $id=(int)$_POST['id'];
  // adjust total back
  $row=$pdo->prepare('SELECT amount,kind FROM emergency_transactions WHERE id=? AND user_id=?'); $row->execute([$id,$u]); $row=$row->fetch();
  if($row){
    if($row['kind']==='deposit') $pdo->prepare('UPDATE emergency_fund SET total=GREATEST(0,total-?) WHERE user_id=?')->execute([$row['amount'],$u]);
    else $pdo->prepare('UPDATE emergency_fund SET total=total+? WHERE user_id=?')->execute([$row['amount'],$u]);
  }
  $pdo->prepare('DELETE FROM emergency_transactions WHERE id=? AND user_id=?')->execute([$id,$u]);
}