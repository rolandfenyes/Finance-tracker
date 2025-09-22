<?php
require_once __DIR__ . '/../helpers.php';

function settings_theme_show(PDO $pdo)
{
  require_login();

  $themes = available_themes();
  $currentTheme = current_theme_slug();
  $currentMeta = theme_meta($currentTheme) ?? [];

  view('settings/theme', compact('themes', 'currentTheme', 'currentMeta'));
}

function settings_theme_update(PDO $pdo)
{
  verify_csrf();
  require_login();

  $selected = trim($_POST['theme'] ?? '');
  $catalog = available_themes();
  $slug = isset($catalog[$selected]) ? $selected : default_theme_slug();

  $stmt = $pdo->prepare('UPDATE users SET theme=? WHERE id=?');
  $stmt->execute([$slug, uid()]);

  $_SESSION['flash_success'] = __('Theme updated.');
  redirect('/settings/theme');
}
