<?php
require_once __DIR__ . '/../helpers.php';

function incomes_index(PDO $pdo){
    require_login(); $u = uid();

    // Load incomes (with linked category label/color for display)
    $q = $pdo->prepare("
        SELECT b.*,
               c.label AS cat_label,
               COALESCE(NULLIF(c.color,''), '#6B7280') AS cat_color
          FROM basic_incomes b
          LEFT JOIN categories c
                 ON c.id = b.category_id AND c.user_id = b.user_id
         WHERE b.user_id = ?
         ORDER BY b.label, b.valid_from DESC, b.id DESC
    ");
    $q->execute([$u]);
    $rows = $q->fetchAll();

    // Available user currencies (add form default)
    $c = $pdo->prepare('SELECT code, is_main FROM user_currencies WHERE user_id=? ORDER BY is_main DESC, code');
    $c->execute([$u]);
    $currencies = $c->fetchAll();

    // Categories to choose from when assigning a basic income
    $cs = $pdo->prepare("
        SELECT id, label, COALESCE(NULLIF(color,''),'#6B7280') AS color
          FROM categories
         WHERE user_id = ?
         ORDER BY lower(label)
    ");
    $cs->execute([$u]);
    $categories = $cs->fetchAll();

    view('settings/basic_incomes', compact('rows','currencies','categories'));
}

// Add new base income or record a raise by same label (auto-closes previous period)
function incomes_add(PDO $pdo){
    verify_csrf(); require_login(); $u = uid();

    $label      = trim($_POST['label'] ?? '');
    if ($label === '') { $_SESSION['flash'] = __('flash.label_required'); return; }

    $amount     = (float)($_POST['amount'] ?? 0);
    $currency   = strtoupper(trim($_POST['currency'] ?? ''));
    if ($currency === '') $currency = 'HUF';
    $valid_from = $_POST['valid_from'] ?: date('Y-m-d');

    // Optional category assignment
    $catId = ($_POST['category_id'] ?? '') !== '' ? (int)$_POST['category_id'] : null;
    if ($catId !== null) {
        $chk = $pdo->prepare('SELECT 1 FROM categories WHERE id=? AND user_id=?');
        $chk->execute([$catId, $u]);
        if (!$chk->fetch()) $catId = null; // guard against cross-user ids
    }

    $pdo->beginTransaction();
    try {
        // Close any open-ended record for the same label BEFORE the new valid_from
        $stmt = $pdo->prepare(
            "UPDATE basic_incomes
                SET valid_to = (?::date - INTERVAL '1 day')::date
              WHERE user_id   = ?
                AND label      = ?
                AND (valid_to IS NULL OR valid_to >= ?::date)
                AND valid_from <  ?::date"
        );
        $stmt->execute([$valid_from, $u, $label, $valid_from, $valid_from]);

        // Insert new row (open-ended)
        $ins = $pdo->prepare(
            'INSERT INTO basic_incomes(user_id,label,amount,currency,valid_from,valid_to,category_id)
             VALUES (?,?,?,?,?,NULL,?)'
        );
        $ins->execute([$u, $label, $amount, $currency, $valid_from, $catId]);

        $pdo->commit();
        $_SESSION['flash'] = __('flash.income_saved');
    } catch (Throwable $e) {
        $pdo->rollBack();
        $_SESSION['flash'] = __('flash.income_save_failed');
        // throw $e; // uncomment to debug locally
    }
}

function incomes_edit(PDO $pdo){
    verify_csrf(); require_login(); $u = uid();

    $id         = (int)($_POST['id'] ?? 0);
    if (!$id) return;

    $label      = trim($_POST['label'] ?? '');
    $amount     = (float)($_POST['amount'] ?? 0);
    $currency   = strtoupper(trim($_POST['currency'] ?? 'HUF'));
    $valid_from = $_POST['valid_from'] ?? null;
    $valid_to   = ($_POST['valid_to'] ?? '') !== '' ? $_POST['valid_to'] : null;

    // Optional category assignment
    $catId = ($_POST['category_id'] ?? '') !== '' ? (int)$_POST['category_id'] : null;
    if ($catId !== null) {
        $chk = $pdo->prepare('SELECT 1 FROM categories WHERE id=? AND user_id=?');
        $chk->execute([$catId, $u]);
        if (!$chk->fetch()) $catId = null;
    }

    $stmt = $pdo->prepare('
        UPDATE basic_incomes
           SET label=?, amount=?, currency=?, valid_from=?, valid_to=?, category_id=?
         WHERE id=? AND user_id=?
    ');
    $stmt->execute([$label,$amount,$currency,$valid_from,$valid_to,$catId,$id,$u]);
}

function incomes_delete(PDO $pdo){
    verify_csrf(); require_login(); $u = uid();
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) return;

    $pdo->prepare('DELETE FROM basic_incomes WHERE id=? AND user_id=?')->execute([$id,$u]);
}
