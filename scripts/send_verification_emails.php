<?php
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../src/helpers.php';
require __DIR__ . '/../src/services/email_notifications.php';

$options = array_slice($argv, 1);
$refreshToken = false;

foreach ($options as $option) {
    if ($option === '--help' || $option === '-h') {
        fwrite(STDOUT, "Usage: php scripts/send_verification_emails.php [--refresh-token]\n");
        fwrite(STDOUT, "  --refresh-token  Generate a new verification token for each user.\n");
        exit(0);
    }

    if ($option === '--refresh-token') {
        $refreshToken = true;
        continue;
    }

    fwrite(STDERR, "Unknown option: {$option}\n");
    fwrite(STDERR, "Usage: php scripts/send_verification_emails.php [--refresh-token]\n");
    exit(1);
}

$stmt = $pdo->query("SELECT id, email, full_name, email_verified_at, email_verification_token, desired_language FROM users " .
    "WHERE email_verified_at IS NULL AND email IS NOT NULL AND email <> '' ORDER BY id");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$users) {
    fwrite(STDOUT, "No unverified users found.\n");
    exit(0);
}

$sent = 0;
$failed = 0;

foreach ($users as $user) {
    $email = trim((string)$user['email']);
    if ($email === '') {
        continue;
    }

    $user['full_name_plain'] = $user['full_name'] ? pii_decrypt($user['full_name']) : '';
    $preferred = trim((string)($user['desired_language'] ?? ''));
    $user['desired_language'] = $preferred !== '' ? $preferred : null;

    try {
        $ok = email_send_verification($pdo, $user, $refreshToken);
    } catch (Throwable $e) {
        $ok = false;
        error_log('[mail] Failed to send verification email to ' . $email . ': ' . $e->getMessage());
    }

    if ($ok) {
        $sent++;
        fwrite(STDOUT, "[SENT] {$email}\n");
    } else {
        $failed++;
        fwrite(STDERR, "[FAILED] {$email}\n");
    }
}

fwrite(STDOUT, "Done. Sent: {$sent}, Failed: {$failed}\n");
