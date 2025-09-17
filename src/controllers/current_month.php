<?php
require_once __DIR__ . '/../helpers.php';

function current_month_controller(PDO $pdo) {
    require_login();
    $u = uid();
    $y = (int)date('Y');
    $m = (int)date('n');

    // month boundaries
    $first = date('Y-m-01');
    $last  = date('Y-m-t');

    // Transactions of current month
    $stmt = $pdo->prepare("
        SELECT t.*, c.label AS cat_label
        FROM transactions t
        LEFT JOIN categories c
          ON c.id = t.category_id
         AND c.user_id = t.user_id
        WHERE t.user_id = ?
          AND t.occurred_on BETWEEN ?::date AND ?::date
        ORDER BY t.occurred_on DESC, t.id DESC
    ");
    $stmt->execute([$u, $first, $last]);
    $tx = $stmt->fetchAll();

    // Sums from transactions
    $qTx = $pdo->prepare("
        SELECT
          COALESCE(SUM(CASE WHEN kind='income'   THEN amount ELSE 0 END),0) AS income_tx,
          COALESCE(SUM(CASE WHEN kind='spending' THEN amount ELSE 0 END),0) AS spending_tx
        FROM transactions
        WHERE user_id=? AND occurred_on BETWEEN ?::date AND ?::date
    ");
    $qTx->execute([$u, $first, $last]);
    $t = $qTx->fetch();
    $incomeTx   = (float)$t['income_tx'];
    $spendingTx = (float)$t['spending_tx'];

    // Basic incomes that are active for this month (treated as monthly amount)
    $qBi = $pdo->prepare("
        SELECT COALESCE(SUM(amount),0) AS bi_sum
        FROM basic_incomes
        WHERE user_id=?
          AND valid_from <= ?::date
          AND (valid_to IS NULL OR valid_to >= ?::date)
    ");
    $qBi->execute([$u, $last, $first]);
    $biSum = (float)$qBi->fetchColumn();

    $sumIn  = $incomeTx + $biSum;  // <-- include basic incomes
    $sumOut = $spendingTx;

    // Categories for quick add
    $cats = $pdo->prepare('SELECT id,label,kind FROM categories WHERE user_id=? ORDER BY kind,label');
    $cats->execute([$u]);
    $cats = $cats->fetchAll();

    view('current_month', compact('tx','sumIn','sumOut','cats','y','m'));
}
