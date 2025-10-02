<?php

namespace Stocks;

use DateTime;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

class TradeService
{
    private PDO $pdo;

    /**
     * Cached table existence lookups keyed by lower-case table name.
     *
     * @var array<string,bool>
     */
    private array $tableCache = [];

    /**
     * Cached column existence lookups keyed by table => column name.
     *
     * @var array<string,array<string,bool>>
     */
    private array $columnCache = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param array{symbol:string,market?:?string,quantity:float,price:float,fee?:float,side:string,currency:string,executed_at?:?string,note?:?string} $payload
     */
    public function recordTrade(int $userId, array $payload): int
    {
        $symbol = strtoupper(trim($payload['symbol'] ?? ''));
        if ($symbol === '') {
            throw new RuntimeException('Symbol is required');
        }
        $side = strtoupper(trim($payload['side'] ?? ''));
        if (!in_array($side, ['BUY', 'SELL'], true)) {
            throw new RuntimeException('Invalid trade side');
        }
        $quantity = (float)($payload['quantity'] ?? 0);
        if ($quantity <= 0) {
            throw new RuntimeException('Quantity must be positive');
        }
        $price = (float)($payload['price'] ?? 0);
        if ($price <= 0) {
            throw new RuntimeException('Price must be positive');
        }
        $fee = isset($payload['fee']) ? (float)$payload['fee'] : 0.0;
        $currency = strtoupper(trim($payload['currency'] ?? 'USD'));
        $executedAt = $payload['executed_at'] ?? null;
        $executedTs = $executedAt ? new DateTime($executedAt) : new DateTime();
        $note = $payload['note'] ?? null;
        $market = isset($payload['market']) && $payload['market'] !== null && $payload['market'] !== ''
            ? strtoupper((string)$payload['market'])
            : null;

        $usesLegacy = $this->usesLegacySchema();

        if ($usesLegacy) {
            if ($this->tableExists('stocks')) {
                // Populate metadata when the master table exists even in legacy mode.
                $this->ensureStockExists($symbol, $market, $currency);
            }

            return $this->recordTradeLegacy($userId, $symbol, $side, $quantity, $price, $currency, $executedTs, $fee);
        }

        $startedTransaction = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $startedTransaction = true;
        }

        try {
            $stockId = $this->ensureStockExists($symbol, $market, $currency);
            $stmt = $this->pdo->prepare('INSERT INTO stock_trades(user_id, stock_id, symbol, trade_on, side, quantity, price, currency, executed_at, fee, note, market, created_at, updated_at)
                VALUES(?,?,?,?,?,?,?,?,?,?,?, ?, NOW(), NOW()) RETURNING id');
            $stmt->execute([
                $userId,
                $stockId,
                $symbol,
                $executedTs->format('Y-m-d'),
                strtolower($side),
                $quantity,
                $price,
                $currency,
                $executedTs->format('Y-m-d H:i:sP'),
                $fee,
                $note,
                $market,
            ]);
            $tradeId = (int)$stmt->fetchColumn();

            $this->rebuildPositions($userId, $stockId);
            if ($startedTransaction) {
                $this->pdo->commit();
            }
            return $tradeId;
        } catch (Throwable $e) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function recordTradeLegacy(int $userId, string $symbol, string $side, float $quantity, float $price, string $currency, DateTime $executedAt, float $fee): int
    {
        $adjustedPrice = $price;
        if ($fee > 0 && $quantity > 0) {
            if (strtoupper($side) === 'BUY') {
                $adjustedPrice = ($quantity * $price + $fee) / $quantity;
            } else {
                $adjustedPrice = max(0.0, ($quantity * $price - $fee) / $quantity);
            }
        }

        $stmt = $this->pdo->prepare('INSERT INTO stock_trades(user_id, symbol, trade_on, side, quantity, price, currency)
            VALUES(?,?,?,?,?,?,?) RETURNING id');
        $stmt->execute([
            $userId,
            $symbol,
            $executedAt->format('Y-m-d'),
            strtolower($side),
            $quantity,
            $adjustedPrice,
            $currency,
        ]);
        return (int)$stmt->fetchColumn();
    }

    public function deleteTrade(int $userId, int $tradeId): void
    {
        $trade = $this->fetchTrade($userId, $tradeId);
        if (!$trade) {
            return;
        }
        $stockId = (int)$trade['stock_id'];
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM stock_trades WHERE id=? AND user_id=?')->execute([$tradeId, $userId]);
            $this->rebuildPositions($userId, $stockId);
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Remove all stock-related history for the given user.
     *
     * @return array{trades:int,positions:int,lots:int,realized:int,snapshots:int}
     */
    public function clearUserHistory(int $userId): array
    {
        $stats = [
            'trades' => 0,
            'positions' => 0,
            'lots' => 0,
            'realized' => 0,
            'snapshots' => 0,
        ];

        if ($this->usesLegacySchema()) {
            $stmt = $this->pdo->prepare('DELETE FROM stock_trades WHERE user_id=?');
            $stmt->execute([$userId]);
            $stats['trades'] = $stmt->rowCount();
            return $stats;
        }

        $this->pdo->beginTransaction();
        try {
            if ($this->tableExists('stock_lots')) {
                $stmtLots = $this->pdo->prepare('DELETE FROM stock_lots WHERE position_id IN (SELECT id FROM stock_positions WHERE user_id=?)');
                $stmtLots->execute([$userId]);
                $stats['lots'] = $stmtLots->rowCount();
            }

            if ($this->tableExists('stock_realized_pl')) {
                $stmtRealized = $this->pdo->prepare('DELETE FROM stock_realized_pl WHERE user_id=?');
                $stmtRealized->execute([$userId]);
                $stats['realized'] = $stmtRealized->rowCount();
            }

            if ($this->tableExists('stock_positions')) {
                $stmtPositions = $this->pdo->prepare('DELETE FROM stock_positions WHERE user_id=?');
                $stmtPositions->execute([$userId]);
                $stats['positions'] = $stmtPositions->rowCount();
            }

            if ($this->tableExists('stock_portfolio_snapshots')) {
                $stmtSnapshots = $this->pdo->prepare('DELETE FROM stock_portfolio_snapshots WHERE user_id=?');
                $stmtSnapshots->execute([$userId]);
                $stats['snapshots'] = $stmtSnapshots->rowCount();
            }

            $stmtTrades = $this->pdo->prepare('DELETE FROM stock_trades WHERE user_id=?');
            $stmtTrades->execute([$userId]);
            $stats['trades'] = $stmtTrades->rowCount();

            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $stats;
    }

    public function rebuildPositions(int $userId, ?int $stockId = null): void
    {
        if ($this->usesLegacySchema()) {
            return;
        }

        $stocks = $stockId ? [ (int)$stockId ] : $this->loadUserStockIds($userId);
        if (empty($stocks)) {
            return;
        }
        require_once __DIR__ . '/../fx.php';
        $baseCurrency = fx_user_main($this->pdo, $userId);

        foreach ($stocks as $sid) {
            $positionId = $this->ensurePositionRow($userId, $sid);
            $this->pdo->prepare('DELETE FROM stock_lots WHERE position_id=?')->execute([$positionId]);
            $this->pdo->prepare('DELETE FROM stock_realized_pl WHERE user_id=? AND stock_id=?')->execute([$userId, $sid]);
            $this->pdo->prepare('UPDATE stock_positions SET qty=0, avg_cost_ccy=0, cash_impact_ccy=0, updated_at=NOW() WHERE id=?')->execute([$positionId]);

            $stmt = $this->pdo->prepare('SELECT id, side, quantity, price, fee, currency, executed_at FROM stock_trades WHERE user_id=? AND stock_id=? ORDER BY executed_at ASC, id ASC');
            $stmt->execute([$userId, $sid]);
            $trades = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$trades) {
                continue;
            }

            $qty = 0.0;
            $totalCost = 0.0;
            $cashImpact = 0.0;
            $lots = [];

            foreach ($trades as $trade) {
                $tradeQty = (float)$trade['quantity'];
                $tradePrice = (float)$trade['price'];
                $tradeFee = (float)($trade['fee'] ?? 0);
                $executedAt = new DateTime($trade['executed_at']);
                $currency = $trade['currency'] ?: 'USD';
                if ($trade['side'] === 'buy') {
                    $cost = $tradeQty * $tradePrice + $tradeFee;
                    $qty += $tradeQty;
                    $totalCost += $cost;
                    $cashImpact -= $cost;
                    $lotId = $this->insertLot($positionId, $tradeQty, $tradePrice, $tradeFee, $executedAt);
                    $lots[] = [
                        'id' => $lotId,
                        'qty_total' => $tradeQty,
                        'remaining' => $tradeQty,
                        'price' => $tradePrice,
                        'fee' => $tradeFee,
                        'opened_at' => $executedAt,
                    ];
                } else {
                    if ($tradeQty <= 0) {
                        continue;
                    }
                    $availableBefore = max(0.0, $qty);
                    $tolerance = max(1e-6, $availableBefore * 1e-6);
                    $consumeQty = ($tradeQty > $availableBefore + $tolerance)
                        ? $availableBefore
                        : $tradeQty;
                    $remaining = $consumeQty;
                    $sellFeePerShare = $tradeFee > 0 ? $tradeFee / $tradeQty : 0.0;
                    $realizedCcy = 0.0;
                    foreach ($lots as &$lot) {
                        if ($remaining <= 0) {
                            break;
                        }
                        if ($lot['remaining'] <= 0) {
                            continue;
                        }
                        $portion = min($lot['remaining'], $remaining);
                        $buyFeePerShare = $lot['fee'] > 0 && $lot['qty_total'] > 0 ? $lot['fee'] / $lot['qty_total'] : 0.0;
                        $costPerShare = $lot['price'] + $buyFeePerShare;
                        $proceedsPerShare = $tradePrice - $sellFeePerShare;
                        $realizedCcy += ($proceedsPerShare - $costPerShare) * $portion;
                        $lot['remaining'] -= $portion;
                        $qty -= $portion;
                        $totalCost -= $costPerShare * $portion;
                        $this->updateLot($lot['id'], $lot['remaining'], $lot['qty_total'] - $lot['remaining'], $executedAt, $lot['remaining'] <= 1e-8);
                        $remaining -= $portion;
                    }
                    unset($lot);
                    $qty = max(0.0, $qty);
                    if ($remaining > 1e-6) {
                        $remaining = 0.0;
                    }
                    if ($totalCost < 0 && abs($totalCost) <= 1e-6) {
                        $totalCost = 0.0;
                    }
                    $totalCost = max(0.0, $totalCost);
                    $cashImpact += $tradeQty * $tradePrice - $tradeFee;
                    if ($consumeQty > 0) {
                        $realizedBase = fx_convert($this->pdo, $realizedCcy, $currency, $baseCurrency, $executedAt->format('Y-m-d'));
                        $this->insertRealized($userId, $sid, (int)$trade['id'], $realizedBase, $realizedCcy, $currency, $consumeQty, $executedAt);
                    }
                }
            }

            $avgCost = ($qty > 0) ? $totalCost / $qty : 0.0;
            $this->pdo->prepare('UPDATE stock_positions SET qty=?, avg_cost_ccy=?, avg_cost_currency=(SELECT currency FROM stocks WHERE id=?), cash_impact_ccy=?, updated_at=NOW() WHERE id=?')
                ->execute([$qty, $avgCost, $sid, $cashImpact, $positionId]);
        }
    }

    private function ensureStockExists(string $symbol, ?string $market, string $currency): int
    {
        if (!$this->tableExists('stocks')) {
            throw new RuntimeException('Stocks table is not available');
        }
        if ($market !== null && $market !== '') {
            $market = strtoupper($market);
        } else {
            $market = null;
        }
        $stmt = $this->pdo->prepare('SELECT id FROM stocks WHERE UPPER(symbol)=? LIMIT 1');
        $stmt->execute([$symbol]);
        $id = $stmt->fetchColumn();
        if ($id) {
            $this->pdo->prepare('UPDATE stocks SET market=COALESCE(?, market), currency=COALESCE(?, currency), updated_at=NOW() WHERE id=?')
                ->execute([$market, $currency, $id]);
            return (int)$id;
        }
        $insertMarket = $market ?: 'NASDAQ';
        $stmt = $this->pdo->prepare('INSERT INTO stocks(symbol, market, currency, created_at, updated_at) VALUES(UPPER(?), ?, ?, NOW(), NOW()) RETURNING id');
        $stmt->execute([$symbol, $insertMarket, $currency]);
        return (int)$stmt->fetchColumn();
    }

    private function ensurePositionRow(int $userId, int $stockId): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM stock_positions WHERE user_id=? AND stock_id=?');
        $stmt->execute([$userId, $stockId]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int)$id;
        }
        $stmt = $this->pdo->prepare('INSERT INTO stock_positions(user_id, stock_id, avg_cost_currency) VALUES(?,?, (SELECT currency FROM stocks WHERE id=?)) RETURNING id');
        $stmt->execute([$userId, $stockId, $stockId]);
        return (int)$stmt->fetchColumn();
    }

    private function fetchTrade(int $userId, int $tradeId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM stock_trades WHERE id=? AND user_id=?');
        $stmt->execute([$tradeId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function insertLot(int $positionId, float $qty, float $price, float $fee, DateTime $openedAt): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO stock_lots(position_id, qty_open, qty_closed, open_price, fee, opened_at, created_at, updated_at)
            VALUES(?,?,?,?,?,?, NOW(), NOW()) RETURNING id');
        $stmt->execute([$positionId, $qty, 0, $price, $fee, $openedAt->format('Y-m-d H:i:sP')]);
        return (int)$stmt->fetchColumn();
    }

    private function updateLot(int $lotId, float $qtyOpen, float $qtyClosed, DateTime $closedAt, bool $closed): void
    {
        $this->pdo->prepare('UPDATE stock_lots SET qty_open=?, qty_closed=?, closed_at=?, updated_at=NOW() WHERE id=?')
            ->execute([$qtyOpen, $qtyClosed, $closed ? $closedAt->format('Y-m-d H:i:sP') : null, $lotId]);
    }

    private function insertRealized(int $userId, int $stockId, int $tradeId, float $base, float $ccy, string $currency, float $qty, DateTime $closedAt): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO stock_realized_pl(user_id, stock_id, sell_trade_id, realized_pl_base, realized_pl_ccy, currency, method, qty_closed, closed_at, created_at)
            VALUES(?,?,?,?,?,?,?,?,?, NOW())');
        $stmt->execute([$userId, $stockId, $tradeId, $base, $ccy, $currency, 'FIFO', $qty, $closedAt->format('Y-m-d H:i:sP')]);
    }

    /**
     * @return int[]
     */
    private function loadUserStockIds(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT DISTINCT stock_id FROM stock_trades WHERE user_id=?');
        $stmt->execute([$userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function usesLegacySchema(): bool
    {
        if (!$this->tableExists('stock_positions')) {
            return true;
        }
        if (!$this->tableExists('stock_lots')) {
            return true;
        }
        if (!$this->tableExists('stock_realized_pl')) {
            return true;
        }
        if (!$this->tableExists('stocks')) {
            return true;
        }
        if (!$this->columnExists('stock_trades', 'stock_id')) {
            return true;
        }

        return false;
    }

    private function tableExists(string $table): bool
    {
        $key = strtolower($table);
        if (array_key_exists($key, $this->tableCache)) {
            return $this->tableCache[$key];
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = ? LIMIT 1');
        $stmt->execute([$table]);
        $exists = (bool)$stmt->fetchColumn();
        $this->tableCache[$key] = $exists;
        return $exists;
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

}
