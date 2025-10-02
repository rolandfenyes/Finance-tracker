<?php
require __DIR__ . '/../config/load_env.php';
$config = require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../src/stocks/TradeService.php';

$options = getopt('', ['user::', 'symbol::']);
$userId = isset($options['user']) ? (int)$options['user'] : null;
$symbol = isset($options['symbol']) ? strtoupper(trim($options['symbol'])) : null;

$tradeService = new Stocks\TradeService($pdo);

if ($userId && $symbol) {
    $stockId = $pdo->prepare('SELECT id FROM stocks WHERE UPPER(symbol)=?');
    $stockId->execute([$symbol]);
    $sid = $stockId->fetchColumn();
    if (!$sid) {
        fwrite(STDERR, "Unknown symbol $symbol\n");
        exit(1);
    }
    $tradeService->rebuildPositions($userId, (int)$sid);
    echo "Rebuilt positions for user {$userId}, symbol {$symbol}\n";
    exit(0);
}

if ($userId) {
    $tradeService->rebuildPositions($userId);
    echo "Rebuilt all stocks for user {$userId}\n";
    exit(0);
}

$users = $pdo->query('SELECT id FROM users')->fetchAll(PDO::FETCH_COLUMN);
foreach ($users as $uid) {
    $tradeService->rebuildPositions((int)$uid);
}

echo "Rebuilt positions for " . count($users) . " users\n";
