<?php
require_once __DIR__ . '/../helpers.php';

function scheduled_index(PDO $pdo){ require_login(); $u=uid();
  $rows=$pdo->prepare('SELECT * FROM scheduled_payments WHERE user_id=? ORDER BY next_due NULLS LAST, id');
  $rows->execute([$u]); $rows=$rows->fetchAll();
  view('scheduled/index', compact('rows'));
}

function scheduled_add(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $stmt=$pdo->prepare('INSERT INTO scheduled_payments(user_id,title,amount,currency,rrule,next_due) VALUES(?,?,?,?,?,?)');
  $stmt->execute([$u, trim($_POST['title']), (float)$_POST['amount'], $_POST['currency']?:'HUF', trim($_POST['rrule']), $_POST['next_due']?:null]);
}

function scheduled_edit(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $stmt=$pdo->prepare('UPDATE scheduled_payments SET title=?, amount=?, currency=?, rrule=?, next_due=? WHERE id=? AND user_id=?');
  $stmt->execute([trim($_POST['title']), (float)$_POST['amount'], $_POST['currency']?:'HUF', trim($_POST['rrule']), $_POST['next_due']?:null, (int)$_POST['id'], $u]);
}

function scheduled_delete(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $pdo->prepare('DELETE FROM scheduled_payments WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$u]);
}