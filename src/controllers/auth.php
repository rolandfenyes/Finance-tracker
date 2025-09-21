<?php
require_once __DIR__ . '/../helpers.php';

function register_step1_form() {
  view('auth/register_step1', []); // use your new pretty form UI
}

function register_step1_submit(PDO $pdo) {
  verify_csrf();
  $name  = trim($_POST['full_name'] ?? '');
  $dob   = $_POST['date_of_birth'] ?? null;
  $email = trim(strtolower($_POST['email'] ?? ''));
  $pass  = $_POST['password'] ?? '';

  if ($name==='' || !$email || strlen($pass)<8) {
    $_SESSION['flash'] = 'Please fill all fields (password ≥ 8 chars).';
    redirect('/register');
  }

  // refuse duplicates
  $chk = $pdo->prepare('SELECT 1 FROM users WHERE email=?');
  $chk->execute([$email]);
  if ($chk->fetch()) {
    $_SESSION['flash'] = 'Email already registered.';
    redirect('/register');
  }

  $hash = password_hash($pass, PASSWORD_DEFAULT);
  $pdo->prepare('INSERT INTO users(full_name, email, password_hash, date_of_birth, onboard_step, needs_tutorial)
                 VALUES (?,?,?,?,?,true)')
      ->execute([$name, $email, $hash, $dob?:null, 1]);

  // Log in + jump to step 2
  $uid = (int)$pdo->lastInsertId();
  $_SESSION['uid'] = $uid;
  redirect('/onboard/rules');
}
