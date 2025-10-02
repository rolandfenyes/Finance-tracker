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
        $toFetch = [];
        $indexBySymbol = [];
        $symbolCurrency = [];
        foreach ($known as $row) {
            $symbol = $row['symbol'];
            $indexBySymbol[$symbol] = (int)$row['id'];
            $symbolCurrency[$symbol] = $row['currency'] ?? null;
            if (!$forceRefresh && isset($this->memoryCache[$symbol])) {
                $cached = $this->memoryCache[$symbol];
                if (($cached['ts'] ?? 0) + $this->ttlSeconds > $now) {
                    $result[] = $cached['data'];
                    continue;
                }
            }
            $toFetch[] = $symbol;
        }

        if (!empty($toFetch)) {
            $placeholders = implode(',', array_fill(0, count($toFetch), '?'));
            $ids = array_map(static fn($symbol) => $indexBySymbol[$symbol], $toFetch);
            $lastRows = $this->pdo->prepare("SELECT stock_id,last,prev_close,day_high,day_low,volume,provider_ts,stale,updated_at FROM stock_prices_last WHERE stock_id IN ($placeholders)");
            $lastRows->execute($ids);
            $existing = [];
            foreach ($lastRows as $row) {
                $existing[(int)$row['stock_id']] = $row;
            }

            $needsProvider = [];
            foreach ($toFetch as $symbol) {
                $stockId = $indexBySymbol[$symbol];
                $row = $existing[$stockId] ?? null;
                if ($row && !$forceRefresh) {
                    $updatedAt = strtotime((string)$row['updated_at']);
                    if ($updatedAt && $updatedAt + $this->ttlSeconds > $now) {
                        $formatted = $this->formatRow($symbol, (int)$stockId, $row, $symbolCurrency[$symbol] ?? null);
                        $result[] = $formatted;
                        $this->memoryCache[$symbol] = ['ts' => $now, 'data' => $formatted];
                        continue;
                    }
                }
                $needsProvider[] = $symbol;
            }

            if (!empty($needsProvider)) {
                if ($forceRefresh) {
                    $chunks = array_chunk($needsProvider, $this->maxProviderBatch ?: count($needsProvider));
                    foreach ($chunks as $chunk) {
                        $fetched = $this->adapter->fetchLiveQuotes($chunk);
                        foreach ($chunk as $symbol) {
                            $stockId = $indexBySymbol[$symbol];
                            $quote = $fetched[$symbol] ?? null;
                            if ($quote) {
                                $this->upsertLastQuote($stockId, $quote);
                                $row = $quote + ['provider_ts' => $quote['provider_ts'] ?? null, 'stale' => false];
                                $formatted = $this->formatRow($symbol, $stockId, $row, $symbolCurrency[$symbol] ?? null);
                                $result[] = $formatted;
                                $this->memoryCache[$symbol] = ['ts' => $now, 'data' => $formatted];
                            } elseif (isset($existing[$stockId])) {
                                $row = $existing[$stockId];
                                $row['stale'] = true;
                                $formatted = $this->formatRow($symbol, $stockId, $row, $symbolCurrency[$symbol] ?? null);
                                $result[] = $formatted;
                                $this->memoryCache[$symbol] = ['ts' => $now, 'data' => $formatted];
                            }
                        }
                    }
                } else {
                    $withoutExisting = array_values(array_filter($needsProvider, static function ($symbol) use ($existing, $indexBySymbol) {
                        $stockId = $indexBySymbol[$symbol];
                        return !isset($existing[$stockId]);
                    }));
                    $withExisting = array_values(array_diff($needsProvider, $withoutExisting));

                    $fetchBudget = $this->maxProviderBatch;
                    $fetchList = [];
                    if (!empty($withoutExisting)) {
                        $fetchList = $withoutExisting;
                        $fetchBudget -= count($withoutExisting);
                    }
                    if ($fetchBudget > 0 && !empty($withExisting)) {
                        $fetchList = array_merge($fetchList, array_slice($withExisting, 0, $fetchBudget));
                    }
                    $deferred = array_diff($needsProvider, $fetchList);

                    $fetched = $this->adapter->fetchLiveQuotes($fetchList);
                    foreach ($fetchList as $symbol) {
                        $stockId = $indexBySymbol[$symbol];
                        $quote = $fetched[$symbol] ?? null;
                        if ($quote) {
                            $this->upsertLastQuote($stockId, $quote);
                            $row = $quote + ['provider_ts' => $quote['provider_ts'] ?? null, 'stale' => false];
                            $formatted = $this->formatRow($symbol, $stockId, $row, $symbolCurrency[$symbol] ?? null);
                            $result[] = $formatted;
                            $this->memoryCache[$symbol] = ['ts' => $now, 'data' => $formatted];
                        } elseif (isset($existing[$stockId])) {
                            $row = $existing[$stockId];
                            $row['stale'] = true;
                            $formatted = $this->formatRow($symbol, $stockId, $row, $symbolCurrency[$symbol] ?? null);
                            $result[] = $formatted;
                            $this->memoryCache[$symbol] = ['ts' => $now, 'data' => $formatted];
                        }
                    }

                    foreach ($deferred as $symbol) {
                        $stockId = $indexBySymbol[$symbol];
                        if (!isset($existing[$stockId])) {
                            continue;
                        }
                        $row = $existing[$stockId];
                        $row['stale'] = true;
                        $formatted = $this->formatRow($symbol, $stockId, $row, $symbolCurrency[$symbol] ?? null);
                        $result[] = $formatted;
                        $this->memoryCache[$symbol] = ['ts' => $now, 'data' => $formatted];
                    }
                }
            }
        }

        return array_values($result);
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
        $stockId = $this->lookupStockId($symbol);
        if (!$stockId) {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT date, open, high, low, close, volume FROM price_daily WHERE stock_id=? AND date BETWEEN ?::date AND ?::date ORDER BY date ASC');
        $stmt->execute([$stockId, $from, $to]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$this->coversRange($rows, $from, $to)) {
            $candles = $this->adapter->fetchDailyHistory($symbol, $from, $to);
            foreach ($candles as $candle) {
                $this->storeDailyCandle($stockId, $candle);
            }
            $stmt->execute([$stockId, $from, $to]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return array_map(static function ($row) {
            return [
                'date' => $row['date'],
                'open' => $row['open'] !== null ? (float)$row['open'] : null,
                'high' => $row['high'] !== null ? (float)$row['high'] : null,
                'low' => $row['low'] !== null ? (float)$row['low'] : null,
                'close' => $row['close'] !== null ? (float)$row['close'] : null,
                'volume' => $row['volume'] !== null ? (float)$row['volume'] : null,
            ];
        }, $rows);
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
        $stmt = $this->pdo->prepare('SELECT id FROM stocks WHERE UPPER(symbol)=? LIMIT 1');
        $stmt->execute([$symbol]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
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
}
