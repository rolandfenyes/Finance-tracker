<?php

declare(strict_types=1);

namespace MyMoneyMap\Stocks\Services;

use DateInterval;
use DateTimeImmutable;
use MyMoneyMap\Stocks\DTO\AllocationSlice;
use MyMoneyMap\Stocks\DTO\Holding;
use MyMoneyMap\Stocks\DTO\PortfolioSnapshot;
use MyMoneyMap\Stocks\DTO\WatchlistEntry;
use MyMoneyMap\Stocks\Repositories\SettingsRepository;
use MyMoneyMap\Stocks\Repositories\StockRepository;
use MyMoneyMap\Stocks\Repositories\TradeRepository;
use PDO;

use function fx_convert;
use function fx_user_main;

final class PortfolioService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly TradeRepository $trades,
        private readonly StockRepository $stocks,
        private readonly SettingsRepository $settings,
        private readonly PriceDataService $prices,
        private readonly SignalsService $signals,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function buildSnapshot(int $userId, string $realizedRange = '1M', array $filters = []): PortfolioSnapshot
    {
        $baseCurrency = fx_user_main($this->pdo, $userId);
        [$from, $to] = $this->rangeToDates($realizedRange);

        $rawHoldings = $this->trades->holdingsRaw($userId);
        $watchIds = $this->stocks->watchlistStockIds($userId);
        $watchStocks = $this->stocks->findByIds($watchIds);

        $quoteRequests = [];
        foreach ($rawHoldings as $row) {
            $quoteRequests[$row['stock_id']] = [
                'stock_id' => (int) $row['stock_id'],
                'symbol' => $row['symbol'],
                'exchange' => $row['exchange'],
            ];
        }
        foreach ($watchStocks as $row) {
            $quoteRequests[$row['id']] = [
                'stock_id' => (int) $row['id'],
                'symbol' => $row['symbol'],
                'exchange' => $row['exchange'],
            ];
        }

        $quotes = $quoteRequests !== [] ? $this->prices->getLiveQuotes(array_values($quoteRequests)) : [];

        $filtered = $this->filterHoldings($rawHoldings, $filters, $watchIds);

        $holdingTemp = [];
        $totalMarketValueBase = 0.0;
        $totalCostBase = 0.0;
        $dailyPLBase = 0.0;
        $unrealizedBaseTotal = 0.0;
        $allocTicker = [];
        $allocSector = [];
        $allocCurrency = [];

        foreach ($filtered as $row) {
            $qty = (float) $row['qty'];
            if ($qty <= 1e-6) {
                continue;
            }
            $stockId = (int) $row['stock_id'];
            $symbol = (string) $row['symbol'];
            $exchange = (string) $row['exchange'];
            $name = (string) $row['name'];
            $currency = strtoupper((string) $row['currency']);
            $avgCost = (float) $row['avg_cost_ccy'];
            $sector = $row['sector'] ?: null;
            $industry = $row['industry'] ?: null;
            $quote = $quotes[$stockId] ?? null;
            $lastPrice = $quote?->last ?? $avgCost;
            $date = $quote ? $quote->asOf->format('Y-m-d') : (new DateTimeImmutable())->format('Y-m-d');

            $avgCostBase = fx_convert($this->pdo, $avgCost, $currency, $baseCurrency, $date);
            $lastPriceBase = fx_convert($this->pdo, $lastPrice, $currency, $baseCurrency, $date);
            $marketValue = $qty * $lastPrice;
            $marketValueBase = $qty * $lastPriceBase;
            $totalCostBasePosition = fx_convert($this->pdo, $avgCost * $qty, $currency, $baseCurrency, $date);
            $unrealizedBase = fx_convert($this->pdo, ($lastPrice - $avgCost) * $qty, $currency, $baseCurrency, $date);
            $unrealizedPercent = $avgCost > 0 ? (($lastPrice - $avgCost) / $avgCost) * 100.0 : 0.0;
            $dayChange = $quote ? ($quote->last - $quote->previousClose) * $qty : 0.0;
            $dayChangeBase = $quote ? fx_convert($this->pdo, $dayChange, $currency, $baseCurrency, $date) : 0.0;

            $holdingTemp[] = [
                'stock_id' => $stockId,
                'symbol' => $symbol,
                'exchange' => $exchange,
                'name' => $name,
                'currency' => $currency,
                'qty' => $qty,
                'avg_cost' => $avgCost,
                'avg_cost_base' => $avgCostBase,
                'last_price' => $lastPrice,
                'last_price_base' => $lastPriceBase,
                'market_value' => $marketValue,
                'market_value_base' => $marketValueBase,
                'unrealized' => $unrealizedBase,
                'unrealized_percent' => $unrealizedPercent,
                'day_change' => $dayChange,
                'day_change_base' => $dayChangeBase,
                'sector' => $sector,
                'industry' => $industry,
                'quote' => $quote,
            ];

            $totalMarketValueBase += $marketValueBase;
            $totalCostBase += $totalCostBasePosition;
            $unrealizedBaseTotal += $unrealizedBase;
            $dailyPLBase += $dayChangeBase;

            $allocTicker[$symbol] = ($allocTicker[$symbol] ?? 0.0) + $marketValueBase;
            $sectorKey = $sector ?: 'Unclassified';
            $allocSector[$sectorKey] = ($allocSector[$sectorKey] ?? 0.0) + $marketValueBase;
            $allocCurrency[$currency] = ($allocCurrency[$currency] ?? 0.0) + $marketValueBase;
        }

        $holdings = [];
        foreach ($holdingTemp as $row) {
            $weight = $totalMarketValueBase > 0 ? ($row['market_value_base'] / $totalMarketValueBase) : 0.0;
            $concentration = $weight >= 0.15;
            $holdings[] = new Holding(
                $row['stock_id'],
                $row['symbol'],
                $row['exchange'],
                $row['name'],
                $row['currency'],
                $row['qty'],
                $row['avg_cost'],
                $row['avg_cost_base'],
                $baseCurrency,
                $row['last_price'],
                $row['last_price_base'],
                $row['market_value'],
                $row['market_value_base'],
                $row['unrealized'],
                $row['unrealized_percent'],
                $row['day_change'],
                $row['day_change_base'],
                $weight * 100.0,
                $row['sector'],
                $row['industry'],
                null,
                $concentration,
            );
        }

        $allocationsByTicker = $this->buildAllocationSlices($allocTicker, $totalMarketValueBase);
        $allocationsBySector = $this->buildAllocationSlices($allocSector, $totalMarketValueBase);
        $allocationsByCurrency = $this->buildAllocationSlices($allocCurrency, $totalMarketValueBase);

        $watchEntries = [];
        foreach ($watchStocks as $row) {
            $stockId = (int) $row['id'];
            $quote = $quotes[$stockId] ?? null;
            $watchEntries[] = new WatchlistEntry(
                $stockId,
                (string) $row['symbol'],
                (string) $row['name'],
                (string) $row['exchange'],
                strtoupper((string) $row['currency']),
                $quote
            );
        }

        $cashImpact = $this->calculateCashImpact($userId, $baseCurrency);
        $realized = $this->trades->sumRealized($userId, $from, $to);
        $unrealizedPercent = $totalCostBase > 0 ? ($unrealizedBaseTotal / $totalCostBase) * 100.0 : 0.0;

        $snapshot = new PortfolioSnapshot(
            $baseCurrency,
            $totalMarketValueBase,
            $totalCostBase,
            $unrealizedBaseTotal,
            $unrealizedPercent,
            $realized,
            $cashImpact,
            $dailyPLBase,
            $holdings,
            $watchEntries,
            $allocationsByTicker,
            $allocationsBySector,
            $allocationsByCurrency,
            []
        );

        $insights = $this->signals->portfolioInsights($userId, $snapshot);

        return new PortfolioSnapshot(
            $snapshot->baseCurrency,
            $snapshot->totalMarketValue,
            $snapshot->totalCost,
            $snapshot->unrealized,
            $snapshot->unrealizedPercent,
            $snapshot->realizedPeriod,
            $snapshot->cashImpact,
            $snapshot->dailyPL,
            $snapshot->holdings,
            $snapshot->watchlist,
            $snapshot->allocationsByTicker,
            $snapshot->allocationsBySector,
            $snapshot->allocationsByCurrency,
            $insights
        );
    }

    /**
     * @return list<AllocationSlice>
     */
    private function buildAllocationSlices(array $map, float $total): array
    {
        $slices = [];
        foreach ($map as $label => $value) {
            $weight = $total > 0 ? ($value / $total) * 100.0 : 0.0;
            $slices[] = new AllocationSlice((string) $label, (float) $value, $weight);
        }
        usort($slices, static fn (AllocationSlice $a, AllocationSlice $b): int => $b->weight <=> $a->weight);
        return $slices;
    }

    /**
     * @param array<int, array<string, mixed>> $holdings
     * @return array<int, array<string, mixed>>
     */
    private function filterHoldings(array $holdings, array $filters, array $watchIds): array
    {
        if ($filters === []) {
            return $holdings;
        }

        $search = isset($filters['search']) ? strtolower((string) $filters['search']) : null;
        $currency = isset($filters['currency']) ? strtoupper((string) $filters['currency']) : null;
        $sector = isset($filters['sector']) ? strtolower((string) $filters['sector']) : null;
        $watchOnly = !empty($filters['watchlist']);

        return array_values(array_filter($holdings, static function (array $row) use ($search, $currency, $sector, $watchOnly, $watchIds): bool {
            if ($search) {
                $hay = strtolower((string) $row['symbol'] . ' ' . (string) $row['name']);
                if (!str_contains($hay, $search)) {
                    return false;
                }
            }
            if ($currency && strtoupper((string) $row['currency']) !== $currency) {
                return false;
            }
            if ($sector) {
                $rowSector = strtolower((string) ($row['sector'] ?? ''));
                if ($rowSector === '' || !str_contains($rowSector, $sector)) {
                    return false;
                }
            }
            if ($watchOnly && !in_array((int) $row['stock_id'], $watchIds, true)) {
                return false;
            }
            return true;
        }));
    }

    /**
     * @return array{0: ?DateTimeImmutable, 1: ?DateTimeImmutable}
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
            'YTD' => [new DateTimeImmutable($to->format('Y-01-01')), $to],
            default => [$to->sub(new DateInterval('P1M')), $to],
        };
    }

    private function calculateCashImpact(int $userId, string $baseCurrency): float
    {
        $trades = $this->trades->allTrades($userId);
        $impact = 0.0;
        foreach ($trades as $trade) {
            $side = strtoupper((string) $trade['side']);
            $qty = (float) $trade['qty'];
            $price = (float) $trade['price'];
            $fee = (float) $trade['fee'];
            $currency = strtoupper((string) $trade['currency']);
            $date = (new DateTimeImmutable((string) $trade['executed_at']))->format('Y-m-d');

            if ($side === 'BUY') {
                $flow = -($qty * $price + $fee);
            } elseif ($side === 'SELL') {
                $flow = ($qty * $price) - $fee;
            } else {
                continue;
            }
            $impact += fx_convert($this->pdo, $flow, $currency, $baseCurrency, $date);
        }
        return $impact;
    }
}
