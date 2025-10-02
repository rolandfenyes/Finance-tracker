<?php

namespace Stocks;

require_once __DIR__ . '/CashService.php';
require_once __DIR__ . '/../fx.php';

use DateInterval;
use DateTime;
use PDO;

class PortfolioService
{
    private PDO $pdo;
    private PriceDataService $priceDataService;
    private CashService $cashService;

    /**
     * Directory where serialized overview caches are stored.
     */
    private ?string $cacheDir = null;

    /**
     * Cache lifetime in seconds.
     */
    private int $cacheTtl = 0;

    /**
     * Whether disk-based caching is enabled.
     */
    private bool $cacheEnabled = false;

    /**
     * Simple in-request cache for repeated overview calls.
     *
     * @var array<string,array>
     */
    private array $runtimeCache = [];

    /**
     * Cached table existence lookups to avoid repeated information_schema hits.
     *
     * @var array<string,bool>
     */
    private array $schemaCache = [];

    /**
     * Cached column existence lookups keyed by table => column name.
     *
     * @var array<string,array<string,bool>>
     */
    private array $columnCache = [];

    public function __construct(PDO $pdo, PriceDataService $priceDataService, ?CashService $cashService = null, ?string $cacheDir = null, ?int $cacheTtl = null)
    {
        $this->pdo = $pdo;
        $this->priceDataService = $priceDataService;
        $this->cashService = $cashService ?? new CashService($pdo);
        if ($cacheDir !== null && $cacheDir !== '') {
            $normalized = rtrim($cacheDir, DIRECTORY_SEPARATOR);
            if (!is_dir($normalized)) {
                @mkdir($normalized, 0775, true);
            }
            if (is_dir($normalized) && is_writable($normalized)) {
                $this->cacheDir = $normalized;
                $this->cacheTtl = max(0, (int)($cacheTtl ?? 0));
                $this->cacheEnabled = $this->cacheTtl > 0;
            }
        }
    }

    /**
     * @param array{search?:?string,sector?:?string,currency?:?string,watchlist_only?:bool,realized_period?:?string} $filters
     * @return array<string,mixed>
     */
    public function buildOverview(int $userId, array $filters = [], bool $includeTransactions = false): array
    {
        $cacheKey = $this->cacheKey($userId, $filters, $includeTransactions);
        if (isset($this->runtimeCache[$cacheKey])) {
            return $this->runtimeCache[$cacheKey];
        }

        $baseCurrency = fx_user_main($this->pdo, $userId) ?: 'EUR';
        $today = (new DateTime())->format('Y-m-d');

        $shouldUseDiskCache = $this->cacheEnabled && !$includeTransactions;
        if ($shouldUseDiskCache) {
            $cached = $this->readCache($cacheKey);
            if ($cached !== null) {
                $this->runtimeCache[$cacheKey] = $cached;
                return $cached;
            }
        }

        $positions = $this->loadPositions($userId, $filters);
        $watchlistRows = $this->loadWatchlistRows($userId);

        $positionSymbols = array_map(static fn($row) => $row['symbol'], $positions);
        $watchlistSymbols = array_map(static fn($row) => $row['symbol'], $watchlistRows);
        $symbols = array_values(array_unique(array_merge($positionSymbols, $watchlistSymbols)));
        $quotes = $this->priceDataService->getLiveQuotes($symbols);
        $quotesBySymbol = [];
        foreach ($quotes as $quote) {
            $quotesBySymbol[$quote['symbol']] = $quote;
        }

        $holdings = [];
        $totalMarketBase = 0.0;
        $totalCostBase = 0.0;
        $totalDailyBase = 0.0;
        $cashImpactBase = 0.0;
        $marketByCurrency = [];
        $unrealizedByCurrency = [];

        foreach ($positions as $row) {
            $symbol = $row['symbol'];
            $currency = $row['currency'] ?: 'USD';
            $qty = (float)$row['qty'];
            $avgCost = (float)$row['avg_cost_ccy'];
            $quote = $quotesBySymbol[$symbol] ?? null;
            $last = $quote['last'] ?? null;
            $prevClose = $quote['prev_close'] ?? null;
            $marketValueCcy = $last !== null ? $qty * $last : $qty * $avgCost;
            $marketValueBase = fx_convert($this->pdo, $marketValueCcy, $currency, $baseCurrency, $today);
            $costCcy = $qty * $avgCost;
            $costBase = fx_convert($this->pdo, $costCcy, $currency, $baseCurrency, $today);
            $unrealizedCcy = $last !== null ? ($last - $avgCost) * $qty : 0.0;
            $unrealizedBase = fx_convert($this->pdo, $unrealizedCcy, $currency, $baseCurrency, $today);
            $dayPlCcy = ($last !== null && $prevClose !== null) ? ($last - $prevClose) * $qty : 0.0;
            $dayPlBase = fx_convert($this->pdo, $dayPlCcy, $currency, $baseCurrency, $today);
            $cashImpactBase += fx_convert($this->pdo, (float)$row['cash_impact_ccy'], $currency, $baseCurrency, $today);

            $marketByCurrency[$currency] = ($marketByCurrency[$currency] ?? 0.0) + $marketValueCcy;
            $unrealizedByCurrency[$currency] = ($unrealizedByCurrency[$currency] ?? 0.0) + $unrealizedCcy;

            $holdings[] = [
                'symbol' => $symbol,
                'name' => $row['name'] ?? $symbol,
                'currency' => $currency,
                'sector' => $row['sector'] ?? null,
                'industry' => $row['industry'] ?? null,
                'qty' => $qty,
                'avg_cost' => $avgCost,
                'last_price' => $last,
                'prev_close' => $prevClose,
                'market_value_ccy' => $marketValueCcy,
                'market_value_base' => $marketValueBase,
                'cost_ccy' => $costCcy,
                'cost_base' => $costBase,
                'unrealized_ccy' => $unrealizedCcy,
                'unrealized_base' => $unrealizedBase,
                'unrealized_pct' => $costBase > 0 ? ($unrealizedBase / $costBase) * 100 : null,
                'day_pl_ccy' => $dayPlCcy,
                'day_pl_base' => $dayPlBase,
                'stale' => $quote['stale'] ?? false,
            ];
            $totalMarketBase += $marketValueBase;
            $totalCostBase += $costBase;
            $totalDailyBase += $dayPlBase;
        }

        $weights = $this->distributeWeights($holdings, $totalMarketBase);
        foreach ($holdings as $idx => $holding) {
            $holdings[$idx]['weight_pct'] = $weights[$holding['symbol']] ?? 0.0;
            $holdings[$idx]['risk_note'] = ($holdings[$idx]['weight_pct'] > 15) ? 'High concentration' : null;
        }

        $cashEntries = [];
        $cashBalanceBase = 0.0;
        $cashTotals = $this->cashService->sumByCurrency($userId);
        foreach ($cashTotals as $currencyCode => $amount) {
            $converted = fx_convert($this->pdo, $amount, $currencyCode, $baseCurrency, $today);
            $cashEntries[] = [
                'currency' => $currencyCode,
                'amount' => $amount,
                'amount_base' => $converted,
            ];
            $cashBalanceBase += $converted;
        }

        $realizedPeriod = $filters['realized_period'] ?? 'YTD';
        [$from, $to] = $this->resolvePeriodRange($realizedPeriod);
        $realized = $this->sumRealized($userId, $from, $to);

        $overviewTotals = [
            'base_currency' => $baseCurrency,
            'total_market_value' => $totalMarketBase,
            'total_cost' => $totalCostBase,
            'unrealized_pl' => $totalMarketBase - $totalCostBase,
            'unrealized_pct' => ($totalCostBase > 0) ? (($totalMarketBase - $totalCostBase) / $totalCostBase) * 100 : 0.0,
            'daily_pl' => $totalDailyBase,
            'cash_impact' => $cashImpactBase,
            'cash_balance' => $cashBalanceBase,
            'realized_pl' => $realized['base'],
            'realized_period' => $realizedPeriod,
            'total_market_value_by_currency' => $this->cleanCurrencyTotals($marketByCurrency),
            'unrealized_by_currency' => $this->cleanCurrencyTotals($unrealizedByCurrency),
            'cash_by_currency' => $this->cleanCurrencyTotals($cashTotals),
            'realized_by_currency' => $this->cleanCurrencyTotals($realized['by_currency']),
        ];

        $allocations = $this->buildAllocations($holdings);
        $watchlist = $this->buildWatchlist($watchlistRows, $quotesBySymbol, $baseCurrency, $today);
        $transactions = $includeTransactions ? $this->loadTransactions($userId) : [];

        $snapshot = [
            'totals' => $overviewTotals,
            'holdings' => $holdings,
            'allocations' => $allocations,
            'watchlist' => $watchlist,
            'cash' => $cashEntries,
            'trades' => $transactions,
        ];

        $this->runtimeCache[$cacheKey] = $snapshot;
        if ($shouldUseDiskCache) {
            $this->writeCache($cacheKey, $snapshot);
        }

        return $snapshot;
    }

    /**
     * Expose transactions without forcing the overview snapshot to hydrate them every time.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listTransactions(int $userId): array
    {
        return $this->loadTransactions($userId);
    }

    public function invalidateOverviewCache(int $userId): void
    {
        $prefix = $this->cacheKeyPrefix($userId);
        foreach (array_keys($this->runtimeCache) as $key) {
            if (strpos($key, $prefix) === 0) {
                unset($this->runtimeCache[$key]);
            }
        }

        if ($this->cacheDir === null) {
            return;
        }

        $pattern = $this->cacheDir . DIRECTORY_SEPARATOR . $prefix . '*.json';
        $files = glob($pattern) ?: [];
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    private function cacheKey(int $userId, array $filters, bool $includeTransactions): string
    {
        ksort($filters);
        return sprintf('%s%s_%s', $this->cacheKeyPrefix($userId), $includeTransactions ? 'with_tx' : 'summary', sha1(json_encode($filters)));
    }

    private function cacheKeyPrefix(int $userId): string
    {
        return 'overview_' . $userId . '_';
    }

    private function cacheFile(string $cacheKey): ?string
    {
        if (!$this->cacheEnabled || $this->cacheDir === null) {
            return null;
        }

        return $this->cacheDir . DIRECTORY_SEPARATOR . $cacheKey . '.json';
    }

    private function readCache(string $cacheKey): ?array
    {
        $file = $this->cacheFile($cacheKey);
        if ($file === null || !is_file($file)) {
            return null;
        }

        $modified = filemtime($file);
        if ($modified === false || $modified + $this->cacheTtl <= time()) {
            @unlink($file);
            return null;
        }

        $raw = file_get_contents($file);
        if ($raw === false || $raw === '') {
            return null;
        }

        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    private function writeCache(string $cacheKey, array $payload): void
    {
        $file = $this->cacheFile($cacheKey);
        if ($file === null) {
            return;
        }

        $json = json_encode($payload, JSON_PRESERVE_ZERO_FRACTION);
        if ($json === false) {
            return;
        }

        file_put_contents($file, $json, LOCK_EX);
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>>
     */
    private function loadPositions(int $userId, array $filters): array
    {
        if ($this->tableExists('stock_positions') && $this->tableExists('stocks')) {
            return $this->loadPositionsFromSnapshots($userId, $filters);
        }

        return $this->loadPositionsLegacy($userId, $filters);
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>>
     */
    private function loadPositionsFromSnapshots(int $userId, array $filters): array
    {
        $sql = 'SELECT sp.qty, sp.avg_cost_ccy, sp.avg_cost_currency, sp.cash_impact_ccy, s.symbol, s.name, s.currency, s.sector, s.industry
            FROM stock_positions sp
            JOIN stocks s ON s.id = sp.stock_id';
        $params = [$userId];
        $watchlistOnly = !empty($filters['watchlist_only']);
        if ($watchlistOnly && $this->tableExists('watchlist')) {
            $sql .= ' JOIN watchlist w ON w.stock_id = sp.stock_id AND w.user_id = sp.user_id';
        } elseif ($watchlistOnly) {
            return [];
        }
        $sql .= ' WHERE sp.user_id = ? AND sp.qty <> 0';
        if (!empty($filters['sector'])) {
            $sql .= ' AND s.sector = ?';
            $params[] = $filters['sector'];
        }
        if (!empty($filters['currency'])) {
            $sql .= ' AND s.currency = ?';
            $params[] = strtoupper($filters['currency']);
        }
        if (!empty($filters['search'])) {
            $sql .= ' AND (UPPER(s.symbol) LIKE ? OR UPPER(COALESCE(s.name, \'\')) LIKE ?)';
            $needle = '%' . strtoupper($filters['search']) . '%';
            $params[] = $needle;
            $params[] = $needle;
        }
        $sql .= ' ORDER BY s.symbol ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $filtered = [];
        foreach ($rows as $row) {
            $qty = isset($row['qty']) ? (float)$row['qty'] : 0.0;
            if ($qty <= 1e-6) {
                continue;
            }
            $row['qty'] = max(0.0, $qty);
            $filtered[] = $row;
        }

        return $filtered;
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>>
     */
    private function loadPositionsLegacy(int $userId, array $filters): array
    {
        $hasStocksTable = $this->tableExists('stocks');
        $watchlistOnly = !empty($filters['watchlist_only']);
        $hasWatchlist = $this->tableExists('watchlist');
        if ($watchlistOnly && (!$hasStocksTable || !$hasWatchlist)) {
            return [];
        }

        $select = 'SELECT agg.qty, agg.avg_cost_ccy, agg.currency AS avg_cost_currency, agg.cash_impact_ccy, agg.symbol';
        if ($hasStocksTable) {
            $select .= ', COALESCE(s.name, agg.symbol) AS name, COALESCE(s.currency, agg.currency) AS currency, s.sector, s.industry';
        } else {
            $select .= ', agg.symbol AS name, agg.currency AS currency, NULL AS sector, NULL AS industry';
        }

        $sql = $select . " FROM (
            SELECT
                UPPER(symbol) AS symbol,
                SUM(CASE WHEN LOWER(side) = 'buy' THEN quantity ELSE -quantity END) AS qty,
                CASE
                    WHEN SUM(CASE WHEN LOWER(side) = 'buy' THEN quantity ELSE -quantity END) <> 0
                        THEN SUM(CASE WHEN LOWER(side) = 'buy' THEN quantity * price ELSE 0 END)
                            / NULLIF(SUM(CASE WHEN LOWER(side) = 'buy' THEN quantity ELSE 0 END), 0)
                    ELSE 0
                END AS avg_cost_ccy,
                COALESCE(MAX(currency), 'USD') AS currency,
                SUM(CASE WHEN LOWER(side) = 'buy' THEN -(quantity * price) ELSE (quantity * price) END) AS cash_impact_ccy
            FROM stock_trades
            WHERE user_id = ?
            GROUP BY UPPER(symbol)
        ) agg";

        $params = [$userId];
        if ($hasStocksTable) {
            $sql .= ' LEFT JOIN stocks s ON UPPER(s.symbol) = agg.symbol';
        }
        if ($watchlistOnly && $hasWatchlist) {
            $sql .= ' JOIN watchlist w ON w.stock_id = s.id AND w.user_id = ?';
            $params[] = $userId;
        }

        $conditions = ['agg.qty <> 0'];
        if (!empty($filters['sector']) && $hasStocksTable) {
            $conditions[] = 's.sector = ?';
            $params[] = $filters['sector'];
        }
        if (!empty($filters['currency'])) {
            $conditions[] = ($hasStocksTable ? 'COALESCE(s.currency, agg.currency)' : 'agg.currency') . ' = ?';
            $params[] = strtoupper($filters['currency']);
        }
        if (!empty($filters['search'])) {
            $conditions[] = '(agg.symbol LIKE ?' . ($hasStocksTable ? ' OR UPPER(COALESCE(s.name, \'\')) LIKE ?' : '') . ')';
            $needle = '%' . strtoupper($filters['search']) . '%';
            $params[] = $needle;
            if ($hasStocksTable) {
                $params[] = $needle;
            }
        }
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY agg.symbol ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $filtered = [];
        foreach ($rows as $row) {
            $qty = isset($row['qty']) ? (float)$row['qty'] : 0.0;
            if ($qty <= 1e-6) {
                continue;
            }
            $row['qty'] = max(0.0, $qty);
            $filtered[] = $row;
        }

        if (!$hasStocksTable) {
            foreach ($filtered as &$row) {
                $row['sector'] = $row['sector'] ?? null;
                $row['industry'] = $row['industry'] ?? null;
            }
            unset($row);
        }

        return $filtered;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function loadTransactions(int $userId): array
    {
        if (!$this->tableExists('stock_trades')) {
            return [];
        }

        $hasStocks = $this->tableExists('stocks');
        $hasStockId = $this->columnExists('stock_trades', 'stock_id');
        $hasExecutedAt = $this->columnExists('stock_trades', 'executed_at');
        $hasFee = $this->columnExists('stock_trades', 'fee');
        $hasNote = $this->columnExists('stock_trades', 'note');
        $hasMarket = $this->columnExists('stock_trades', 'market');

        $select = 'SELECT t.id, UPPER(t.symbol) AS symbol, t.side, t.quantity, t.price, t.currency, t.trade_on';
        $select .= $hasExecutedAt ? ', t.executed_at' : ', NULL AS executed_at';
        $select .= $hasFee ? ', t.fee' : ', NULL AS fee';
        $select .= $hasNote ? ', t.note' : ', NULL AS note';
        $select .= $hasMarket ? ', t.market' : ', NULL AS market';

        if ($hasStocks) {
            if ($hasStockId) {
                $select .= ', s.name, s.currency AS stock_currency';
            } else {
                $select .= ', s.name, COALESCE(s.currency, t.currency) AS stock_currency';
            }
        } else {
            $select .= ', NULL AS name, t.currency AS stock_currency';
        }

        $sql = $select . ' FROM stock_trades t';
        if ($hasStocks) {
            if ($hasStockId) {
                $sql .= ' LEFT JOIN stocks s ON s.id = t.stock_id';
            } else {
                $sql .= ' LEFT JOIN stocks s ON UPPER(s.symbol) = UPPER(t.symbol)';
            }
        }
        $sql .= ' WHERE t.user_id = ?';
        $orderExpr = $hasExecutedAt ? 'COALESCE(t.executed_at, t.trade_on::timestamptz)' : 't.trade_on';
        $sql .= ' ORDER BY ' . $orderExpr . ' DESC, t.id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            if (!isset($row['stock_currency']) || $row['stock_currency'] === null) {
                $row['stock_currency'] = $row['currency'] ?? 'USD';
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<int,array<string,mixed>> $holdings
     * @return array<string,float>
     */
    private function distributeWeights(array $holdings, float $totalMarket): array
    {
        $weights = [];
        if ($totalMarket <= 0) {
            foreach ($holdings as $holding) {
                $weights[$holding['symbol']] = 0.0;
            }
            return $weights;
        }
        foreach ($holdings as $holding) {
            $weights[$holding['symbol']] = ($holding['market_value_base'] / $totalMarket) * 100;
        }
        return $weights;
    }

    private function resolvePeriodRange(string $period): array
    {
        $now = new DateTime();
        $end = clone $now;
        $start = new DateTime($now->format('Y-01-01'));
        switch (strtoupper($period)) {
            case '1M':
                $start = (clone $now)->sub(new DateInterval('P1M'));
                break;
            case '3M':
                $start = (clone $now)->sub(new DateInterval('P3M'));
                break;
            case '1Y':
                $start = (clone $now)->sub(new DateInterval('P1Y'));
                break;
            case 'ALL':
                $start = new DateTime('2000-01-01');
                break;
            case 'YTD':
            default:
                break;
        }
        return [$start->format('Y-m-d'), $end->format('Y-m-d')];
    }

    private function sumRealized(int $userId, string $from, string $to): array
    {
        if (!$this->tableExists('stock_realized_pl')) {
            return ['base' => 0.0, 'by_currency' => []];
        }
        $params = [$userId, $from . ' 00:00:00', $to . ' 23:59:59'];
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(realized_pl_base),0) FROM stock_realized_pl WHERE user_id=? AND closed_at BETWEEN ?::timestamptz AND ?::timestamptz');
        $stmt->execute($params);
        $base = (float)$stmt->fetchColumn();

        $stmtCurrency = $this->pdo->prepare('SELECT currency, COALESCE(SUM(realized_pl_ccy),0) AS total FROM stock_realized_pl WHERE user_id=? AND closed_at BETWEEN ?::timestamptz AND ?::timestamptz GROUP BY currency');
        $stmtCurrency->execute($params);
        $byCurrency = [];
        foreach ($stmtCurrency->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $currency = strtoupper((string)($row['currency'] ?? ''));
            if ($currency === '') {
                continue;
            }
            $byCurrency[$currency] = (float)$row['total'];
        }

        return [
            'base' => $base,
            'by_currency' => $byCurrency,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $holdings
     * @return array<string,array<string,float>>
     */
    private function buildAllocations(array $holdings): array
    {
        $byTicker = [];
        $bySector = [];
        $byCurrency = [];
        foreach ($holdings as $holding) {
            $tickerShare = $holding['market_value_base'];
            $symbol = $holding['symbol'];
            $byTicker[$symbol] = ($byTicker[$symbol] ?? 0.0) + $tickerShare;
            if (!empty($holding['sector'])) {
                $bySector[$holding['sector']] = ($bySector[$holding['sector']] ?? 0.0) + $tickerShare;
            }
            if (!empty($holding['currency'])) {
                $currency = $holding['currency'];
                $byCurrency[$currency] = ($byCurrency[$currency] ?? 0.0) + $tickerShare;
            }
        }
        $total = array_sum($byTicker);
        $format = static function (array $data, float $total): array {
            if ($total <= 0) {
                return [];
            }
            $result = [];
            foreach ($data as $label => $value) {
                $result[] = [
                    'label' => $label,
                    'value' => $value,
                    'weight_pct' => ($value / $total) * 100,
                ];
            }
            usort($result, static fn($a, $b) => $b['value'] <=> $a['value']);
            return $result;
        };
        return [
            'by_ticker' => $format($byTicker, $total),
            'by_sector' => $format($bySector, $total),
            'by_currency' => $format($byCurrency, $total),
        ];
    }

    private function buildWatchlist(array $rows, array $quotesBySymbol, string $baseCurrency, string $today): array
    {
        if (!$rows) {
            return [];
        }
        require_once __DIR__ . '/../fx.php';
        $watchlist = [];
        foreach ($rows as $row) {
            $symbol = $row['symbol'];
            $quote = $quotesBySymbol[$symbol] ?? null;
            $last = $quote['last'] ?? null;
            $currency = $row['currency'] ?? 'USD';
            $lastBase = $last !== null ? fx_convert($this->pdo, $last, $currency, $baseCurrency, $today) : null;
            $watchlist[] = [
                'symbol' => $symbol,
                'name' => $row['name'] ?? $symbol,
                'currency' => $currency,
                'last_price' => $last,
                'last_price_base' => $lastBase,
                'prev_close' => $quote['prev_close'] ?? null,
                'stale' => $quote['stale'] ?? false,
            ];
        }
        return $watchlist;
    }

    private function loadWatchlistRows(int $userId): array
    {
        if (!$this->tableExists('watchlist') || !$this->tableExists('stocks')) {
            return [];
        }

        $stmt = $this->pdo->prepare('SELECT w.stock_id, s.symbol, s.name, s.currency FROM watchlist w JOIN stocks s ON s.id = w.stock_id WHERE w.user_id=? ORDER BY s.symbol');
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string,float> $totals
     * @return array<string,float>
     */
    private function cleanCurrencyTotals(array $totals): array
    {
        $filtered = [];
        foreach ($totals as $currency => $amount) {
            $currency = strtoupper((string)$currency);
            if ($currency === '') {
                continue;
            }
            if (abs($amount) < 0.0005) {
                continue;
            }
            $filtered[$currency] = $amount;
        }

        if (!$filtered) {
            return [];
        }

        uasort($filtered, static function (float $a, float $b): int {
            return abs($b) <=> abs($a);
        });

        return $filtered;
    }

    private function columnExists(string $table, string $column): bool
    {
        $tableKey = strtolower($table);
        $columnKey = strtolower($column);
        if (isset($this->columnCache[$tableKey][$columnKey])) {
            return $this->columnCache[$tableKey][$columnKey];
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ? LIMIT 1');
        $stmt->execute([$table, $column]);
        $exists = (bool)$stmt->fetchColumn();
        if (!isset($this->columnCache[$tableKey])) {
            $this->columnCache[$tableKey] = [];
        }
        $this->columnCache[$tableKey][$columnKey] = $exists;
        return $exists;
    }

    private function tableExists(string $table): bool
    {
        $key = strtolower($table);
        if (array_key_exists($key, $this->schemaCache)) {
            return $this->schemaCache[$key];
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_name = ? LIMIT 1');
        $stmt->execute([$key]);
        $exists = (bool)$stmt->fetchColumn();
        $this->schemaCache[$key] = $exists;
        return $exists;
    }
}
