<?php

declare(strict_types=1);

namespace MyMoneyMap\Stocks\Adapters;

use DateTimeImmutable;
use DateTimeInterface;
use MyMoneyMap\Stocks\DTO\LiveQuote;
use MyMoneyMap\Stocks\DTO\QuoteHistory;

final class NullAdapter implements PriceProviderAdapter
{
    /**
     * @param array<int, string> $symbols
     * @return array<string, LiveQuote>
     */
    public function fetchLiveQuotes(array $symbols): array
    {
        $now = new DateTimeImmutable();
        $quotes = [];
        foreach ($symbols as $symbol) {
            $quotes[$symbol] = new LiveQuote($symbol, 0.0, 0.0, 0.0, 0.0, 0.0, $now, true);
        }
        return $quotes;
    }

    public function fetchDailyHistory(string $symbol, DateTimeInterface $from, DateTimeInterface $to): QuoteHistory
    {
        return new QuoteHistory([], DateTimeImmutable::createFromInterface($from), DateTimeImmutable::createFromInterface($to));
    }
}
