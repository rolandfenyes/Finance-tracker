<?php
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../src/helpers.php';
require __DIR__ . '/../src/services/email_notifications.php';

$type = $argv[1] ?? '';
$validTypes = ['tips', 'weekly', 'monthly', 'yearly', 'ef-motivation'];
if (!in_array($type, $validTypes, true)) {
    fwrite(STDERR, "Usage: php scripts/send_user_emails.php [" . implode('|', $validTypes) . "]\n");
    exit(1);
}

$requiresVerification = in_array($type, ['tips', 'weekly', 'monthly', 'yearly', 'ef-motivation'], true);

$stmt = $pdo->query("SELECT id, email, full_name, email_verified_at, email_verification_token, desired_language FROM users WHERE email IS NOT NULL AND email <> '' ORDER BY id");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$users) {
    fwrite(STDOUT, "No users found.\n");
    exit(0);
}

$sent = 0;
$skipped = 0;

foreach ($users as $user) {
    $email = trim((string)$user['email']);
    if ($email === '') {
        $skipped++;
        continue;
    }

    if ($requiresVerification && empty($user['email_verified_at'])) {
        $skipped++;
        continue;
    }

    $user['full_name_plain'] = $user['full_name'] ? pii_decrypt($user['full_name']) : '';
    if (array_key_exists('desired_language', $user)) {
        $user['desired_language_raw'] = $user['desired_language'];
    }

    switch ($type) {
        case 'tips':
            $ok = email_send_tips($user);
            break;
        case 'weekly':
            $ok = email_send_weekly_results($pdo, $user);
            break;
        case 'monthly':
            $ok = email_send_monthly_results($pdo, $user);
            break;
        case 'yearly':
            $ok = email_send_yearly_results($pdo, $user);
            break;
        case 'ef-motivation':
            $ok = email_send_emergency_motivation($pdo, $user);
            break;
        default:
            $ok = false;
    }

    if ($ok) {
        $sent++;
        fwrite(STDOUT, "[SENT] {$email}\n");
    } else {
        fwrite(STDERR, "[FAILED] {$email}\n");
    }
}

fwrite(STDOUT, "Done. Sent: {$sent}, Skipped: {$skipped}\n");
