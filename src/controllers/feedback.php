<?php
require_once __DIR__ . '/../helpers.php';

function feedback_index(PDO $pdo){
  require_login(); 
  $u = uid();

  // Filters (safe defaults)
  $tab = $_GET['tab'] ?? 'all';
  if (!in_array($tab, ['all','bugs','ideas','mine','open','resolved'], true)) {
    $tab = 'all';
  }

  $flt = [
    'tab'  => $tab,
    'q'    => trim($_GET['q'] ?? ''),
    'page' => max(1, (int)($_GET['page'] ?? 1)),
  ];

  $per = 20;

  $where = ['f.user_id = f.user_id']; // tautology
  $params = [];

  switch ($flt['tab']) {
    case 'bugs':     $where[] = "f.kind = 'bug'"; break;
    case 'ideas':    $where[] = "f.kind = 'idea'"; break;
    case 'mine':     $where[] = "f.user_id = ?"; $params[] = $u; break;
    case 'open':     $where[] = "f.status IN ('open','in_progress')"; break;
    case 'resolved': $where[] = "f.status IN ('resolved','closed')"; break;
  }

  if ($flt['q'] !== '') {
    $where[] = "(f.title ILIKE ? OR f.message ILIKE ?)";
    $params[] = '%'.$flt['q'].'%';
    $params[] = '%'.$flt['q'].'%';
  }

  $whereSql = implode(' AND ', $where);

  // Count
  $cnt = $pdo->prepare("SELECT COUNT(*) FROM feedback f WHERE $whereSql");
  $cnt->execute($params);
  $total = (int)$cnt->fetchColumn();
  $pages = max(1, (int)ceil($total / $per));
  $page  = min($flt['page'], $pages);
  $off   = ($page - 1) * $per;

  // Page
  $stmt = $pdo->prepare("
    SELECT f.*, u.email, u.full_name
    FROM feedback f
    JOIN users u ON u.id = f.user_id
    WHERE $whereSql
    ORDER BY f.created_at DESC, f.id DESC
    LIMIT $per OFFSET $off
  ");
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  view('feedback/index', compact('rows','page','pages','flt'));
}


function feedback_add(PDO $pdo){
  verify_csrf(); require_login(); $u = uid();

  $kind     = ($_POST['kind'] ?? '') === 'bug' ? 'bug' : 'idea';
  $title    = trim($_POST['title'] ?? '');
  $message  = trim($_POST['message'] ?? '');
  $severity = ($_POST['severity'] ?? null);
  if ($severity !== null && !in_array($severity, ['low','medium','high'], true)) $severity = null;

  if ($title === '' || $message === '') {
    $_SESSION['flash'] = 'Please provide a title and message.';
    redirect('/feedback');
  }

  $pdo->prepare("
    INSERT INTO feedback(user_id,kind,title,message,severity)
    VALUES (?,?,?,?,?)
  ")->execute([$u,$kind,$title,$message,$severity]);

  $_SESSION['flash_success'] = 'Thanks for the feedback!';
  redirect('/feedback');
}

function feedback_update_status(PDO $pdo){
  verify_csrf(); require_login(); $u = uid();
  // Basic guard: allow the author to close their own, or admins could manage all.
  $id     = (int)($_POST['id'] ?? 0);
  $status = $_POST['status'] ?? 'open';
  if (!in_array($status, ['open','in_progress','resolved','closed'], true)) $status = 'open';

  // Check ownership (or replace with your admin check)
  $chk = $pdo->prepare("SELECT user_id FROM feedback WHERE id=?");
  $chk->execute([$id]); $owner = (int)($chk->fetchColumn() ?: 0);
  if (!$id || !$owner) { redirect('/feedback'); }

  if ($owner !== $u /* && !is_admin() */ ) {
    // Non-owners limited to closing their own item
    if (!in_array($status, ['closed'], true)) { $_SESSION['flash']='You can only close your own item.'; redirect('/feedback'); }
  }

  $pdo->prepare("UPDATE feedback SET status=?, updated_at=NOW() WHERE id=?")->execute([$status,$id]);
  $_SESSION['flash_success'] = 'Status updated.';
}

function feedback_delete(PDO $pdo){
  verify_csrf(); require_login(); $u = uid();
  $id = (int)($_POST['id'] ?? 0);
  if (!$id) redirect('/feedback');

  // Only author (or admin) can delete
  $chk = $pdo->prepare("SELECT user_id FROM feedback WHERE id=?");
  $chk->execute([$id]); $owner = (int)($chk->fetchColumn() ?: 0);
  if (!$owner || ($owner !== $u /* && !is_admin() */)) {
    $_SESSION['flash'] = 'Not allowed.'; redirect('/feedback');
  }

  $pdo->prepare("DELETE FROM feedback WHERE id=?")->execute([$id]);
  $_SESSION['flash_success'] = 'Entry removed.';
}
