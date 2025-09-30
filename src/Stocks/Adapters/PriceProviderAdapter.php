<?php

declare(strict_types=1);

namespace MyMoneyMap\Stocks\Adapters;

use DateTimeInterface;
use MyMoneyMap\Stocks\DTO\LiveQuote;
use MyMoneyMap\Stocks\DTO\QuoteHistory;

interface PriceProviderAdapter
{
    /**
     * @param array<int, string> $symbols
     * @return array<string, LiveQuote>
     */
    public function fetchLiveQuotes(array $symbols): array;

    /**
     * @return QuoteHistory
     */
    public function fetchDailyHistory(string $symbol, DateTimeInterface $from, DateTimeInterface $to): QuoteHistory;
}
