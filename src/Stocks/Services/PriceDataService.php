<?php

declare(strict_types=1);

namespace MyMoneyMap\Stocks\Services;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use MyMoneyMap\Stocks\Adapters\PriceProviderAdapter;
use MyMoneyMap\Stocks\DTO\LiveQuote;
use MyMoneyMap\Stocks\DTO\QuoteHistory;
use MyMoneyMap\Stocks\DTO\QuoteHistoryPoint;
use MyMoneyMap\Stocks\Repositories\PriceRepository;

final class PriceDataService
{
    /** @var array<string, array{quote: LiveQuote, expires: DateTimeImmutable}> */
    private array $memoryCache = [];

    public function __construct(
        private readonly PriceRepository $prices,
        private readonly PriceProviderAdapter $adapter,
        private readonly int $cacheTtlSeconds = 8,
    ) {
    }

    /**
     * @param list<array{stock_id:int,symbol:string,exchange:string}> $requests
     * @return array<int, LiveQuote>
     */
    public function getLiveQuotes(array $requests): array
    {
        $now = new DateTimeImmutable();
        $result = [];
        $needFetch = [];

        foreach ($requests as $request) {
            $symbol = strtoupper($request['symbol']);
            $exchange = strtoupper($request['exchange']);
            $key = $this->key($symbol, $exchange);

            if (isset($this->memoryCache[$key]) && $this->memoryCache[$key]['expires'] > $now) {
                $result[$request['stock_id']] = $this->memoryCache[$key]['quote'];
                continue;
            }

            $db = $this->prices->lastPrice($request['stock_id']);
            if ($db) {
                $quote = $this->fromRow($symbol, $db);
                $result[$request['stock_id']] = $quote;
                $this->memoryCache[$key] = [
                    'quote' => $quote,
                    'expires' => $now->add(new DateInterval('PT' . $this->cacheTtlSeconds . 'S')),
                ];
            }

            $needFetch[$key] = $symbol;
        }

        if ($needFetch !== []) {
            $fetched = $this->adapter->fetchLiveQuotes(array_values(array_unique($needFetch)));
            foreach ($requests as $request) {
                $symbol = strtoupper($request['symbol']);
                $exchange = strtoupper($request['exchange']);
                $key = $this->key($symbol, $exchange);
                $quote = $fetched[$symbol] ?? null;
                if (!$quote) {
                    continue;
                }

                $result[$request['stock_id']] = $quote;
                $this->memoryCache[$key] = [
                    'quote' => $quote,
                    'expires' => $now->add(new DateInterval('PT' . $this->cacheTtlSeconds . 'S')),
                ];

                $this->prices->upsertLastPrice($request['stock_id'], [
                    'last' => $quote->last,
                    'prev_close' => $quote->previousClose,
                    'day_high' => $quote->dayHigh,
                    'day_low' => $quote->dayLow,
                    'volume' => $quote->volume,
                    'provider_ts' => $quote->asOf->format('c'),
                ]);
            }
        }

        return $result;
    }

    public function getDailyHistory(int $stockId, string $symbol, DateTimeInterface $from, DateTimeInterface $to): QuoteHistory
    {
        $rows = $this->prices->dailySeries($stockId, DateTimeImmutable::createFromInterface($from), DateTimeImmutable::createFromInterface($to));
        $points = [];
        foreach ($rows as $row) {
            $points[] = new QuoteHistoryPoint(
                new DateTimeImmutable($row['date']),
                (float) $row['open'],
                (float) $row['high'],
                (float) $row['low'],
                (float) $row['close'],
                (float) $row['volume'],
                false
            );
        }

        $history = new QuoteHistory(
            $points,
            DateTimeImmutable::createFromInterface($from),
            DateTimeImmutable::createFromInterface($to)
        );

        if ($history->isEmpty()) {
            $fetched = $this->adapter->fetchDailyHistory($symbol, $from, $to);
            foreach ($fetched as $point) {
                $this->prices->insertDailyPrice($stockId, $point->date, [
                    'open' => $point->open,
                    'high' => $point->high,
                    'low' => $point->low,
                    'close' => $point->close,
                    'volume' => $point->volume,
                ]);
            }
            return $fetched;
        }

        return $history->fillGaps();
    }

    private function key(string $symbol, string $exchange): string
    {
        return $symbol . '@' . $exchange;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function fromRow(string $symbol, array $row): LiveQuote
    {
        $updated = isset($row['provider_ts']) ? new DateTimeImmutable((string) $row['provider_ts']) : new DateTimeImmutable('-1 day');
        $stale = $updated < (new DateTimeImmutable('-15 minutes'));

        return new LiveQuote(
            $symbol,
            (float) ($row['last'] ?? 0),
            (float) ($row['prev_close'] ?? 0),
            (float) ($row['day_high'] ?? 0),
            (float) ($row['day_low'] ?? 0),
            (float) ($row['volume'] ?? 0),
            $updated,
            $stale,
        );
    }
}
