<?php
require_once __DIR__ . '/../helpers.php';

function loans_index(PDO $pdo){ require_login(); $u=uid();
  $loans=$pdo->prepare('SELECT * FROM loans WHERE user_id=? ORDER BY id');
  $loans->execute([$u]); $loans=$loans->fetchAll();
  view('loans/index', compact('loans'));
}

function loans_add(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $stmt=$pdo->prepare('INSERT INTO loans(user_id,name,principal,interest_rate,start_date,end_date,payment_day,extra_payment,balance) VALUES(?,?,?,?,?,?,?,?,?)');
  $stmt->execute([$u, trim($_POST['name']), (float)$_POST['principal'], (float)$_POST['interest_rate'], $_POST['start_date'], $_POST['end_date']?:null, (int)$_POST['payment_day'], (float)($_POST['extra_payment']??0), (float)$_POST['principal']]);
}

function loans_edit(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $stmt=$pdo->prepare('UPDATE loans SET name=?, principal=?, interest_rate=?, start_date=?, end_date=?, payment_day=?, extra_payment=?, balance=? WHERE id=? AND user_id=?');
  $stmt->execute([trim($_POST['name']), (float)$_POST['principal'], (float)$_POST['interest_rate'], $_POST['start_date'], $_POST['end_date']?:null, (int)$_POST['payment_day'], (float)($_POST['extra_payment']??0), (float)$_POST['balance'], (int)$_POST['id'], $u]);
}

function loans_delete(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $pdo->prepare('DELETE FROM loans WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$u]);
}

function loan_payment_add(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $loanId=(int)$_POST['loan_id'];
  $amount=(float)$_POST['amount']; $interest=(float)($_POST['interest_component']??0); $principal=max(0,$amount-$interest);
  $pdo->prepare('INSERT INTO loan_payments(loan_id,paid_on,amount,principal_component,interest_component,currency) VALUES(?,?,?,?,?,?)')
      ->execute([$loanId, $_POST['paid_on'], $amount, $principal, $interest, $_POST['currency']??'HUF']);
  $pdo->prepare('UPDATE loans SET balance = GREATEST(0,balance-?) WHERE id=? AND user_id=?')->execute([$principal,$loanId,$u]);
}