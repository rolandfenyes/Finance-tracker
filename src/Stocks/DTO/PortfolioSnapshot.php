<?php

declare(strict_types=1);

namespace MyMoneyMap\Stocks\DTO;

final class PortfolioSnapshot
{
    /**
     * @param list<Holding> $holdings
     * @param list<WatchlistEntry> $watchlist
     * @param list<AllocationSlice> $allocationsByTicker
     * @param list<AllocationSlice> $allocationsBySector
     * @param list<AllocationSlice> $allocationsByCurrency
     * @param list<Insight> $insights
     */
    public function __construct(
        public readonly string $baseCurrency,
        public readonly float $totalMarketValue,
        public readonly float $totalCost,
        public readonly float $unrealized,
        public readonly float $unrealizedPercent,
        public readonly float $realizedPeriod,
        public readonly float $cashImpact,
        public readonly float $dailyPL,
        public readonly array $holdings,
        public readonly array $watchlist,
        public readonly array $allocationsByTicker,
        public readonly array $allocationsBySector,
        public readonly array $allocationsByCurrency,
        public readonly array $insights,
    ) {
    }
}
