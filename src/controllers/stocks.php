<?php

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../fx.php';
require_once __DIR__ . '/../stocks/PriceProviderAdapter.php';
require_once __DIR__ . '/../stocks/Adapters/NullPriceProvider.php';
require_once __DIR__ . '/../stocks/Adapters/FinnhubAdapter.php';
require_once __DIR__ . '/../stocks/PriceDataService.php';
require_once __DIR__ . '/../stocks/PortfolioService.php';
require_once __DIR__ . '/../stocks/TradeService.php';
require_once __DIR__ . '/../stocks/SignalsService.php';
require_once __DIR__ . '/../stocks/ChartsService.php';

use Stocks\Adapters\FinnhubAdapter;
use Stocks\Adapters\NullPriceProvider;
use Stocks\ChartsService;
use Stocks\PortfolioService;
use Stocks\PriceDataService;
use Stocks\SignalsService;
use Stocks\TradeService;

function stocks_index(PDO $pdo): void
{
    require_login();
    $userId = uid();
    $priceService = stocks_price_service($pdo);
    $portfolio = new PortfolioService($pdo, $priceService);
    $signalsService = new SignalsService($pdo, $priceService);
    $chartsService = new ChartsService($pdo, $priceService);

    $filters = [
        'search' => $_GET['q'] ?? null,
        'sector' => $_GET['sector'] ?? null,
        'currency' => $_GET['currency'] ?? null,
        'watchlist_only' => !empty($_GET['watchlist']),
        'realized_period' => $_GET['period'] ?? null,
    ];
    $overview = $portfolio->buildOverview($userId, $filters);
    $holdings = $overview['holdings'];
    $totals = $overview['totals'] + ['user_id' => $userId, 'default_target' => 10.0];

    $insights = [];
    foreach ($holdings as $holding) {
        $insights[$holding['symbol']] = $signalsService->analyze($userId, $holding, ['prev_close' => $holding['prev_close'] ?? null], $totals);
    }

    $portfolioChart = $chartsService->portfolioValueSeries($userId, $_GET['chartRange'] ?? '6M');
    $refreshSeconds = stocks_refresh_seconds($userId, $pdo);

    $userCurrencies = stocks_user_currencies($pdo, $userId);

    view('stocks/index', [
        'overview' => $overview,
        'insights' => $insights,
        'portfolioChart' => $portfolioChart,
        'filters' => $filters,
        'refreshSeconds' => $refreshSeconds,
        'userCurrencies' => $userCurrencies,
        'error' => $_GET['error'] ?? null,
    ]);
}

function stocks_detail(PDO $pdo, string $symbol): void
{
    require_login();
    $userId = uid();
    $symbol = strtoupper($symbol);
    $priceService = stocks_price_service($pdo);
    $signalsService = new SignalsService($pdo, $priceService);
    $chartsService = new ChartsService($pdo, $priceService);

    $stockRow = stocks_fetch_stock($pdo, $symbol);
    if (!$stockRow) {
        http_response_code(404);
        view('errors/404');
        return;
    }

    $baseCurrency = fx_user_main($pdo, $userId);
    $positionStmt = $pdo->prepare('SELECT sp.*, s.name, s.symbol, s.currency FROM stock_positions sp JOIN stocks s ON s.id = sp.stock_id WHERE sp.user_id=? AND UPPER(s.symbol)=?');
    $positionStmt->execute([$userId, $symbol]);
    $position = $positionStmt->fetch(PDO::FETCH_ASSOC);

    $quote = $priceService->getLiveQuotes([$symbol]);
    $quoteRow = $quote[0] ?? null;
    $historyRange = $_GET['range'] ?? '6M';
    $historyBounds = stocks_history_bounds($historyRange);
    $priceHistory = $priceService->getDailyHistory($symbol, $historyBounds['start'], $historyBounds['end']);

    $holdingLike = [
        'symbol' => $symbol,
        'currency' => $stockRow['currency'] ?? 'USD',
        'avg_cost' => $position['avg_cost_ccy'] ?? 0,
        'qty' => $position['qty'] ?? 0,
        'last_price' => $quoteRow['last'] ?? null,
        'prev_close' => $quoteRow['prev_close'] ?? null,
        'weight_pct' => $position && ($position['qty'] ?? 0) > 0 ? 0.0 : 0.0,
    ];
    $totals = ['user_id' => $userId, 'default_target' => 10.0];
    $insights = $signalsService->analyze($userId, $holdingLike, ['prev_close' => $quoteRow['prev_close'] ?? null], $totals);

    $positionSeries = $chartsService->positionValueSeries($userId, $symbol, $historyRange);

    $realizedStmt = $pdo->prepare('SELECT COALESCE(SUM(realized_pl_base),0) FROM stock_realized_pl WHERE user_id=? AND stock_id=? AND DATE_TRUNC(\'year\', closed_at)=DATE_TRUNC(\'year\', CURRENT_DATE)');
    $realizedStmt->execute([$userId, $stockRow['id']]);
    $realizedYtd = (float)$realizedStmt->fetchColumn();

    $settings = stocks_settings($pdo, $userId);
    $isWatched = stocks_is_watched($pdo, $userId, (int)$stockRow['id']);

    $userCurrencies = stocks_user_currencies($pdo, $userId);

    view('stocks/show', [
        'stock' => $stockRow,
        'position' => $position,
        'quote' => $quoteRow,
        'priceHistory' => $priceHistory,
        'insights' => $insights,
        'positionSeries' => $positionSeries,
        'realizedYtd' => $realizedYtd,
        'settings' => $settings,
        'isWatched' => $isWatched,
        'historyRange' => $historyRange,
        'baseCurrency' => $baseCurrency,
        'userCurrencies' => $userCurrencies,
    ]);
}

function stocks_trade(PDO $pdo): void
{
    verify_csrf();
    require_login();
    $userId = uid();
    $tradeService = new TradeService($pdo);
    $side = strtoupper($_POST['side'] ?? '');
    $symbol = strtoupper(trim($_POST['symbol'] ?? ''));
    $quantity = (float)($_POST['quantity'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $currency = $_POST['currency'] ?? 'USD';
    $fee = isset($_POST['fee']) ? (float)$_POST['fee'] : 0.0;
    $date = $_POST['trade_date'] ?? date('Y-m-d');
    $time = $_POST['trade_time'] ?? '15:30:00';
    $note = $_POST['note'] ?? null;
    $marketInput = $_POST['market'] ?? null;
    $market = $marketInput !== null ? strtoupper(trim((string)$marketInput)) : null;
    if ($market === '') {
        $market = null;
    }
    $executedAt = trim($date . ' ' . $time);

    $payload = [
        'symbol' => $symbol,
        'side' => $side,
        'quantity' => $quantity,
        'price' => $price,
        'currency' => $currency,
        'fee' => $fee,
        'executed_at' => $executedAt,
        'note' => $note,
        'market' => $market,
    ];
    try {
        $tradeService->recordTrade($userId, $payload);
        $destination = '/stocks/' . urlencode($symbol);
        if (!stocks_table_exists($pdo, 'stocks')) {
            $destination = '/stocks';
        }
        redirect($destination);
    } catch (Throwable $e) {
        error_log('[stocks_trade] ' . $e->getMessage());
        redirect('/stocks?error=trade');
    }
}

function stocks_delete_trade(PDO $pdo): void
{
    verify_csrf();
    require_login();
    $userId = uid();
    $tradeId = (int)($_POST['id'] ?? 0);
    if ($tradeId <= 0) {
        redirect('/stocks');
        return;
    }
    $tradeService = new TradeService($pdo);
    $tradeService->deleteTrade($userId, $tradeId);
    redirect('/stocks');
}

function stocks_toggle_watch(PDO $pdo, string $symbol): void
{
    verify_csrf();
    require_login();
    $userId = uid();
    $stock = stocks_fetch_stock($pdo, strtoupper($symbol));
    if (!$stock) {
        redirect('/stocks/' . urlencode($symbol));
        return;
    }
    $existsStmt = $pdo->prepare('SELECT id FROM watchlist WHERE user_id=? AND stock_id=?');
    $existsStmt->execute([$userId, $stock['id']]);
    $exists = $existsStmt->fetchColumn();
    if ($exists) {
        $pdo->prepare('DELETE FROM watchlist WHERE id=?')->execute([$exists]);
    } else {
        $pdo->prepare('INSERT INTO watchlist(user_id, stock_id, created_at) VALUES(?,?, NOW()) ON CONFLICT DO NOTHING')->execute([$userId, $stock['id']]);
    }
    redirect('/stocks/' . urlencode($symbol));
}

/**
 * @return array<int, array{code:string,is_main?:mixed}>
 */
function stocks_user_currencies(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT code, is_main FROM user_currencies WHERE user_id=? ORDER BY is_main DESC, code');
    $stmt->execute([$userId]);
    $currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($currencies) {
        return $currencies;
    }

    $main = fx_user_main($pdo, $userId);
    if ($main) {
        return [['code' => strtoupper($main), 'is_main' => true]];
    }

    return [['code' => 'USD', 'is_main' => true]];
}

function stocks_live_api(PDO $pdo): void
{
    require_login();
    $symbolsParam = $_GET['symbols'] ?? '';
    $symbols = array_filter(array_map('trim', explode(',', $symbolsParam)));
    $priceService = stocks_price_service($pdo);
    $quotes = $priceService->getLiveQuotes($symbols);
    json_response(['quotes' => $quotes]);
}

function stocks_history_api(PDO $pdo, string $symbol): void
{
    require_login();
    $range = $_GET['range'] ?? '6M';
    $priceService = stocks_price_service($pdo);
    $bounds = stocks_history_bounds($range);
    $priceHistory = $priceService->getDailyHistory(strtoupper($symbol), $bounds['start'], $bounds['end']);
    $chartsService = new ChartsService($pdo, $priceService);
    $series = $chartsService->positionValueSeries(uid(), strtoupper($symbol), $range);
    json_response([
        'price' => $priceHistory,
        'position' => $series,
    ]);
}

/**
 * @return array{start: string, end: string}
 */
function stocks_history_bounds(string $range): array
{
    $range = strtoupper($range);
    $end = date('Y-m-d');
    $start = match ($range) {
        '1D' => date('Y-m-d', strtotime('-1 day')),
        '5D' => date('Y-m-d', strtotime('-5 days')),
        '1M' => date('Y-m-d', strtotime('-1 month')),
        '6M' => date('Y-m-d', strtotime('-6 months')),
        '1Y' => date('Y-m-d', strtotime('-1 year')),
        '5Y' => date('Y-m-d', strtotime('-5 years')),
        default => date('Y-m-d', strtotime('-6 months')),
    };

    return ['start' => $start, 'end' => $end];
}

function stocks_price_service(PDO $pdo): PriceDataService
{
    static $service;
    if ($service) {
        return $service;
    }
    global $config;
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
    return $service;
}

function stocks_refresh_seconds(int $userId, PDO $pdo): int
{
    $default = stocks_refresh_default();
    if (!stocks_table_exists($pdo, 'user_settings_stocks')) {
        return $default;
    }

    $stmt = $pdo->prepare('SELECT refresh_seconds FROM user_settings_stocks WHERE user_id=?');
    $stmt->execute([$userId]);
    $value = (int)($stmt->fetchColumn() ?: 0);
    if ($value <= 0) {
        return $default;
    }
    return max(5, $value);
}

function stocks_settings(PDO $pdo, int $userId): array
{
    if (!stocks_table_exists($pdo, 'user_settings_stocks')) {
        return ['unrealized_method' => 'AVERAGE', 'realized_method' => 'FIFO'];
    }
    $stmt = $pdo->prepare('SELECT * FROM user_settings_stocks WHERE user_id=?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: ['unrealized_method' => 'AVERAGE', 'realized_method' => 'FIFO'];
}

function stocks_fetch_stock(PDO $pdo, string $symbol): ?array
{
    if (!stocks_table_exists($pdo, 'stocks')) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM stocks WHERE UPPER(symbol)=? LIMIT 1');
    $stmt->execute([$symbol]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function stocks_is_watched(PDO $pdo, int $userId, int $stockId): bool
{
    if (!stocks_table_exists($pdo, 'watchlist')) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM watchlist WHERE user_id=? AND stock_id=?');
    $stmt->execute([$userId, $stockId]);
    return (bool)$stmt->fetchColumn();
}

function stocks_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    $key = strtolower($table);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = ? LIMIT 1');
    $stmt->execute([$table]);
    $cache[$key] = (bool)$stmt->fetchColumn();
    return $cache[$key];
}

function stocks_refresh_default(): int
{
    global $config;
    $value = (int)($config['stocks']['refresh_seconds'] ?? 10);
    return max(5, $value);
}
