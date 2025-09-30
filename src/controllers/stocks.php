<?php

declare(strict_types=1);

use MyMoneyMap\Stocks\Adapters\FinnhubAdapter;
use MyMoneyMap\Stocks\Adapters\NullAdapter;
use MyMoneyMap\Stocks\Controllers\StocksController;
use MyMoneyMap\Stocks\Repositories\PriceRepository;
use MyMoneyMap\Stocks\Repositories\SettingsRepository;
use MyMoneyMap\Stocks\Repositories\StockRepository;
use MyMoneyMap\Stocks\Repositories\TradeRepository;
use MyMoneyMap\Stocks\Services\ChartsService;
use MyMoneyMap\Stocks\Services\PortfolioService;
use MyMoneyMap\Stocks\Services\PriceDataService;
use MyMoneyMap\Stocks\Services\SignalsService;
use MyMoneyMap\Stocks\Services\TradeService;

function stocks_controller(PDO $pdo): StocksController
{
    static $controller;
    if ($controller instanceof StocksController) {
        return $controller;
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

    $priceService = new PriceDataService($priceRepo, $adapter);
    $signals = new SignalsService($pdo, $priceService, $settingsRepo);
    $portfolio = new PortfolioService($pdo, $tradeRepo, $stockRepo, $settingsRepo, $priceService, $signals);
    $tradeService = new TradeService($pdo, $stockRepo, $tradeRepo, $settingsRepo);
    $charts = new ChartsService($pdo, $tradeRepo, $priceService);

    $controller = new StocksController(
        $pdo,
        $portfolio,
        $tradeService,
        $charts,
        $priceService,
        $stockRepo,
        $settingsRepo,
        $signals,
        $tradeRepo
    );

    return $controller;
}

function stocks_index(PDO $pdo): void
{
    stocks_controller($pdo)->index();
}

function stocks_show(PDO $pdo, string $symbol): void
{
    stocks_controller($pdo)->show($symbol);
}

function trade_buy(PDO $pdo): void
{
    stocks_controller($pdo)->recordTrade('buy');
}

function trade_sell(PDO $pdo): void
{
    stocks_controller($pdo)->recordTrade('sell');
}

function trade_delete(PDO $pdo): void
{
    stocks_controller($pdo)->deleteTrade();
}

function stocks_watch(PDO $pdo, int $stockId): void
{
    stocks_controller($pdo)->toggleWatch($stockId);
}

function stocks_live(PDO $pdo): void
{
    stocks_controller($pdo)->liveQuotes();
}

function stocks_history(PDO $pdo, string $symbol): void
{
    stocks_controller($pdo)->history($symbol);
}
