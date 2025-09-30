<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/config/load_env.php';
$config = require $root . '/config/config.php';
require $root . '/config/db.php';
require $root . '/src/helpers.php';
require $root . '/src/fx.php';

spl_autoload_register(function (string $class) use ($root): void {
    $prefix = 'MyMoneyMap\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = $root . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use MyMoneyMap\Stocks\Adapters\FinnhubAdapter;
use MyMoneyMap\Stocks\Adapters\NullAdapter;
use MyMoneyMap\Stocks\Repositories\PriceRepository;
use MyMoneyMap\Stocks\Repositories\SettingsRepository;
use MyMoneyMap\Stocks\Repositories\StockRepository;
use MyMoneyMap\Stocks\Repositories\TradeRepository;
use MyMoneyMap\Stocks\Services\PriceDataService;
use MyMoneyMap\Stocks\Services\SignalsService;
use MyMoneyMap\Stocks\Services\PortfolioService;
use MyMoneyMap\Stocks\Services\TradeService;
use MyMoneyMap\Stocks\Services\ChartsService;

$options = getopt('', ['range::']);
$range = strtoupper($options['range'] ?? '1Y');
$symbols = array_values(array_filter(array_slice($argv, 1), static fn ($arg) => !str_starts_with($arg, '--')));

if ($symbols === []) {
    fwrite(STDERR, "Usage: php scripts/stocks_backfill.php SYMBOL [SYMBOL...] [--range=1Y]\n");
    exit(1);
}

$pdo = $pdo ?? null;
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "Database connection missing.\n");
    exit(1);
}

$provider = strtolower(getenv('STOCKS_PROVIDER') ?: 'null');
$adapter = match ($provider) {
    'finnhub' => new FinnhubAdapter(getenv('FINNHUB_API_KEY') ?: ''),
    default => new NullAdapter(),
};

$priceRepo = new PriceRepository($pdo);
$stockRepo = new StockRepository($pdo);
$tradeRepo = new TradeRepository($pdo);
$settingsRepo = new SettingsRepository($pdo);
$priceService = new PriceDataService($priceRepo, $adapter, 5);
$signals = new SignalsService($pdo, $priceService, $settingsRepo);
$portfolio = new PortfolioService($pdo, $tradeRepo, $stockRepo, $settingsRepo, $priceService, $signals);
$tradeService = new TradeService($pdo, $stockRepo, $tradeRepo, $settingsRepo);
$charts = new ChartsService($pdo, $tradeRepo, $priceService);

$to = new DateTimeImmutable();
$from = match ($range) {
    '1W' => $to->sub(new DateInterval('P7D')),
    '1M' => $to->sub(new DateInterval('P1M')),
    '3M' => $to->sub(new DateInterval('P3M')),
    '6M' => $to->sub(new DateInterval('P6M')),
    '5Y' => $to->sub(new DateInterval('P5Y')),
    default => $to->sub(new DateInterval('P1Y')),
};

foreach ($symbols as $symbolRaw) {
    $symbol = strtoupper(trim($symbolRaw));
    if ($symbol === '') {
        continue;
    }
    $stock = $stockRepo->findOneBySymbol($symbol);
    if (!$stock) {
        $stockId = $stockRepo->upsert([
            'symbol' => $symbol,
            'exchange' => 'MANUAL',
            'name' => $symbol,
            'currency' => 'USD',
        ]);
        $stock = $stockRepo->findByIds([$stockId])[0] ?? null;
    }
    if (!$stock) {
        fwrite(STDERR, "Could not ensure stock record for {$symbol}\n");
        continue;
    }

    $history = $priceService->getDailyHistory((int) $stock['id'], $symbol, $from, $to);
    $count = iterator_count($history->getIterator());
    fwrite(STDOUT, "Fetched {$count} candles for {$symbol} ({$from->format('Y-m-d')} -> {$to->format('Y-m-d')})\n");
}
