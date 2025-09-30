<?php

declare(strict_types=1);

namespace MyMoneyMap\Stocks\Adapters;

use DateTimeImmutable;
use DateTimeInterface;
use MyMoneyMap\Stocks\DTO\LiveQuote;
use MyMoneyMap\Stocks\DTO\QuoteHistory;
use MyMoneyMap\Stocks\DTO\QuoteHistoryPoint;

final class FinnhubAdapter implements PriceProviderAdapter
{
    public function __construct(private readonly string $apiKey)
    {
    }

    /**
     * @param array<int, string> $symbols
     * @return array<string, LiveQuote>
     */
    public function fetchLiveQuotes(array $symbols): array
    {
        if ($this->apiKey === '') {
            return [];
        }

        $result = [];
        foreach ($symbols as $symbol) {
            $json = $this->httpGet('https://finnhub.io/api/v1/quote?symbol=' . urlencode($symbol));
            if (!$json) {
                continue;
            }
            $data = json_decode($json, true);
            if (!is_array($data) || !isset($data['c'])) {
                continue;
            }
            $asOf = isset($data['t']) ? (new DateTimeImmutable())->setTimestamp((int) $data['t']) : new DateTimeImmutable();
            $result[$symbol] = new LiveQuote(
                $symbol,
                (float) ($data['c'] ?? 0),
                (float) ($data['pc'] ?? 0),
                (float) ($data['h'] ?? 0),
                (float) ($data['l'] ?? 0),
                (float) ($data['v'] ?? 0),
                $asOf,
                false
            );
        }

        return $result;
    }

    public function fetchDailyHistory(string $symbol, DateTimeInterface $from, DateTimeInterface $to): QuoteHistory
    {
        if ($this->apiKey === '') {
            return new QuoteHistory([], DateTimeImmutable::createFromInterface($from), DateTimeImmutable::createFromInterface($to));
        }

        $fromTs = $from->getTimestamp();
        $toTs = $to->getTimestamp();
        $json = $this->httpGet(sprintf('https://finnhub.io/api/v1/stock/candle?symbol=%s&resolution=D&from=%d&to=%d', urlencode($symbol), $fromTs, $toTs));
        if (!$json) {
            return new QuoteHistory([], DateTimeImmutable::createFromInterface($from), DateTimeImmutable::createFromInterface($to));
        }
        $data = json_decode($json, true);
        if (!is_array($data) || ($data['s'] ?? null) !== 'ok') {
            return new QuoteHistory([], DateTimeImmutable::createFromInterface($from), DateTimeImmutable::createFromInterface($to));
        }

        $points = [];
        $count = count($data['t'] ?? []);
        for ($i = 0; $i < $count; $i++) {
            $date = (new DateTimeImmutable())->setTimestamp((int) $data['t'][$i])->setTime(0, 0);
            $points[] = new QuoteHistoryPoint(
                $date,
                (float) $data['o'][$i],
                (float) $data['h'][$i],
                (float) $data['l'][$i],
                (float) $data['c'][$i],
                (float) $data['v'][$i],
                false
            );
        }

        if ($points === []) {
            return new QuoteHistory([], DateTimeImmutable::createFromInterface($from), DateTimeImmutable::createFromInterface($to));
        }

        $first = $points[0]->date;
        $last = $points[count($points) - 1]->date;
        return new QuoteHistory($points, $first, $last);
    }

    private function httpGet(string $url): ?string
    {
        $queryGlue = str_contains($url, '?') ? '&' : '?';
        $finalUrl = $url . $queryGlue . 'token=' . urlencode($this->apiKey);

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 8,
                'header' => "Accept: application/json\r\n",
            ],
        ]);

        $response = @file_get_contents($finalUrl, false, $context);
        if ($response === false) {
            return null;
        }
        return $response;
    }
}
