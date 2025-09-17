<?php
require_once __DIR__ . '/../helpers.php';

function currencies_index(PDO $pdo){
    require_login(); $u=uid();
    // Current user currencies
    $cur = $pdo->prepare('SELECT uc.code, uc.is_main, c.name FROM user_currencies uc JOIN currencies c ON c.code=uc.code WHERE uc.user_id=? ORDER BY uc.is_main DESC, uc.code');
    $cur->execute([$u]); $userCurrencies = $cur->fetchAll();

    // All available minus already added
    $avail = $pdo->prepare('SELECT code, name FROM currencies WHERE code NOT IN (SELECT code FROM user_currencies WHERE user_id=?) ORDER BY code');
    $avail->execute([$u]); $available = $avail->fetchAll();

    view('settings/currencies', compact('userCurrencies','available'));
}

function currency_add(PDO $pdo){
    verify_csrf(); require_login(); $u = uid();

    $code = strtoupper(trim($_POST['code'] ?? ''));
    if ($code === '') return; // nothing selected

    // ensure currency exists
    $exists = $pdo->prepare('SELECT 1 FROM currencies WHERE code = ?');
    $exists->execute([$code]);
    if (!$exists->fetchColumn()) return;

    // first currency for this user becomes main
    $has = $pdo->prepare('SELECT COUNT(*) FROM user_currencies WHERE user_id = ?');
    $has->execute([$u]);
    $isMain = ((int)$has->fetchColumn() === 0);

    // insert with an explicit boolean binding
    $stmt = $pdo->prepare(
        'INSERT INTO user_currencies(user_id, code, is_main)
         VALUES (?, ?, ?)
         ON CONFLICT (user_id, code) DO NOTHING'
    );
    $stmt->bindValue(1, $u, PDO::PARAM_INT);
    $stmt->bindValue(2, $code, PDO::PARAM_STR);
    $stmt->bindValue(3, $isMain, PDO::PARAM_BOOL);   // <— key change
    $stmt->execute();
}


function currency_remove(PDO $pdo){
    verify_csrf(); require_login(); $u=uid();
    $code = strtoupper(trim($_POST['code'] ?? ''));
    if ($code==='') return;
    // prevent removing main currency
    $m=$pdo->prepare('SELECT is_main FROM user_currencies WHERE user_id=? AND code=?');
    $m->execute([$u,$code]); $row=$m->fetch();
    if ($row && !$row['is_main']) {
        $pdo->prepare('DELETE FROM user_currencies WHERE user_id=? AND code=?')->execute([$u,$code]);
    }
}

function currency_set_main(PDO $pdo){
    verify_csrf(); require_login(); $u=uid();
    $code = strtoupper(trim($_POST['code'] ?? ''));
    if ($code==='') return;
    $pdo->prepare('UPDATE user_currencies SET is_main=false WHERE user_id=?')->execute([$u]);
    $pdo->prepare('UPDATE user_currencies SET is_main=true WHERE user_id=? AND code=?')->execute([$u,$code]);
}