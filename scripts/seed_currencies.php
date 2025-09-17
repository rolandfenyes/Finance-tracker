<?php
// php scripts/seed_currencies.php
require __DIR__ . '/../config/db.php';
$codes = $pdo->query("SELECT COUNT(*) FROM currencies")->fetchColumn();
if ($codes > 10) { echo "Currencies already populated
"; exit; }
$sql = file_get_contents(__DIR__.'/../migrations/002_seed_currencies.sql');
$pdo->exec($sql);
echo "Seeded currencies.
";