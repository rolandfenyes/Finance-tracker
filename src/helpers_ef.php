// /src/controllers/helpers_ef.php
<?php
require_once __DIR__ . '/helpers.php';

function ef_ensure_categories(PDO $pdo, int $userId): array {
  $defs = [
    // adding TO EF should appear as spending in monthly view
    'ef_add'      => ['Emergency Fund',  '#16a34a', 'spending'],
    // withdrawing FROM EF should appear as income in monthly view
    'ef_withdraw' => ['EF withdrawal',   '#2563eb', 'income'],
  ];

  $ids = [];
  foreach ($defs as $sysKey => [$label, $color, $kind]) {
    $q = $pdo->prepare("SELECT id FROM categories WHERE user_id=? AND system_key=?");
    $q->execute([$userId, $sysKey]);
    $id = $q->fetchColumn();

    if (!$id) {
      $ins = $pdo->prepare("
        INSERT INTO categories(user_id, label, color, kind, system_key, protected)
        VALUES (?,?,?,?,?,TRUE)
        RETURNING id
      ");
      $ins->execute([$userId, $label, $color, $kind, $sysKey]);
      $id = $ins->fetchColumn();
    }

    $ids[$sysKey] = (int)$id;
  }
  return $ids;
}
