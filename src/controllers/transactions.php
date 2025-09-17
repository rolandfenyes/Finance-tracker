<?php
require_once __DIR__ . '/../helpers.php';
function tx_add(PDO $pdo) {
    verify_csrf(); require_login(); $u=uid();
    $kind = $_POST['kind'] === 'income' ? 'income' : 'spending';
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $amount = (float)$_POST['amount'];
    $currency = $_POST['currency'] ?? 'HUF';
    $date = $_POST['occurred_on'] ?? date('Y-m-d');
    $note = trim($_POST['note'] ?? '');

    $stmt = $pdo->prepare('INSERT INTO transactions(user_id,kind,category_id,amount,currency,occurred_on,note) VALUES(?,?,?,?,?,?,?)');
    $stmt->execute([$u,$kind,$category_id,$amount,$currency,$date,$note]);
}

function tx_edit(PDO $pdo) {
    verify_csrf(); require_login(); $u=uid();
    $id = (int)$_POST['id'];
    $stmt = $pdo->prepare('UPDATE transactions SET kind=?, category_id=?, amount=?, currency=?, occurred_on=?, note=?, updated_at=NOW() WHERE id=? AND user_id=?');
    $stmt->execute([
        $_POST['kind']==='income'?'income':'spending',
        !empty($_POST['category_id'])?(int)$_POST['category_id']:null,
        (float)$_POST['amount'],
        $_POST['currency'] ?? 'HUF',
        $_POST['occurred_on'] ?? date('Y-m-d'),
        trim($_POST['note'] ?? ''),
        $id,$u
    ]);
}

function tx_delete(PDO $pdo) {
    verify_csrf(); require_login(); $u=uid();
    $id=(int)$_POST['id'];
    $pdo->prepare('DELETE FROM transactions WHERE id=? AND user_id=?')->execute([$id,$u]);
}