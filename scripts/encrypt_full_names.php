<?php
require __DIR__ . '/../config/load_env.php';
$config = require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../src/helpers.php';

if (!pii_crypto_is_configured()) {
    fwrite(STDERR, "MM_DATA_KEY is not configured. Set it before running this script." . PHP_EOL);
    exit(1);
}

$stmt = $pdo->query("SELECT id, full_name FROM users WHERE full_name IS NOT NULL AND full_name <> ''");
$updated = 0;
$skipped = 0;
$updateStmt = $pdo->prepare('UPDATE users SET full_name = ? WHERE id = ?');

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $wasEncrypted = null;
    $plain = pii_decrypt($row['full_name'], $wasEncrypted);
    if ($wasEncrypted === true) {
        $skipped++;
        continue;
    }
    if ($plain === null || $plain === '') {
        $skipped++;
        continue;
    }
    $cipher = pii_encrypt($plain);
    $updateStmt->execute([$cipher, $row['id']]);
    $updated++;
}

echo sprintf("Encrypted %d full_name values (%d already secure).%s", $updated, $skipped, PHP_EOL);
