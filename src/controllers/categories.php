<?php
require_once __DIR__ . '/../helpers.php';

function categories_index(PDO $pdo){
    require_login(); $u = uid();

    $q = $pdo->prepare("SELECT id, label, kind
                        FROM categories
                        WHERE user_id=?
                        ORDER BY kind, lower(label)");
    $q->execute([$u]);
    $rows = $q->fetchAll();

    // counts to help user see if a category is used (optional)
    $cnt = $pdo->prepare("SELECT category_id, COUNT(*) n
                          FROM transactions
                          WHERE user_id=?
                          GROUP BY category_id");
    $cnt->execute([$u]);
    $usage = [];
    foreach ($cnt as $r) { $usage[(int)$r['category_id']] = (int)$r['n']; }

    view('settings/categories', compact('rows','usage'));
}

function categories_add(PDO $pdo){
    verify_csrf(); require_login(); $u=uid();
    $kind  = ($_POST['kind'] ?? '') === 'spending' ? 'spending' : 'income';
    $label = trim($_POST['label'] ?? '');
    if ($label==='') return;

    // prevent dup per user/kind/label
    $stmt = $pdo->prepare(
        "INSERT INTO categories(user_id,label,kind)
         VALUES (?,?,?)
         ON CONFLICT DO NOTHING"
    );
    $stmt->execute([$u,$label,$kind]);
}

function categories_edit(PDO $pdo){
    verify_csrf(); require_login(); $u=uid();
    $id    = (int)($_POST['id'] ?? 0);
    if (!$id) return;
    $kind  = ($_POST['kind'] ?? '') === 'spending' ? 'spending' : 'income';
    $label = trim($_POST['label'] ?? '');
    if ($label==='') return;

    $stmt = $pdo->prepare("UPDATE categories SET label=?, kind=? WHERE id=? AND user_id=?");
    $stmt->execute([$label,$kind,$id,$u]);
}

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
