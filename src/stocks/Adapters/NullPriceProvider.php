<?php

namespace Stocks\Adapters;

use Stocks\PriceProviderAdapter;

class NullPriceProvider implements PriceProviderAdapter
{
    public function fetchLiveQuotes(array $symbols): array
    {
        $out = [];
        $timestamp = date('c');
        foreach ($symbols as $symbol) {
            $out[strtoupper($symbol)] = [
                'last' => null,
                'prev_close' => null,
                'high' => null,
                'low' => null,
                'volume' => null,
                'currency' => null,
                'provider_ts' => $timestamp,
                'stale' => true,
            ];
        }
        return $out;
    }

    public function fetchDailyHistory(string $symbol, string $from, string $to): array
    {
        return [];
    }

    public function lookupMetadata(string $symbol): array
    {
        return [];
    }
}
