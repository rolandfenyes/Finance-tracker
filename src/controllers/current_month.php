<?php
require_once __DIR__ . '/../helpers.php';
function current_month_controller(PDO $pdo) {
    require_login();
    $u = uid();
    $y = (int)date('Y');
    $m = (int)date('n');

    // Fetch transactions of current month
    $stmt = $pdo->prepare("SELECT t.*, c.label AS cat_label
        FROM transactions t
        LEFT JOIN categories c
        ON c.id = t.category_id
        AND c.user_id = t.user_id
        WHERE t.user_id = ?
        AND EXTRACT(YEAR FROM t.occurred_on) = ?
        AND EXTRACT(MONTH FROM t.occurred_on) = ?
        ORDER BY t.occurred_on DESC, t.id DESC
    ");

    $stmt->execute([$u,$y,$m]);
    $tx = $stmt->fetchAll();

    // Sums
    $sumIn = 0; $sumOut = 0;
    foreach($tx as $row){
        if($row['kind']==='income') $sumIn += $row['amount']; else $sumOut += $row['amount'];
    }

    // Categories for quick add
    $cats = $pdo->prepare('SELECT id,label,kind FROM categories WHERE user_id=? ORDER BY kind,label');
    $cats->execute([$u]);
    $cats = $cats->fetchAll();

    view('current_month', compact('tx','sumIn','sumOut','cats','y','m'));
}