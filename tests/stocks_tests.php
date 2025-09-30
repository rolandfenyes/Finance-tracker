<?php

declare(strict_types=1);

date_default_timezone_set('UTC');

spl_autoload_register(function (string $class): void {
    $prefix = 'MyMoneyMap\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/../src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

require __DIR__ . '/../src/fx.php';

use MyMoneyMap\Stocks\Adapters\PriceProviderAdapter;
use MyMoneyMap\Stocks\DTO\Holding;
use MyMoneyMap\Stocks\DTO\LiveQuote;
use MyMoneyMap\Stocks\DTO\QuoteHistory;
use MyMoneyMap\Stocks\DTO\TradeInput;
use MyMoneyMap\Stocks\Repositories\PriceRepository;
use MyMoneyMap\Stocks\Repositories\SettingsRepository;
use MyMoneyMap\Stocks\Repositories\StockRepository;
use MyMoneyMap\Stocks\Repositories\TradeRepository;
use MyMoneyMap\Stocks\Services\ChartsService;
use MyMoneyMap\Stocks\Services\PortfolioService;
use MyMoneyMap\Stocks\Services\PriceDataService;
use MyMoneyMap\Stocks\Services\SignalsService;
use MyMoneyMap\Stocks\Services\TradeService;

final class TestPriceAdapter implements PriceProviderAdapter
{
    /** @var array<string, LiveQuote> */
    public array $quotes = [];

    /** @var array<string, QuoteHistory> */
    public array $history = [];

    /**
     * @param array<int, string> $symbols
     * @return array<string, LiveQuote>
     */
    public function fetchLiveQuotes(array $symbols): array
    {
        $out = [];
        $now = new DateTimeImmutable();
        foreach ($symbols as $symbol) {
            $symbol = strtoupper($symbol);
            $out[$symbol] = $this->quotes[$symbol] ?? new LiveQuote($symbol, 0.0, 0.0, 0.0, 0.0, 0.0, $now, true);
        }
        return $out;
    }

    public function fetchDailyHistory(string $symbol, \DateTimeInterface $from, \DateTimeInterface $to): QuoteHistory
    {
        $symbol = strtoupper($symbol);
        if (isset($this->history[$symbol])) {
            return $this->history[$symbol];
        }
        return new QuoteHistory([], DateTimeImmutable::createFromInterface($from), DateTimeImmutable::createFromInterface($to));
    }
}

final class TestContext
{
    public function __construct(
        public PDO $pdo,
        public StockRepository $stocks,
        public TradeRepository $trades,
        public SettingsRepository $settings,
        public PriceRepository $prices,
        public TestPriceAdapter $adapter,
        public PriceDataService $priceService,
        public SignalsService $signals,
        public PortfolioService $portfolio,
        public TradeService $tradeService,
        public ChartsService $charts,
    ) {
    }
}

/**
 * @return TestContext
 */
function create_context(): TestContext
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    create_schema($pdo);
    seed_user($pdo, 1, 'Test User', 'USD');

    $priceRepo = new PriceRepository($pdo);
    $stockRepo = new StockRepository($pdo);
    $tradeRepo = new TradeRepository($pdo);
    $settingsRepo = new SettingsRepository($pdo);
    $adapter = new TestPriceAdapter();
    $priceService = new PriceDataService($priceRepo, $adapter, 1);
    $signals = new SignalsService($pdo, $priceService, $settingsRepo);
    $portfolio = new PortfolioService($pdo, $tradeRepo, $stockRepo, $settingsRepo, $priceService, $signals);
    $tradeService = new TradeService($pdo, $stockRepo, $tradeRepo, $settingsRepo);
    $charts = new ChartsService($pdo, $tradeRepo, $priceService);

    return new TestContext($pdo, $stockRepo, $tradeRepo, $settingsRepo, $priceRepo, $adapter, $priceService, $signals, $portfolio, $tradeService, $charts);
}

function create_schema(PDO $pdo): void
{
    $stmts = [
        'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)',
        'CREATE TABLE user_currencies (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, code TEXT NOT NULL, is_main INTEGER NOT NULL DEFAULT 0, FOREIGN KEY(user_id) REFERENCES users(id))',
        'CREATE TABLE stocks (id INTEGER PRIMARY KEY AUTOINCREMENT, symbol TEXT NOT NULL, exchange TEXT NOT NULL, name TEXT, currency TEXT NOT NULL, sector TEXT, industry TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(symbol, exchange))',
        'CREATE TABLE stock_positions (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, stock_id INTEGER NOT NULL, qty REAL NOT NULL DEFAULT 0, avg_cost_ccy REAL NOT NULL DEFAULT 0, avg_cost_currency TEXT NOT NULL DEFAULT "USD", created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(user_id, stock_id), FOREIGN KEY(user_id) REFERENCES users(id), FOREIGN KEY(stock_id) REFERENCES stocks(id))',
        'CREATE TABLE stock_trades (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, stock_id INTEGER NOT NULL, side TEXT NOT NULL, qty REAL NOT NULL, price REAL NOT NULL, fee REAL NOT NULL DEFAULT 0, currency TEXT NOT NULL, executed_at TEXT NOT NULL, note TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(user_id) REFERENCES users(id), FOREIGN KEY(stock_id) REFERENCES stocks(id))',
        'CREATE TABLE stock_lots (id INTEGER PRIMARY KEY AUTOINCREMENT, position_id INTEGER NOT NULL, qty_open REAL NOT NULL, qty_closed REAL NOT NULL DEFAULT 0, open_price REAL NOT NULL, fee REAL NOT NULL DEFAULT 0, currency TEXT NOT NULL, opened_at TEXT NOT NULL, closed_at TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(position_id) REFERENCES stock_positions(id))',
        'CREATE TABLE stock_realized_pl (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, stock_id INTEGER NOT NULL, sell_trade_id INTEGER, realized_pl_base REAL NOT NULL DEFAULT 0, realized_pl_ccy REAL NOT NULL DEFAULT 0, method TEXT NOT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(user_id) REFERENCES users(id), FOREIGN KEY(stock_id) REFERENCES stocks(id))',
        'CREATE TABLE watchlist (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, stock_id INTEGER NOT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(user_id, stock_id))',
        'CREATE TABLE user_settings_stocks (user_id INTEGER PRIMARY KEY, cost_basis_unrealized TEXT NOT NULL DEFAULT "AVERAGE", realized_method TEXT NOT NULL DEFAULT "FIFO", target_allocations TEXT, FOREIGN KEY(user_id) REFERENCES users(id))',
        'CREATE TABLE stock_prices_last (stock_id INTEGER PRIMARY KEY, last REAL NOT NULL DEFAULT 0, prev_close REAL NOT NULL DEFAULT 0, day_high REAL NOT NULL DEFAULT 0, day_low REAL NOT NULL DEFAULT 0, volume REAL NOT NULL DEFAULT 0, provider_ts TEXT, updated_at TEXT DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(stock_id) REFERENCES stocks(id))',
        'CREATE TABLE price_daily (id INTEGER PRIMARY KEY AUTOINCREMENT, stock_id INTEGER NOT NULL, date TEXT NOT NULL, open REAL NOT NULL DEFAULT 0, high REAL NOT NULL DEFAULT 0, low REAL NOT NULL DEFAULT 0, close REAL NOT NULL DEFAULT 0, volume REAL NOT NULL DEFAULT 0, provider TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(stock_id, date), FOREIGN KEY(stock_id) REFERENCES stocks(id))',
        'CREATE TABLE fx_rates (rate_date TEXT NOT NULL, base_code TEXT NOT NULL, code TEXT NOT NULL, rate REAL NOT NULL, PRIMARY KEY(rate_date, base_code, code))',
    ];
    foreach ($stmts as $sql) {
        $pdo->exec($sql);
    }
}

function seed_user(PDO $pdo, int $userId, string $name, string $currency): void
{
    $pdo->prepare('INSERT INTO users(id, name) VALUES (?, ?)')->execute([$userId, $name]);
    $pdo->prepare('INSERT INTO user_currencies(user_id, code, is_main) VALUES (?, ?, 1)')->execute([$userId, strtoupper($currency)]);
}

function assert_true(bool $condition, string $message = 'Assertion failed'): void
{
    if (!$condition) {
        throw new AssertionError($message);
    }
}

function assert_equals(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        $msg = $message !== '' ? $message : sprintf('Expected %s but got %s', var_export($expected, true), var_export($actual, true));
        throw new AssertionError($msg);
    }
}

function assert_float(float $expected, float $actual, float $delta = 0.0001, string $message = ''): void
{
    if (abs($expected - $actual) > $delta) {
        $msg = $message !== '' ? $message : sprintf('Expected %.4f but got %.4f', $expected, $actual);
        throw new AssertionError($msg);
    }
}

/** @var list<array{string, callable}> $tests */
$tests = [];

function test(string $name, callable $fn): void
{
    global $tests;
    $tests[] = [$name, $fn];
}

test('FIFO realized P/L and remaining lot averages', function (): void {
    $ctx = create_context();
    $userId = 1;
    $date1 = new DateTimeImmutable('2023-01-02 10:00:00');
    $date2 = new DateTimeImmutable('2023-01-10 10:00:00');
    $date3 = new DateTimeImmutable('2023-02-01 09:30:00');

    $res1 = $ctx->tradeService->recordTrade(new TradeInput($userId, 'FIFO', 'NYSE', 'FIFO Corp', 'USD', 'BUY', 100, 10.0, 1.0, $date1));
    assert_true($res1->success, 'First buy failed');
    $res2 = $ctx->tradeService->recordTrade(new TradeInput($userId, 'FIFO', 'NYSE', 'FIFO Corp', 'USD', 'BUY', 50, 12.0, 1.0, $date2));
    assert_true($res2->success, 'Second buy failed');
    $res3 = $ctx->tradeService->recordTrade(new TradeInput($userId, 'FIFO', 'NYSE', 'FIFO Corp', 'USD', 'SELL', 120, 15.0, 1.0, $date3));
    assert_true($res3->success, 'Sell failed');

    $stock = $ctx->stocks->findOneBySymbol('FIFO');
    assert_true($stock !== null, 'Stock not stored');

    $realized = $ctx->trades->sumRealizedForStock($userId, (int) $stock['id']);
    assert_float(557.6, $realized, 0.01, 'Realized P/L mismatch');

    $position = $ctx->trades->findPosition($userId, (int) $stock['id']);
    assert_true($position !== null, 'Position missing');
    assert_float(30.0, (float) $position['qty'], 0.0001, 'Remaining quantity incorrect');
    assert_float(12.02, (float) $position['avg_cost_ccy'], 0.001, 'Average cost after sells incorrect');
});

test('Average cost recalculates after deleting trade', function (): void {
    $ctx = create_context();
    $userId = 1;
    $d1 = new DateTimeImmutable('2023-03-01');
    $d2 = new DateTimeImmutable('2023-03-15');

    $buy1 = $ctx->tradeService->recordTrade(new TradeInput($userId, 'AVGX', 'NASDAQ', 'Average Inc', 'USD', 'BUY', 10, 100.0, 0.0, $d1));
    assert_true($buy1->success);
    $buy2 = $ctx->tradeService->recordTrade(new TradeInput($userId, 'AVGX', 'NASDAQ', 'Average Inc', 'USD', 'BUY', 10, 120.0, 0.0, $d2));
    assert_true($buy2->success);

    $stock = $ctx->stocks->findOneBySymbol('AVGX');
    $position = $ctx->trades->findPosition($userId, (int) $stock['id']);
    assert_float(110.0, (float) $position['avg_cost_ccy'], 0.0001);

    $ctx->tradeService->deleteTrade($userId, (int) $buy2->tradeId);

    $positionAfter = $ctx->trades->findPosition($userId, (int) $stock['id']);
    assert_float(10.0, (float) $positionAfter['qty'], 0.0001);
    assert_float(100.0, (float) $positionAfter['avg_cost_ccy'], 0.0001);
});

test('Portfolio snapshot converts currencies using FX rates', function (): void {
    $ctx = create_context();
    $userId = 1;
    $buyDate = new DateTimeImmutable('2023-05-20');
    $quoteDate = new DateTimeImmutable('2023-06-01 15:30:00');

    _fx_store($ctx->pdo, $buyDate->format('Y-m-d'), 'USD', 1.10);
    _fx_store($ctx->pdo, $quoteDate->format('Y-m-d'), 'USD', 1.20);

    $buy = $ctx->tradeService->recordTrade(new TradeInput($userId, 'ACM', 'PAR', 'Acme SA', 'EUR', 'BUY', 5, 100.0, 2.0, $buyDate));
    assert_true($buy->success);

    $ctx->adapter->quotes['ACM'] = new LiveQuote('ACM', 110.0, 108.0, 111.0, 107.0, 1000000.0, $quoteDate, false);

    $snapshot = $ctx->portfolio->buildSnapshot($userId, '6M');
    assert_equals('USD', $snapshot->baseCurrency);
    assert_float(660.0, $snapshot->totalMarketValue, 0.01, 'Market value in base currency incorrect');
    assert_float(602.4, $snapshot->totalCost, 0.01, 'Total cost base incorrect: got ' . $snapshot->totalCost);
    assert_float(57.6, $snapshot->unrealized, 0.01, 'Unrealized P/L incorrect');
    assert_float(-552.2, $snapshot->cashImpact, 0.1, 'Cash impact incorrect');
    assert_float(12.0, $snapshot->dailyPL, 0.01, 'Daily P/L base incorrect');
    assert_equals(1, count($snapshot->holdings));
});

test('Signals service derives momentum and RSI insights', function (): void {
    $ctx = create_context();
    $userId = 1;
    $stockId = $ctx->stocks->create('MOMO', 'NYSE', 'Momentum Corp', 'USD');
    $today = new DateTimeImmutable('now');
    $start = $today->sub(new DateInterval('P59D'));

    $date = $start;
    $price = 50.0;
    while ($date <= $today) {
        $ctx->prices->insertDailyPrice($stockId, $date, [
            'open' => $price,
            'high' => $price + 1,
            'low' => $price - 1,
            'close' => $price,
            'volume' => 10000,
            'provider' => 'test',
        ]);
        $date = $date->add(new DateInterval('P1D'));
        $price += 0.5;
    }

    $ctx->pdo->prepare('INSERT INTO user_settings_stocks(user_id, target_allocations) VALUES(?, ?)')
        ->execute([$userId, json_encode(['MOMO' => 25.0], JSON_THROW_ON_ERROR)]);

    $holding = new Holding(
        $stockId,
        'MOMO',
        'NYSE',
        'Momentum Corp',
        'USD',
        15.0,
        55.0,
        55.0,
        'USD',
        $price - 0.5,
        $price - 0.5,
        15.0 * ($price - 0.5),
        15.0 * ($price - 0.5),
        150.0,
        20.0,
        7.5,
        7.5,
        10.0,
        'Tech',
        null,
        null,
        false
    );

    $insights = $ctx->signals->positionInsights($userId, $holding);
    $titles = array_map(static fn($insight) => $insight->title, $insights);
    $joined = implode(', ', $titles);
    assert_true(in_array('Momentum', $titles, true), 'Momentum not found: ' . $joined);
    assert_true(in_array('Potential add', $titles, true), 'Potential add not found: ' . $joined);
    assert_true(in_array('Overbought', $titles, true), 'Overbought not found: ' . $joined);
    assert_true(in_array('Consider stop update', $titles, true), 'Consider stop update not found: ' . $joined);
    assert_true(in_array('Position health', $titles, true), 'Position health not found: ' . $joined);
});

test('Integration: trades feed portfolio metrics', function (): void {
    $ctx = create_context();
    $userId = 1;
    $now = new DateTimeImmutable('now');
    $buyUsdDate = $now->sub(new DateInterval('P20D'));
    $buyEurDate = $now->sub(new DateInterval('P18D'));
    $sellUsdDate = $now->sub(new DateInterval('P5D'));
    $quoteDate = $now;

    _fx_store($ctx->pdo, $buyEurDate->format('Y-m-d'), 'USD', 1.05);
    _fx_store($ctx->pdo, $quoteDate->format('Y-m-d'), 'USD', 1.10);

    $usdBuy = $ctx->tradeService->recordTrade(new TradeInput($userId, 'USX', 'NYSE', 'US Stock', 'USD', 'BUY', 10, 50.0, 1.0, $buyUsdDate));
    assert_true($usdBuy->success);
    $eurBuy = $ctx->tradeService->recordTrade(new TradeInput($userId, 'ERX', 'FRA', 'Euro Stock', 'EUR', 'BUY', 5, 40.0, 0.5, $buyEurDate));
    assert_true($eurBuy->success);
    $usdSell = $ctx->tradeService->recordTrade(new TradeInput($userId, 'USX', 'NYSE', 'US Stock', 'USD', 'SELL', 4, 60.0, 1.0, $sellUsdDate));
    assert_true($usdSell->success);

    $ctx->adapter->quotes['USX'] = new LiveQuote('USX', 55.0, 54.0, 56.0, 53.5, 2000000.0, $quoteDate, false);
    $ctx->adapter->quotes['ERX'] = new LiveQuote('ERX', 42.0, 41.0, 43.0, 40.5, 500000.0, $quoteDate, false);

    $snapshot = $ctx->portfolio->buildSnapshot($userId, '6M');
    assert_equals(2, count($snapshot->holdings));
    $realized = $ctx->trades->sumRealized($userId);
    assert_float(38.6, $realized, 0.01, 'Realized P/L after sell incorrect');
    $symbols = array_map(static fn($h) => $h->symbol, $snapshot->holdings);
    sort($symbols);
    assert_equals(['ERX', 'USX'], $symbols);
});

$failures = 0;
foreach ($tests as [$name, $fn]) {
    try {
        $fn();
        echo ". $name\n";
    } catch (Throwable $e) {
        $failures++;
        echo "F $name: " . $e->getMessage() . "\n";
    }
}

echo $failures === 0 ? "All tests passed\n" : sprintf("%d test(s) failed\n", $failures);
exit($failures === 0 ? 0 : 1);
