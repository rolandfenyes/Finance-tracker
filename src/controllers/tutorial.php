<?php
require_once __DIR__ . '/../helpers.php';

function tutorial_index(PDO $pdo){
  // Render a multi-section page: how to add transactions, schedules, goals, EF, loans, month view, filters, currencies, etc.
  // Include a “Don’t show again” button:
  view('tutorial/index', []);
}

function tutorial_dismiss(PDO $pdo){
  verify_csrf(); require_login();
  $pdo->prepare('UPDATE users SET needs_tutorial=false WHERE id=?')->execute([uid()]);
  redirect('/');
}
