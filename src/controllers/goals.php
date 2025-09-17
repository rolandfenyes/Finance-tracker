<?php
require_once __DIR__ . '/../helpers.php';

function goals_index(PDO $pdo){ require_login(); $u=uid();
  $rows=$pdo->prepare('SELECT * FROM goals WHERE user_id=? ORDER BY priority, id');
  $rows->execute([$u]); $rows=$rows->fetchAll();
  $tot=$pdo->prepare("SELECT COALESCE(SUM(current_amount),0) c, COALESCE(SUM(target_amount),0) t FROM goals WHERE user_id=? AND status='active'");
  $tot->execute([$u]); $tot=$tot->fetch();
  view('goals/index', ['rows'=>$rows,'tot'=>$tot]);
}

function goals_add(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $stmt=$pdo->prepare('INSERT INTO goals(user_id,title,target_amount,current_amount,currency,deadline,priority,status) VALUES(?,?,?,?,?,?,?,?)');
  $stmt->execute([$u,trim($_POST['title']), (float)$_POST['target_amount'], (float)($_POST['current_amount']??0), $_POST['currency']??'HUF', $_POST['deadline']?:null, (int)($_POST['priority']??3), $_POST['status']??'active']);
}

function goals_edit(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $stmt=$pdo->prepare('UPDATE goals SET title=?, target_amount=?, current_amount=?, currency=?, deadline=?, priority=?, status=? WHERE id=? AND user_id=?');
  $stmt->execute([trim($_POST['title']), (float)$_POST['target_amount'], (float)($_POST['current_amount']??0), $_POST['currency']??'HUF', $_POST['deadline']?:null, (int)($_POST['priority']??3), $_POST['status']??'active', (int)$_POST['id'], $u]);
}

function goals_delete(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $pdo->prepare('DELETE FROM goals WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$u]);
}