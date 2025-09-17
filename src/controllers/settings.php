<?php
require_once __DIR__ . '/../helpers.php';
function settings_controller(PDO $pdo){
    require_login(); $u=uid();
    // Load user, currencies, rules, incomes
    $user = $pdo->prepare('SELECT id,email,full_name FROM users WHERE id=?');
    $user->execute([$u]); $user=$user->fetch();

    $curr = $pdo->prepare('SELECT uc.code, uc.is_main, c.name FROM user_currencies uc JOIN currencies c ON c.code=uc.code WHERE uc.user_id=? ORDER BY is_main DESC, code');
    $curr->execute([$u]); $curr=$curr->fetchAll();

    $rules = $pdo->prepare('SELECT * FROM cashflow_rules WHERE user_id=? ORDER BY id');
    $rules->execute([$u]); $rules=$rules->fetchAll();

    $basic = $pdo->prepare('SELECT * FROM basic_incomes WHERE user_id=? ORDER BY valid_from DESC');
    $basic->execute([$u]); $basic=$basic->fetchAll();

    view('settings/index', compact('user','curr','rules','basic'));
}