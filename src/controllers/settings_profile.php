<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../webauthn.php';

function settings_profile_show(PDO $pdo){
  require_login(); $u = uid();
  $stmt = $pdo->prepare('SELECT email, full_name, date_of_birth FROM users WHERE id=?');
  $stmt->execute([$u]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
  $user['full_name'] = pii_decrypt($user['full_name'] ?? null);

  $passkeys = webauthn_list_passkeys($pdo, $u);

  view('settings/profile', compact('user', 'passkeys'));
}

function settings_profile_update(PDO $pdo){
  verify_csrf(); require_login(); $u = uid();

  $full = trim($_POST['full_name'] ?? '');
  $dob  = $_POST['date_of_birth'] ?? null;

  try {
    $nameToStore = $full !== '' ? pii_encrypt($full) : null;
  } catch (Throwable $e) {
    $_SESSION['flash'] = __('We could not secure your profile. Please contact an administrator.');
    redirect('/settings/profile');
  }

  try {
    $stmt = $pdo->prepare('UPDATE users SET full_name=?, date_of_birth=? WHERE id=?');
    $stmt->execute([$nameToStore, $dob ?: null, $u]);
    $_SESSION['flash_success'] = __('Profile details updated.');
  } catch (Throwable $e){
    $_SESSION['flash'] = __('Could not update profile.');
  }
  redirect('/settings/profile');
}

function settings_profile_password_update(PDO $pdo){
  verify_csrf(); require_login(); $u = uid();

  $pass = (string)($_POST['password'] ?? '');
  $pass2 = (string)($_POST['password2'] ?? '');

  if ($pass === '' || $pass2 === '') {
    $_SESSION['flash'] = __('Please provide and confirm a new password.');
    redirect('/settings/profile');
  }

  if ($pass !== $pass2) {
    $_SESSION['flash'] = __('Passwords must match.');
    redirect('/settings/profile');
  }

  if (strlen($pass) < 8) {
    $_SESSION['flash'] = __('Passwords must be at least 8 characters long.');
    redirect('/settings/profile');
  }

  try {
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?');
    $stmt->execute([$hash, $u]);
    $_SESSION['flash_success'] = __('Password updated.');
  } catch (Throwable $e) {
    $_SESSION['flash'] = __('Could not update password.');
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
