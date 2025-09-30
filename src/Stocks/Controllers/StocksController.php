<?php

declare(strict_types=1);

namespace MyMoneyMap\Stocks\Controllers;

use DateInterval;
use DateTimeImmutable;
use MyMoneyMap\Stocks\DTO\TradeInput;
use MyMoneyMap\Stocks\Repositories\SettingsRepository;
use MyMoneyMap\Stocks\Repositories\StockRepository;
use MyMoneyMap\Stocks\Repositories\TradeRepository;
use MyMoneyMap\Stocks\Services\ChartsService;
use MyMoneyMap\Stocks\Services\PortfolioService;
use MyMoneyMap\Stocks\Services\PriceDataService;
use MyMoneyMap\Stocks\Services\SignalsService;
use MyMoneyMap\Stocks\Services\TradeService;
use PDO;
use RuntimeException;

use function json_response;
use function uid;
use function view;
use function verify_csrf;

final class StocksController
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly PortfolioService $portfolio,
        private readonly TradeService $tradeService,
        private readonly ChartsService $charts,
        private readonly PriceDataService $prices,
        private readonly StockRepository $stocks,
        private readonly SettingsRepository $settings,
        private readonly SignalsService $signals,
        private readonly TradeRepository $tradeRepo,
    ) {
    }

    public function index(): void
    {
        $userId = uid();
        $realizedRange = isset($_GET['realized']) ? (string) $_GET['realized'] : '1M';
        $filters = [
            'search' => $_GET['search'] ?? null,
            'currency' => $_GET['currency'] ?? null,
            'sector' => $_GET['sector'] ?? null,
            'watchlist' => isset($_GET['watchlist']) ? (bool) $_GET['watchlist'] : false,
        ];
        $snapshot = $this->portfolio->buildSnapshot($userId, $realizedRange, $filters);
        $trades = $this->tradeRepo->tradesForUser($userId, 100);
        $chartRange = isset($_GET['chart_range']) ? (string) $_GET['chart_range'] : '3M';
        $portfolioChart = $this->charts->portfolioValueSeries($userId, $chartRange);
        $settings = $this->settings->userSettings($userId);

        view('stocks/index', [
            'snapshot' => $snapshot,
            'trades' => $trades,
            'realizedRange' => $realizedRange,
            'chartRange' => $chartRange,
            'portfolioChart' => $portfolioChart,
            'filters' => $filters,
            'settings' => $settings,
        ]);
    }

    public function show(string $symbol): void
    {
        $userId = uid();
        $stock = $this->stocks->findOneBySymbol($symbol);
        if (!$stock) {
            throw new RuntimeException('Stock not found');
        }

        $snapshot = $this->portfolio->buildSnapshot($userId, '1M', ['search' => $symbol]);
        $holding = null;
        foreach ($snapshot->holdings as $item) {
            if ($item->symbol === $symbol) {
                $holding = $item;
                break;
            }
        }
        $quote = $this->prices->getLiveQuotes([[
            'stock_id' => (int) $stock['id'],
            'symbol' => (string) $stock['symbol'],
            'exchange' => (string) $stock['exchange'],
        ]]);
        $quote = $quote[(int) $stock['id']] ?? null;

        $priceSeries = $this->charts->stockPriceSeries((int) $stock['id'], (string) $stock['symbol'], '6M');
        $positionSeries = $this->charts->positionValueSeries($userId, (int) $stock['id'], (string) $stock['symbol'], (string) $stock['currency'], '6M');
        $realizedYtd = $this->tradeRepo->sumRealizedForStock($userId, (int) $stock['id'], new DateTimeImmutable(date('Y-01-01')), new DateTimeImmutable());
        $insights = $holding ? $this->signals->positionInsights($userId, $holding) : [];
        $settings = $this->settings->userSettings($userId);
        $watchlist = $this->stocks->watchlistStockIds($userId);
        $isWatched = in_array((int) $stock['id'], $watchlist, true);

        view('stocks/show', [
            'stock' => $stock,
            'holding' => $holding,
            'quote' => $quote,
            'priceSeries' => $priceSeries,
            'positionSeries' => $positionSeries,
            'realizedYtd' => $realizedYtd,
            'insights' => $insights,
            'settings' => $settings,
        ]);
    }

    public function recordTrade(string $side): void
    {
        verify_csrf();
        $side = strtoupper($side);
        if (!in_array($side, ['BUY', 'SELL'], true)) {
            throw new RuntimeException('Invalid trade side.');
        }
        $executedInput = $_POST['executed_at'] ?? ($_POST['trade_on'] ?? date('Y-m-d'));
        $executedAt = $this->parseDateTime($executedInput);
        $input = new TradeInput(
            uid(),
            strtoupper(trim((string) ($_POST['symbol'] ?? ''))),
            strtoupper(trim((string) ($_POST['exchange'] ?? 'NYSE'))),
            trim((string) ($_POST['name'] ?? ($_POST['symbol'] ?? 'Unknown'))),
            strtoupper(trim((string) ($_POST['currency'] ?? 'USD'))),
            $side,
            (float) ($_POST['quantity'] ?? 0),
            (float) ($_POST['price'] ?? 0),
            isset($_POST['fee']) ? (float) $_POST['fee'] : 0.0,
            $executedAt,
            isset($_POST['note']) ? (string) $_POST['note'] : null
        );

        $result = $this->tradeService->recordTrade($input);
        if ($result->success) {
            $_SESSION['flash_success'] = 'Trade recorded.';
        } else {
            $_SESSION['flash'] = $result->message ?? 'Trade failed.';
        }
    }

    public function deleteTrade(): void
    {
        verify_csrf();
        $tradeId = (int) ($_POST['id'] ?? 0);
        if ($tradeId <= 0) {
            $_SESSION['flash'] = 'Invalid trade identifier.';
            return;
        }
        $this->tradeService->deleteTrade(uid(), $tradeId);
        $_SESSION['flash_success'] = 'Trade deleted.';
    }

    public function toggleWatch(int $stockId): void
    {
        verify_csrf();
        $added = $this->stocks->toggleWatchlist(uid(), $stockId);
        $_SESSION['flash_success'] = $added ? 'Added to watchlist.' : 'Removed from watchlist.';
    }

    public function liveQuotes(): void
    {
        $symbolsParam = isset($_GET['symbols']) ? explode(',', (string) $_GET['symbols']) : [];
        $requests = [];
        foreach ($symbolsParam as $symbol) {
            $symbol = strtoupper(trim($symbol));
            if ($symbol === '') {
                continue;
            }
            $stock = $this->stocks->findOneBySymbol($symbol);
            if ($stock) {
                $requests[] = [
                    'stock_id' => (int) $stock['id'],
                    'symbol' => (string) $stock['symbol'],
                    'exchange' => (string) $stock['exchange'],
                ];
            }
        }
        $quotes = $this->prices->getLiveQuotes($requests);
        $payload = [];
        foreach ($quotes as $stockId => $quote) {
            $payload[] = [
                'stock_id' => $stockId,
                'symbol' => $quote->symbol,
                'last' => $quote->last,
                'prev_close' => $quote->previousClose,
                'day_high' => $quote->dayHigh,
                'day_low' => $quote->dayLow,
                'volume' => $quote->volume,
                'as_of' => $quote->asOf->format(DateTimeImmutable::ATOM),
                'stale' => $quote->stale,
            ];
        }
        json_response(['quotes' => $payload]);
    }

    public function history(string $symbol): void
    {
        $range = isset($_GET['range']) ? (string) $_GET['range'] : '6M';
        [$from, $to] = $this->rangeToDates($range);
        $stock = $this->stocks->findOneBySymbol($symbol);
        if (!$stock) {
            json_response(['candles' => []]);
            return;
        }
        $history = $this->prices->getDailyHistory((int) $stock['id'], $symbol, $from, $to);
        $series = [];
        foreach ($history as $point) {
            $series[] = [
                'date' => $point->date->format('Y-m-d'),
                'open' => $point->open,
                'high' => $point->high,
                'low' => $point->low,
                'close' => $point->close,
                'volume' => $point->volume,
            ];
        }
        json_response(['candles' => $series]);
    }

    private function parseDateTime(string $input): DateTimeImmutable
    {
        $input = trim($input);
        if ($input === '') {
            return new DateTimeImmutable();
        }
        if (str_contains($input, 'T')) {
            $dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $input);
            if ($dt instanceof DateTimeImmutable) {
                return $dt;
            }
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $input);
        if ($dt instanceof DateTimeImmutable) {
            return $dt;
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $input);
        if ($dt instanceof DateTimeImmutable) {
            return $dt;
        }
        return new DateTimeImmutable();
    }

    /**
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    private function rangeToDates(string $range): array
    {
        $range = strtoupper($range);
        $to = new DateTimeImmutable();
        return match ($range) {
            '1W' => [$to->sub(new DateInterval('P7D')), $to],
            '1M' => [$to->sub(new DateInterval('P1M')), $to],
            '3M' => [$to->sub(new DateInterval('P3M')), $to],
            '6M' => [$to->sub(new DateInterval('P6M')), $to],
            '1Y' => [$to->sub(new DateInterval('P1Y')), $to],
            '5Y' => [$to->sub(new DateInterval('P5Y')), $to],
            default => [$to->sub(new DateInterval('P6M')), $to],
        };
    }
}
