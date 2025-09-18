<?php
require_once __DIR__ . '/../helpers.php';

// function categories_index(PDO $pdo){
//     require_login(); $u = uid();

//     $q = $pdo->prepare("
//         SELECT id, label, kind, color
//         FROM categories
//         WHERE user_id=?
//         ORDER BY kind, lower(label)
//         ");
//     $q->execute([$u]);
//     $rows = $q->fetchAll();

//     // counts to help user see if a category is used (optional)
//     $cnt = $pdo->prepare("SELECT category_id, COUNT(*) n
//                           FROM transactions
//                           WHERE user_id=?
//                           GROUP BY category_id");
//     $cnt->execute([$u]);
//     $usage = [];
//     foreach ($cnt as $r) { $usage[(int)$r['category_id']] = (int)$r['n']; }

//     view('settings/categories', compact('rows','usage'));
// }

// function categories_add(PDO $pdo){
//     verify_csrf(); require_login(); $u=uid();
//     $kind  = ($_POST['kind'] ?? '') === 'spending' ? 'spending' : 'income';
//     $label = trim($_POST['label'] ?? '');
//     $color = sanitize_hex($_POST['color'] ?? null) ?? '#6B7280'; // default gray
//     if ($label==='') return;

//     $stmt = $pdo->prepare(
//         "INSERT INTO categories(user_id,label,kind,color)
//          VALUES (?,?,?,?)
//          ON CONFLICT DO NOTHING"
//     );
//     $stmt->execute([$u,$label,$kind,$color]);
// }


// function categories_edit(PDO $pdo){
//     verify_csrf(); require_login(); $u=uid();
//     $id    = (int)($_POST['id'] ?? 0);
//     if (!$id) return;
//     $kind  = ($_POST['kind'] ?? '') === 'spending' ? 'spending' : 'income';
//     $label = trim($_POST['label'] ?? '');
//     $color = sanitize_hex($_POST['color'] ?? null) ?? '#6B7280';
//     if ($label==='') return;

//     $stmt = $pdo->prepare("UPDATE categories SET label=?, kind=?, color=? WHERE id=? AND user_id=?");
//     $stmt->execute([$label,$kind,$color,$id,$u]);
// }


function categories_delete(PDO $pdo){
    verify_csrf(); require_login(); $u=uid();
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) return;

    // If you have FK ON DELETE SET NULL, this will succeed; otherwise it may fail.
    try {
        $pdo->prepare("DELETE FROM categories WHERE id=? AND user_id=?")->execute([$id,$u]);
    } catch (Throwable $e) {
        // soft-fallback: null out references then delete (uncomment if needed)
        // $pdo->prepare("UPDATE transactions SET category_id=NULL WHERE user_id=? AND category_id=?")->execute([$u,$id]);
        // $pdo->prepare("DELETE FROM categories WHERE id=? AND user_id=?")->execute([$id,$u]);
    }
}

function sanitize_hex(?string $v): ?string {
    $v = trim((string)$v);
    if ($v === '') return null;
    // allow '#abc' or '#aabbcc' (case-insensitive)
    if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $v)) return strtoupper($v);
    return null;
}

function categories_index(PDO $pdo){
    require_login(); $u = uid();

    $stmt = $pdo->prepare("
      SELECT id, label, kind, color, cashflow_rule_id
      FROM categories
      WHERE user_id=?
      ORDER BY kind, lower(label)
    ");
    $stmt->execute([$u]);
    $rows = $stmt->fetchAll();

    $usageStmt = $pdo->prepare("SELECT category_id, COUNT(*) cnt
                                FROM transactions WHERE user_id=? AND category_id IS NOT NULL
                                GROUP BY category_id");
    $usageStmt->execute([$u]);
    $usage = [];
    foreach($usageStmt as $r) $usage[(int)$r['category_id']] = (int)$r['cnt'];

    // <- load rules for the selector
    $rs = $pdo->prepare("SELECT id, label FROM cashflow_rules WHERE user_id=? ORDER BY lower(label)");
    $rs->execute([$u]);
    $rules = $rs->fetchAll();

    view('settings/categories', compact('rows','usage','rules'));
}


function categories_add(PDO $pdo){
    verify_csrf(); require_login(); $u=uid();
    $kind  = ($_POST['kind'] ?? '') === 'spending' ? 'spending' : 'income';
    $label = trim($_POST['label'] ?? '');
    $color = normalize_hex($_POST['color'] ?? '');
    $rid   = ($_POST['cashflow_rule_id'] ?? '') !== '' ? (int)$_POST['cashflow_rule_id'] : null;

    if ($label==='') return;

    // verify rule belongs to user
    if ($rid !== null){
        $chk = $pdo->prepare("SELECT 1 FROM cashflow_rules WHERE id=? AND user_id=?");
        $chk->execute([$rid,$u]);
        if (!$chk->fetch()) $rid = null;
    }

    $stmt = $pdo->prepare("
      INSERT INTO categories(user_id,label,kind,color,cashflow_rule_id)
      VALUES (?,?,?,?,?)
    ");
    $stmt->execute([$u,$label,$kind,$color,$rid]);
}

function categories_edit(PDO $pdo){
    verify_csrf(); require_login(); $u=uid();
    $id    = (int)($_POST['id'] ?? 0); if (!$id) return;
    $kind  = ($_POST['kind'] ?? '') === 'spending' ? 'spending' : 'income';
    $label = trim($_POST['label'] ?? '');
    $color = normalize_hex($_POST['color'] ?? '');
    $rid   = ($_POST['cashflow_rule_id'] ?? '') !== '' ? (int)$_POST['cashflow_rule_id'] : null;

    if ($label==='') return;

    if ($rid !== null){
        $chk = $pdo->prepare("SELECT 1 FROM cashflow_rules WHERE id=? AND user_id=?");
        $chk->execute([$rid,$u]);
        if (!$chk->fetch()) $rid = null;
    }

    $stmt = $pdo->prepare("
      UPDATE categories
         SET label=?, kind=?, color=?, cashflow_rule_id=?
       WHERE id=? AND user_id=?
    ");
    $stmt->execute([$label,$kind,$color,$rid,$id,$u]);
}