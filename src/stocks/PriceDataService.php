<?php

namespace Stocks;

use PDO;
use Stocks\Adapters\NullPriceProvider;

class PriceDataService
{
    private PDO $pdo;
    private PriceProviderAdapter $adapter;
    private int $ttlSeconds;
    private int $maxProviderBatch;
    /** @var array<string, array> */
    private array $memoryCache = [];
    /** @var array<string,int|null> */
    private array $stockIdCache = [];
    /** @var array<string,array<int,array{date:string,open:?float,high:?float,low:?float,close:?float,volume:?float}>> */
    private array $historyCache = [];
    /** @var array<string,array{stock_id:int,currency:?string}> */
    private array $deferredRefreshMap = [];
    /** @var array<string,bool> */
    private array $historyStaleFlags = [];
    /**
     * Deferred history refresh queue keyed by cache key so the same range is only enqueued once per request.
     *
     * @var array<string,array{cache_key:string,symbol:string,stock_id:int,from:string,to:string}>
     */
    private array $deferredHistoryQueue = [];
    private bool $deferredRegistered = false;
    private bool $responseFinished = false;

    public function __construct(PDO $pdo, ?PriceProviderAdapter $adapter = null, int $ttlSeconds = 10, ?int $maxProviderBatch = null)
    {
        $this->pdo = $pdo;
        $this->adapter = $adapter ?? new NullPriceProvider();
        $this->ttlSeconds = max(5, $ttlSeconds);
        $this->maxProviderBatch = $maxProviderBatch !== null ? max(1, $maxProviderBatch) : 12;
    }

    /**
     * @param string[] $symbols
     * @return array<int, array{stock_id:int,symbol:string,last:?float,prev_close:?float,day_high:?float,day_low:?float,volume:?float,currency:?string,provider_ts:?string,stale:bool}>
     */
    public function getLiveQuotes(array $symbols, bool $forceRefresh = false): array
    {
        if (empty($symbols)) {
            return [];
        }
        $symbols = array_values(array_unique(array_map(static fn($s) => strtoupper(trim((string)$s)), $symbols)));
        $placeholders = implode(',', array_fill(0, count($symbols), '?'));
        $stmt = $this->pdo->prepare("SELECT id, UPPER(symbol) AS symbol, currency FROM stocks WHERE UPPER(symbol) IN ($placeholders)");
        $stmt->execute($symbols);
        $known = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$known) {
            return [];
        }
        $now = time();
        $result = [];
        $indexBySymbol = [];
        $symbolCurrency = [];
        $toProcess = [];

        foreach ($known as $row) {
            $symbol = $row['symbol'];
            $indexBySymbol[$symbol] = (int)$row['id'];
            $symbolCurrency[$symbol] = $row['currency'] ?? null;

            if (!$forceRefresh && isset($this->memoryCache[$symbol])) {
                $cached = $this->memoryCache[$symbol];
                $cachedData = $cached['data'] ?? null;
                $isStale = is_array($cachedData) ? (bool)($cachedData['stale'] ?? false) : false;
                if (!$isStale && ($cached['ts'] ?? 0) + $this->ttlSeconds > $now) {
                    $result[$symbol] = $cachedData;
                    continue;
                }
            }

            $toProcess[] = $symbol;
        }

        if (!empty($toProcess)) {
            $placeholders = implode(',', array_fill(0, count($toProcess), '?'));
            $ids = array_map(static fn($symbol) => $indexBySymbol[$symbol], $toProcess);
            $lastRows = $this->pdo->prepare("SELECT stock_id,last,prev_close,day_high,day_low,volume,provider_ts,stale,updated_at FROM stock_prices_last WHERE stock_id IN ($placeholders)");
            $lastRows->execute($ids);
            $existing = [];
            foreach ($lastRows as $row) {
                $existing[(int)$row['stock_id']] = $row;
            }

            $staleSymbols = [];
            $missingSymbols = [];

            foreach ($toProcess as $symbol) {
                $stockId = $indexBySymbol[$symbol];
                $row = $existing[$stockId] ?? null;
                $isFresh = false;

                if ($row) {
                    $updatedAt = strtotime((string)($row['updated_at'] ?? '')) ?: 0;
                    $isFresh = $updatedAt + $this->ttlSeconds > $now;
                    if ($forceRefresh) {
                        $isFresh = false;
                    }

                    $row['stale'] = !$isFresh;
                    $formatted = $this->formatRow($symbol, $stockId, $row, $symbolCurrency[$symbol] ?? null);
                    $result[$symbol] = $formatted;
                    $this->memoryCache[$symbol] = ['ts' => $now, 'data' => $formatted];

                    if (!$isFresh) {
                        $staleSymbols[] = $symbol;
                    }
                } else {
                    $missingSymbols[] = $symbol;
                    $result[$symbol] = $this->emptyQuote($symbol, $stockId, $symbolCurrency[$symbol] ?? null);
                    $this->memoryCache[$symbol] = ['ts' => $now, 'data' => $result[$symbol]];
                }
            }

            $immediateFetch = $forceRefresh ? array_unique(array_merge($staleSymbols, $missingSymbols)) : $missingSymbols;
            if (!empty($immediateFetch)) {
                $fresh = $this->fetchAndApply($immediateFetch, $indexBySymbol, $symbolCurrency, $now);
                foreach ($immediateFetch as $symbol) {
                    if (isset($fresh[$symbol])) {
                        $result[$symbol] = $fresh[$symbol];
                    }
                }
            }

            $deferred = array_diff($staleSymbols, $immediateFetch);
            if (!$forceRefresh && !empty($deferred)) {
                $this->queueDeferredRefresh($deferred, $indexBySymbol, $symbolCurrency);
            }
        }

        return array_values($result);
    }

    /**
     * Force refresh quotes for the provided symbols and persist them to caches.
     *
     * @param string[] $symbols
     * @return array<int, array{stock_id:int,symbol:string,last:?float,prev_close:?float,day_high:?float,day_low:?float,volume:?float,currency:?string,provider_ts:?string,stale:bool}>
     */
    public function refreshSymbols(array $symbols): array
    {
        if (empty($symbols)) {
            return [];
        }

        return $this->getLiveQuotes($symbols, true);
    }

    /**
     * @return array<int, array{date:string,open:?float,high:?float,low:?float,close:?float,volume:?float}>
     */
    public function getDailyHistory(string $symbol, string $from, string $to): array
    {
        $symbol = strtoupper(trim($symbol));
        if ($symbol === '') {
            return [];
        }
        $cacheKey = $this->historyCacheKey($symbol, $from, $to);
        if (array_key_exists($cacheKey, $this->historyCache)) {
            return $this->historyCache[$cacheKey];
        }
        $stockId = $this->lookupStockId($symbol);
        if (!$stockId) {
            $this->historyCache[$cacheKey] = [];
            $this->historyStaleFlags[$cacheKey] = false;
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT date, open, high, low, close, volume FROM price_daily WHERE stock_id=? AND date BETWEEN ?::date AND ?::date ORDER BY date ASC');
        $stmt->execute([$stockId, $from, $to]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $needsRefresh = !$this->coversRange($rows, $from, $to);
        if ($needsRefresh) {
            $this->queueDeferredHistoryFetch($cacheKey, $symbol, $stockId, $from, $to);
        }

        $mapped = array_map(static function ($row) {
            return [
                'date' => $row['date'],
                'open' => $row['open'] !== null ? (float)$row['open'] : null,
                'high' => $row['high'] !== null ? (float)$row['high'] : null,
                'low' => $row['low'] !== null ? (float)$row['low'] : null,
                'close' => $row['close'] !== null ? (float)$row['close'] : null,
                'volume' => $row['volume'] !== null ? (float)$row['volume'] : null,
            ];
        }, $rows ?: []);

        $this->historyCache[$cacheKey] = $mapped;
        $this->historyStaleFlags[$cacheKey] = $needsRefresh;

        return $mapped;
    }

    public function isHistoryRangeStale(string $symbol, string $from, string $to): bool
    {
        $cacheKey = $this->historyCacheKey(strtoupper(trim($symbol)), $from, $to);

        return (bool)($this->historyStaleFlags[$cacheKey] ?? false);
    }

    public function refreshMetadata(string $symbol): array
    {
        $symbol = strtoupper(trim($symbol));
        if ($symbol === '') {
            return [];
        }
        $stockId = $this->lookupStockId($symbol);
        if (!$stockId) {
            return [];
        }
        $meta = $this->adapter->lookupMetadata($symbol);
        if (!$meta) {
            return [];
        }
        $columns = [];
        $params = [];
        foreach (['name', 'exchange', 'currency', 'sector', 'industry', 'beta'] as $field) {
            if (array_key_exists($field, $meta)) {
                $columns[] = $field . ' = ?';
                $params[] = $meta[$field];
            }
        }
        if ($columns) {
            $params[] = $stockId;
            $sql = 'UPDATE stocks SET ' . implode(', ', $columns) . ', updated_at = NOW() WHERE id = ?';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        }
        return $meta;
    }

    private function lookupStockId(string $symbol): ?int
    {
        $normalized = strtoupper(trim($symbol));
        if ($normalized === '') {
            return null;
        }
        if (array_key_exists($normalized, $this->stockIdCache)) {
            $cached = $this->stockIdCache[$normalized];
            return $cached !== null ? (int)$cached : null;
        }
        $stmt = $this->pdo->prepare('SELECT id FROM stocks WHERE UPPER(symbol)=? LIMIT 1');
        $stmt->execute([$normalized]);
        $id = $stmt->fetchColumn();
        $value = $id ? (int)$id : null;
        $this->stockIdCache[$normalized] = $value;

        return $value;
    }

    /**
     * @param array{last:?float,prev_close:?float,high:?float,low:?float,volume:?float,provider_ts:?string,currency?:?string} $quote
     */
    private function upsertLastQuote(int $stockId, array $quote): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO stock_prices_last(stock_id,last,prev_close,day_high,day_low,volume,provider_ts,stale,updated_at)
            VALUES(?,?,?,?,?,?,?,FALSE,NOW())
            ON CONFLICT (stock_id) DO UPDATE SET
                last = EXCLUDED.last,
                prev_close = EXCLUDED.prev_close,
                day_high = EXCLUDED.day_high,
                day_low = EXCLUDED.day_low,
                volume = EXCLUDED.volume,
                provider_ts = EXCLUDED.provider_ts,
                stale = FALSE,
                updated_at = NOW()');
        $stmt->execute([
            $stockId,
            $quote['last'] ?? null,
            $quote['prev_close'] ?? null,
            $quote['high'] ?? null,
            $quote['low'] ?? null,
            $quote['volume'] ?? null,
            $quote['provider_ts'] ?? null,
        ]);
    }

    /**
     * @param array $row
     * @return array{stock_id:int,symbol:string,last:?float,prev_close:?float,day_high:?float,day_low:?float,volume:?float,currency:?string,provider_ts:?string,stale:bool}
     */
    private function formatRow(string $symbol, int $stockId, array $row, ?string $fallbackCurrency = null): array
    {
        return [
            'stock_id' => $stockId,
            'symbol' => $symbol,
            'last' => isset($row['last']) ? (float)$row['last'] : null,
            'prev_close' => isset($row['prev_close']) ? (float)$row['prev_close'] : null,
            'day_high' => isset($row['day_high']) ? (float)$row['day_high'] : null,
            'day_low' => isset($row['day_low']) ? (float)$row['day_low'] : null,
            'volume' => isset($row['volume']) ? (float)$row['volume'] : null,
            'currency' => $row['currency'] ?? $fallbackCurrency,
            'provider_ts' => $row['provider_ts'] ?? null,
            'stale' => (bool)($row['stale'] ?? false),
        ];
    }

    /**
     * @param array<int, array{date:string}> $rows
     */
    private function coversRange(array $rows, string $from, string $to): bool
    {
        if (!$rows) {
            return false;
        }
        $first = $rows[0]['date'] ?? null;
        $last = $rows[count($rows) - 1]['date'] ?? null;
        if (!$first || !$last) {
            return false;
        }
        return ($first <= $from) && ($last >= $to);
    }

    /**
     * @param array{date:string,open:?float,high:?float,low:?float,close:?float,volume:?float} $candle
     */
    private function storeDailyCandle(int $stockId, array $candle): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO price_daily(stock_id,date,open,high,low,close,volume,provider,created_at)
            VALUES(?,?,?,?,?,?,?,\'provider\',NOW())
            ON CONFLICT (stock_id,date) DO UPDATE SET open=EXCLUDED.open, high=EXCLUDED.high, low=EXCLUDED.low, close=EXCLUDED.close, volume=EXCLUDED.volume');
        $stmt->execute([
            $stockId,
            $candle['date'],
            $candle['open'] ?? null,
            $candle['high'] ?? null,
            $candle['low'] ?? null,
            $candle['close'] ?? null,
            $candle['volume'] ?? null,
        ]);
    }

    private function historyCacheKey(string $symbol, string $from, string $to): string
    {
        return $symbol . '|' . $from . '|' . $to;
    }

    /**
     * @param array<int,string> $symbols
     * @param array<string,int> $indexBySymbol
     * @param array<string,?string> $symbolCurrency
     * @return array<string,array{stock_id:int,symbol:string,last:?float,prev_close:?float,day_high:?float,day_low:?float,volume:?float,currency:?string,provider_ts:?string,stale:bool}>
     */
    private function fetchAndApply(array $symbols, array $indexBySymbol, array $symbolCurrency, ?int $now = null): array
    {
        if (empty($symbols)) {
            return [];
        }

        $now = $now ?? time();
        $chunks = array_chunk($symbols, $this->maxProviderBatch ?: count($symbols));
        $output = [];

        foreach ($chunks as $chunk) {
            $fetched = $this->adapter->fetchLiveQuotes($chunk);
            foreach ($chunk as $symbol) {
                $stockId = $indexBySymbol[$symbol] ?? null;
                if ($stockId === null) {
                    continue;
                }
                $quote = $fetched[$symbol] ?? null;
                if ($quote) {
                    $this->upsertLastQuote($stockId, $quote);
                    $row = $quote + ['provider_ts' => $quote['provider_ts'] ?? null, 'stale' => false];
                    $formatted = $this->formatRow($symbol, $stockId, $row, $symbolCurrency[$symbol] ?? null);
                    $this->memoryCache[$symbol] = ['ts' => $now, 'data' => $formatted];
                    $output[$symbol] = $formatted;
                }
            }
        }

        return $output;
    }

    /**
     * @param array<int,string> $symbols
     * @param array<string,int> $indexBySymbol
     * @param array<string,?string> $symbolCurrency
     */
    private function queueDeferredRefresh(array $symbols, array $indexBySymbol, array $symbolCurrency): void
    {
        foreach ($symbols as $symbol) {
            $stockId = $indexBySymbol[$symbol] ?? null;
            if ($stockId === null) {
                continue;
            }
            $this->deferredRefreshMap[$symbol] = [
                'stock_id' => $stockId,
                'currency' => $symbolCurrency[$symbol] ?? null,
            ];
        }

        $this->registerDeferredProcessor();
    }

    public function processDeferredRefresh(): void
    {
        if (empty($this->deferredRefreshMap) && empty($this->deferredHistoryQueue)) {
            return;
        }

        $this->finishResponseForDeferredWork();

        $payload = $this->deferredRefreshMap;
        $this->deferredRefreshMap = [];
        if (!empty($payload)) {
            $symbols = array_keys($payload);
            $index = [];
            $currencies = [];

            foreach ($payload as $symbol => $info) {
                $index[$symbol] = $info['stock_id'];
                $currencies[$symbol] = $info['currency'];
            }

            $this->fetchAndApply($symbols, $index, $currencies);
        }

        $historyPayload = $this->deferredHistoryQueue;
        $this->deferredHistoryQueue = [];
        if (!empty($historyPayload)) {
            $this->processDeferredHistory($historyPayload);
        }
    }

    private function finishResponseForDeferredWork(): void
    {
        if ($this->responseFinished || PHP_SAPI === 'cli') {
            return;
        }

        $this->responseFinished = true;

        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }

        if (function_exists('session_write_close')) {
            @session_write_close();
        }

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
            return;
        }

        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }
        } elseif (function_exists('ob_flush')) {
            @ob_flush();
        }

        if (function_exists('flush')) {
            @flush();
        }
    }

    /**
     * @param array<string,array{cache_key:string,symbol:string,stock_id:int,from:string,to:string}> $payload
     */
    private function processDeferredHistory(array $payload): void
    {
        if (empty($payload)) {
            return;
        }

        $grouped = [];
        foreach ($payload as $job) {
            $symbol = $job['symbol'];
            if (!isset($grouped[$symbol])) {
                $grouped[$symbol] = [
                    'stock_id' => $job['stock_id'],
                    'symbol' => $symbol,
                    'from' => $job['from'],
                    'to' => $job['to'],
                    'ranges' => [$job],
                ];
            } else {
                if ($job['from'] < $grouped[$symbol]['from']) {
                    $grouped[$symbol]['from'] = $job['from'];
                }
                if ($job['to'] > $grouped[$symbol]['to']) {
                    $grouped[$symbol]['to'] = $job['to'];
                }
                $grouped[$symbol]['ranges'][] = $job;
            }
        }

        $stmt = $this->pdo->prepare('SELECT date, open, high, low, close, volume FROM price_daily WHERE stock_id=? AND date BETWEEN ?::date AND ?::date ORDER BY date ASC');

        foreach ($grouped as $symbol => $bundle) {
            $candles = $this->adapter->fetchDailyHistory($symbol, $bundle['from'], $bundle['to']);
            if (!empty($candles)) {
                foreach ($candles as $candle) {
                    $this->storeDailyCandle($bundle['stock_id'], $candle);
                }
            }

            foreach ($bundle['ranges'] as $range) {
                $stmt->execute([$bundle['stock_id'], $range['from'], $range['to']]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $mapped = array_map(static function ($row) {
                    return [
                        'date' => $row['date'],
                        'open' => $row['open'] !== null ? (float)$row['open'] : null,
                        'high' => $row['high'] !== null ? (float)$row['high'] : null,
                        'low' => $row['low'] !== null ? (float)$row['low'] : null,
                        'close' => $row['close'] !== null ? (float)$row['close'] : null,
                        'volume' => $row['volume'] !== null ? (float)$row['volume'] : null,
                    ];
                }, $rows);

                $this->historyCache[$range['cache_key']] = $mapped;
                $this->historyStaleFlags[$range['cache_key']] = !$this->coversRange($rows, $range['from'], $range['to']);
            }
        }
    }

    private function queueDeferredHistoryFetch(string $cacheKey, string $symbol, int $stockId, string $from, string $to): void
    {
        if (isset($this->deferredHistoryQueue[$cacheKey])) {
            return;
        }

        $this->deferredHistoryQueue[$cacheKey] = [
            'cache_key' => $cacheKey,
            'symbol' => $symbol,
            'stock_id' => $stockId,
            'from' => $from,
            'to' => $to,
        ];

        $this->registerDeferredProcessor();
    }

    private function registerDeferredProcessor(): void
    {
        if ($this->deferredRegistered) {
            return;
        }

        register_shutdown_function([$this, 'processDeferredRefresh']);
        $this->deferredRegistered = true;
    }

    private function emptyQuote(string $symbol, int $stockId, ?string $currency): array
    {
        return [
            'stock_id' => $stockId,
            'symbol' => $symbol,
            'last' => null,
            'prev_close' => null,
            'day_high' => null,
            'day_low' => null,
            'volume' => null,
            'currency' => $currency,
            'provider_ts' => null,
            'stale' => true,
        ];
    }
}
