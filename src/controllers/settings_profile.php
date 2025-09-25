<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../webauthn.php';

function settings_profile_show(PDO $pdo){
  require_login(); $u = uid();
  $stmt = $pdo->prepare('SELECT email, full_name, date_of_birth FROM users WHERE id=?');
  $stmt->execute([$u]); $user = $stmt->fetch(PDO::FETCH_ASSOC);

  $passkeys = webauthn_list_passkeys($pdo, $u);

  view('settings/profile', compact('user', 'passkeys'));
}

function settings_profile_update(PDO $pdo){
  verify_csrf(); require_login(); $u = uid();

  $full = trim($_POST['full_name'] ?? '');
  $dob  = $_POST['date_of_birth'] ?? null;

  // Optional: change password
  $pass = $_POST['password'] ?? '';
  $pass2= $_POST['password2'] ?? '';

  try {
    if ($pass !== '' || $pass2 !== '') {
      if ($pass !== $pass2 || strlen($pass) < 8) {
        $_SESSION['flash'] = 'Passwords must match and be at least 8 characters.';
        redirect('/settings/profile');
      }
      $hash = password_hash($pass, PASSWORD_DEFAULT);
      $stmt = $pdo->prepare('UPDATE users SET full_name=?, date_of_birth=?, password_hash=? WHERE id=?');
      $stmt->execute([$full ?: null, $dob ?: null, $hash, $u]);
    } else {
      $stmt = $pdo->prepare('UPDATE users SET full_name=?, date_of_birth=? WHERE id=?');
      $stmt->execute([$full ?: null, $dob ?: null, $u]);
    }
    $_SESSION['flash_success'] = 'Profile updated.';
  } catch (Throwable $e){
    $_SESSION['flash'] = 'Could not update profile.';
  }
  redirect('/settings/profile');
}

function settings_passkeys_delete(PDO $pdo)
{
  verify_csrf();
  require_login();
  $u = uid();
  $id = (int)($_POST['id'] ?? 0);

  if ($id > 0 && webauthn_delete_passkey($pdo, $u, $id)) {
    $_SESSION['flash_success'] = __('Passkey removed.');
  } else {
    $_SESSION['flash'] = __('Could not remove passkey.');
  }

  redirect('/settings/profile');
}
