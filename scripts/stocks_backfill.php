<?php
require __DIR__ . '/../config/load_env.php';
$config = require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../src/stocks/PriceProviderAdapter.php';
require __DIR__ . '/../src/stocks/Adapters/NullPriceProvider.php';
require __DIR__ . '/../src/stocks/Adapters/FinnhubAdapter.php';
require __DIR__ . '/../src/stocks/PriceDataService.php';

use Stocks\Adapters\FinnhubAdapter;
use Stocks\Adapters\NullPriceProvider;
use Stocks\PriceDataService;

$options = getopt('', ['symbol:', 'from::', 'to::']);
$symbol = isset($options['symbol']) ? strtoupper(trim($options['symbol'])) : null;
if (!$symbol) {
    fwrite(STDERR, "Usage: php scripts/stocks_backfill.php --symbol=TICKER [--from=YYYY-MM-DD] [--to=YYYY-MM-DD]\n");
    exit(1);
}
$from = $options['from'] ?? date('Y-m-d', strtotime('-6 months'));
$to = $options['to'] ?? date('Y-m-d');

$providerName = strtolower($config['stocks']['provider'] ?? 'null');
$adapter = new NullPriceProvider();
if ($providerName === 'finnhub') {
    $providerCfg = $config['stocks']['providers']['finnhub'] ?? [];
    $apiKey = $providerCfg['api_key'] ?? getenv('FINNHUB_API_KEY');
    $baseUrl = $providerCfg['base_url'] ?? getenv('FINNHUB_BASE_URL') ?: 'https://finnhub.io/api/v1';
    if (!empty($apiKey)) {
        $adapter = new FinnhubAdapter($apiKey, $baseUrl);
    }
}

$ttl = (int)($config['stocks']['refresh_seconds'] ?? 10);
$service = new PriceDataService($pdo, $adapter, $ttl);

$candles = $service->getDailyHistory($symbol, $from, $to);
printf("Stored %d candles for %s (%s → %s)\n", count($candles), $symbol, $from, $to);
