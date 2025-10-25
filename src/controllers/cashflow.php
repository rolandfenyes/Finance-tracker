<?php
require_once __DIR__ . '/../helpers.php';

function cashflow_index(PDO $pdo) {
    require_login(); $u = uid();

    // Load rules
    $stmt = $pdo->prepare("SELECT id, label, percent
                           FROM cashflow_rules
                           WHERE user_id=?
                           ORDER BY lower(label)");
    $stmt->execute([$u]);
    $rules = $stmt->fetchAll();

    // Compute total
    $total = 0.0;
    foreach ($rules as $r) $total += (float)$r['percent'];

    view('settings/cashflow', compact('rules','total'));
}

function cashflow_add(PDO $pdo) {
    verify_csrf(); require_login(); $u = uid();
    if (!role_can('cashflow_rules_edit')) {
        $_SESSION['flash'] = __('This role cannot manage cashflow rules. Update the capabilities to enable editing.');
        $_SESSION['flash_type'] = 'error';
        redirect('/settings/cashflow');
    }
    $label = trim($_POST['label'] ?? '');
    $percent = (float)($_POST['percent'] ?? 0);
    if ($label === '' || $percent < 0) return;

    $stmt = $pdo->prepare("INSERT INTO cashflow_rules(user_id,label,percent) VALUES (?,?,?)");
    $stmt->execute([$u, $label, $percent]);
}

function cashflow_edit(PDO $pdo) {
    verify_csrf(); require_login(); $u = uid();
    if (!role_can('cashflow_rules_edit')) {
        $_SESSION['flash'] = __('This role cannot manage cashflow rules. Update the capabilities to enable editing.');
        $_SESSION['flash_type'] = 'error';
        redirect('/settings/cashflow');
    }
    $id = (int)($_POST['id'] ?? 0);
    $label = trim($_POST['label'] ?? '');
    $percent = (float)($_POST['percent'] ?? 0);
    if (!$id || $label === '' || $percent < 0) return;

    $stmt = $pdo->prepare("UPDATE cashflow_rules SET label=?, percent=? WHERE id=? AND user_id=?");
    $stmt->execute([$label, $percent, $id, $u]);
}

function cashflow_delete(PDO $pdo) {
    verify_csrf(); require_login(); $u = uid();
    if (!role_can('cashflow_rules_edit')) {
        $_SESSION['flash'] = __('This role cannot manage cashflow rules. Update the capabilities to enable editing.');
        $_SESSION['flash_type'] = 'error';
        redirect('/settings/cashflow');
    }
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) return;

    $pdo->prepare("DELETE FROM cashflow_rules WHERE id=? AND user_id=?")->execute([$id,$u]);
}
