<?php

declare(strict_types=1);

namespace MyMoneyMap\Stocks\DTO;

final class Holding
{
    public function __construct(
        public readonly int $stockId,
        public readonly string $symbol,
        public readonly string $exchange,
        public readonly string $name,
        public readonly string $currency,
        public readonly float $quantity,
        public readonly float $averageCost,
        public readonly float $averageCostBase,
        public readonly string $baseCurrency,
        public readonly float $lastPrice,
        public readonly float $lastPriceBase,
        public readonly float $marketValue,
        public readonly float $marketValueBase,
        public readonly float $unrealized,
        public readonly float $unrealizedPercent,
        public readonly float $dayChange,
        public readonly float $dayChangeBase,
        public readonly float $weight,
        public readonly ?string $sector = null,
        public readonly ?string $industry = null,
        public readonly ?string $note = null,
        public readonly bool $concentrationWarning = false,
    ) {
    }
}
