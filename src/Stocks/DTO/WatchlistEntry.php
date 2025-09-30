<?php

declare(strict_types=1);

namespace MyMoneyMap\Stocks\DTO;

final class WatchlistEntry
{
    public function __construct(
        public readonly int $stockId,
        public readonly string $symbol,
        public readonly string $name,
        public readonly string $exchange,
        public readonly string $currency,
        public readonly ?LiveQuote $quote,
    ) {
    }
}
