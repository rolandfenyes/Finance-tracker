<?php
require_once __DIR__ . '/../helpers.php';

function tutorial_show(PDO $pdo){
  require_login();
  view('tutorial/index', []);
}

function tutorial_done(PDO $pdo){
  require_login();
  // Try to persist a "tutorial_seen" flag on the user (ignore if column doesn’t exist)
  try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS tutorial_seen boolean NOT NULL DEFAULT false");
  } catch (Throwable $e) { /* ignore */ }

  try {
    $stmt = $pdo->prepare("UPDATE users SET tutorial_seen = TRUE, needs_tutorial = FALSE WHERE id = ?");
    $stmt->execute([uid()]);
  } catch (Throwable $e) { /* ignore */ }

  $_SESSION['flash'] = 'Tutorial completed. Have fun!';
  redirect('/');
}
