<?php
require __DIR__ . '/../../config/load_env.php';
$config = require __DIR__ . '/../../config/config.php';
try {
    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $config['db']['host'], $config['db']['port'], $config['db']['name']);
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Skipping tests: database unavailable ({$e->getMessage()})\n");
    exit(0);
}
require __DIR__ . '/../../src/fx.php';
require __DIR__ . '/../../src/stocks/PriceProviderAdapter.php';
require __DIR__ . '/../../src/stocks/Adapters/NullPriceProvider.php';
require __DIR__ . '/../../src/stocks/PriceDataService.php';
require __DIR__ . '/../../src/stocks/TradeService.php';
require __DIR__ . '/../../src/stocks/SignalsService.php';

use Stocks\Adapters\NullPriceProvider;
use Stocks\PriceDataService;
use Stocks\SignalsService;
use Stocks\TradeService;

function assert_close($expected, $actual, $delta = 0.01, $message = '') {
    if (abs($expected - $actual) > $delta) {
        throw new RuntimeException($message . ' expected ' . $expected . ' got ' . $actual);
    }
}

try {
    $pdo->beginTransaction();

    $pdo->exec("INSERT INTO users(email, password_hash, full_name) VALUES('stocks_test@example.com','x','Test User')");
    $userId = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO user_currencies(user_id, code, is_main) VALUES(?,?,true)')->execute([$userId, 'USD']);

    $tradeService = new TradeService($pdo);

    $tradeService->recordTrade($userId, [
        'symbol' => 'TEST',
        'side' => 'BUY',
        'quantity' => 10,
        'price' => 100,
        'currency' => 'USD',
        'fee' => 0.5,
        'executed_at' => '2023-01-02 15:30:00'
    ]);
    $tradeService->recordTrade($userId, [
        'symbol' => 'TEST',
        'side' => 'BUY',
        'quantity' => 5,
        'price' => 110,
        'currency' => 'USD',
        'fee' => 0.5,
        'executed_at' => '2023-01-15 15:30:00'
    ]);
    $tradeService->recordTrade($userId, [
        'symbol' => 'TEST',
        'side' => 'SELL',
        'quantity' => 8,
        'price' => 150,
        'currency' => 'USD',
        'fee' => 0.5,
        'executed_at' => '2023-02-10 15:30:00'
    ]);

    $pos = $pdo->query("SELECT qty, avg_cost_ccy FROM stock_positions sp JOIN stocks s ON s.id=sp.stock_id WHERE sp.user_id={$userId} AND s.symbol='TEST'")->fetch(PDO::FETCH_ASSOC);
    assert_close(7.0, (float)$pos['qty'], 0.0001, 'Position quantity mismatch');
    assert_close(107.14, (float)$pos['avg_cost_ccy'], 0.1, 'Average cost mismatch');

    $realized = $pdo->query("SELECT realized_pl_ccy FROM stock_realized_pl WHERE user_id={$userId} AND stock_id=(SELECT id FROM stocks WHERE symbol='TEST')")->fetchColumn();
    assert_close(399.5, (float)$realized, 0.5, 'Realized P/L mismatch');

    $pdo->exec("DELETE FROM price_daily");
    $stockId = $pdo->query("SELECT id FROM stocks WHERE symbol='TEST'")->fetchColumn();
    $insert = $pdo->prepare('INSERT INTO price_daily(stock_id, date, open, high, low, close, volume) VALUES(?,?,?,?,?,?,?) ON CONFLICT(stock_id,date) DO UPDATE SET close=excluded.close');
    $start = new DateTime('2023-01-01');
    for ($i=0; $i<60; $i++) {
        $date = (clone $start)->modify("+$i days")->format('Y-m-d');
        $price = 100 + $i * 0.5;
        $insert->execute([$stockId, $date, $price, $price + 1, $price - 1, $price, 1000000]);
    }

    $priceService = new PriceDataService($pdo, new NullPriceProvider(), 10);
    $signals = new SignalsService($pdo, $priceService);
    $holding = [
        'symbol' => 'TEST',
        'currency' => 'USD',
        'avg_cost' => 100,
        'qty' => 1,
        'last_price' => 130,
        'prev_close' => 128,
        'weight_pct' => 5.0,
    ];
    $analysis = $signals->analyze($userId, $holding, ['prev_close' => 128], ['user_id' => $userId, 'default_target' => 10]);
    if (empty($analysis['signals'])) {
        throw new RuntimeException('Signals should not be empty');
    }

    echo "TradeService + SignalsService tests passed\n";
    $pdo->rollBack();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Test failed: ' . $e->getMessage() . "\n");
    exit(1);
}
