<?php
require_once __DIR__ . '/../helpers.php';

function incomes_index(PDO $pdo){
    require_login(); $u=uid();
    // group by label with history, newest first
    $q = $pdo->prepare('SELECT * FROM basic_incomes WHERE user_id=? ORDER BY label, valid_from DESC, id DESC');
    $q->execute([$u]);
    $rows = $q->fetchAll();

    // available user currencies (for the add form default)
    $c = $pdo->prepare('SELECT code, is_main FROM user_currencies WHERE user_id=? ORDER BY is_main DESC, code');
    $c->execute([$u]);
    $currencies = $c->fetchAll();

    view('settings/basic_incomes', compact('rows','currencies'));
}

// Add new base income or record a raise by same label (auto-closes previous period)
function incomes_add(PDO $pdo){
    verify_csrf(); require_login(); $u = uid();

    $label      = trim($_POST['label'] ?? '');
    if ($label === '') { $_SESSION['flash'] = 'Label required.'; return; }

    $amount     = (float)($_POST['amount'] ?? 0);
    $currency   = $_POST['currency'] ?? 'HUF';
    $valid_from = $_POST['valid_from'] ?: date('Y-m-d');

    $pdo->beginTransaction();
    try {
        // close any open-ended record for the same label BEFORE the new valid_from
        $stmt = $pdo->prepare(
            "UPDATE basic_incomes
               SET valid_to = (?::date - INTERVAL '1 day')::date
             WHERE user_id = ?
               AND label    = ?
               AND (valid_to IS NULL OR valid_to >= ?::date)
               AND valid_from < ?::date"
        );
        $stmt->execute([$valid_from, $u, $label, $valid_from, $valid_from]);

        // insert new row (open-ended)
        $ins = $pdo->prepare(
            'INSERT INTO basic_incomes(user_id,label,amount,currency,valid_from,valid_to)
             VALUES (?,?,?,?,?,NULL)'
        );
        $ins->execute([$u, $label, $amount, $currency, $valid_from]);

        $pdo->commit();
        $_SESSION['flash'] = 'Income saved.';
    } catch (Throwable $e) {
        $pdo->rollBack();
        $_SESSION['flash'] = 'Save failed.';
        // Uncomment to debug locally:
        // throw $e;
    }
}


function incomes_edit(PDO $pdo){
    verify_csrf(); require_login(); $u=uid();
    $id = (int)($_POST['id'] ?? 0);
    if(!$id) return;

    $label = trim($_POST['label'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $currency = $_POST['currency'] ?? 'HUF';
    $valid_from = $_POST['valid_from'] ?? null;
    $valid_to = $_POST['valid_to'] !== '' ? $_POST['valid_to'] : null;

    $stmt = $pdo->prepare('UPDATE basic_incomes SET label=?, amount=?, currency=?, valid_from=?, valid_to=? WHERE id=? AND user_id=?');
    $stmt->execute([$label,$amount,$currency,$valid_from,$valid_to,$id,$u]);
}

function incomes_delete(PDO $pdo){
    verify_csrf(); require_login(); $u=uid();
    $id = (int)($_POST['id'] ?? 0);
    if(!$id) return;

    // If deleting the most recent record of a label where previous was auto-closed, you may want to reopen it.
    // Simple approach: just delete; advanced reopening can be added later.
    $pdo->prepare('DELETE FROM basic_incomes WHERE id=? AND user_id=?')->execute([$id,$u]);
}