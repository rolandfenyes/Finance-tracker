<?php

namespace Stocks;

interface PriceProviderAdapter
{
    /**
     * @param string[] $symbols
     * @return array<string, array{last: ?float, prev_close: ?float, high: ?float, low: ?float, volume: ?float, currency: ?string, provider_ts: ?string}>
     */
    public function fetchLiveQuotes(array $symbols): array;

    /**
     * @param string $symbol
     * @param string $from  ISO date (YYYY-MM-DD)
     * @param string $to    ISO date (YYYY-MM-DD)
     * @return array<int, array{date: string, open: ?float, high: ?float, low: ?float, close: ?float, volume: ?float}>
     */
    public function fetchDailyHistory(string $symbol, string $from, string $to): array;

    /**
     * Metadata lookup. Returns array with keys: name, exchange, currency, sector, industry, beta
     *
     * @return array{name?: string, exchange?: string, currency?: string, sector?: string, industry?: string, beta?: ?float}
     */
    public function lookupMetadata(string $symbol): array;
}
