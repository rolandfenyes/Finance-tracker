<?php

declare(strict_types=1);

namespace MyMoneyMap\Stocks\Repositories;

use DateTimeImmutable;
use PDO;
use RuntimeException;

final class TradeRepository
{
    private string $driver;

    public function __construct(private readonly PDO $pdo)
    {
        $this->driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) ?: 'pgsql';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPosition(int $userId, int $stockId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM stock_positions WHERE user_id = ? AND stock_id = ? LIMIT 1');
        $stmt->execute([$userId, $stockId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function upsertPosition(int $userId, int $stockId, float $qty, float $avgCostCcy, string $currency): int
    {
        $existing = $this->findPosition($userId, $stockId);
        if ($existing) {
            $stmt = $this->pdo->prepare('UPDATE stock_positions SET qty = ?, avg_cost_ccy = ?, avg_cost_currency = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->execute([$qty, $avgCostCcy, $currency, $existing['id']]);
            return (int) $existing['id'];
        }

        if ($this->driver === 'sqlite') {
            $stmt = $this->pdo->prepare('INSERT INTO stock_positions(user_id, stock_id, qty, avg_cost_ccy, avg_cost_currency) VALUES(?,?,?,?,?)');
            $stmt->execute([$userId, $stockId, $qty, $avgCostCcy, $currency]);
            return (int) $this->pdo->lastInsertId();
        }
        $stmt = $this->pdo->prepare('INSERT INTO stock_positions(user_id, stock_id, qty, avg_cost_ccy, avg_cost_currency) VALUES(?,?,?,?,?) RETURNING id');
        $stmt->execute([$userId, $stockId, $qty, $avgCostCcy, $currency]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            throw new RuntimeException('Failed to create position');
        }
        return (int) $id;
    }

    public function insertTrade(
        int $userId,
        int $stockId,
        string $side,
        float $qty,
        float $price,
        float $fee,
        string $currency,
        DateTimeImmutable $executedAt,
        ?string $note
    ): int {
        if ($this->driver === 'sqlite') {
            $stmt = $this->pdo->prepare('INSERT INTO stock_trades(user_id, stock_id, side, qty, price, fee, currency, executed_at, note) VALUES(?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$userId, $stockId, $side, $qty, $price, $fee, $currency, $executedAt->format('c'), $note]);
            return (int) $this->pdo->lastInsertId();
        }
        $stmt = $this->pdo->prepare('INSERT INTO stock_trades(user_id, stock_id, side, qty, price, fee, currency, executed_at, note) VALUES(?,?,?,?,?,?,?,?,?) RETURNING id');
        $stmt->execute([$userId, $stockId, $side, $qty, $price, $fee, $currency, $executedAt->format('c'), $note]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            throw new RuntimeException('Failed to insert trade');
        }
        return (int) $id;
    }

    public function deleteTrade(int $userId, int $tradeId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM stock_trades WHERE id = ? AND user_id = ?');
        $stmt->execute([$tradeId, $userId]);
    }

    public function deleteLots(int $positionId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM stock_lots WHERE position_id = ?');
        $stmt->execute([$positionId]);
    }

    public function deleteRealized(int $userId, int $stockId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM stock_realized_pl WHERE user_id = ? AND stock_id = ?');
        $stmt->execute([$userId, $stockId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function tradesForStock(int $userId, int $stockId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM stock_trades WHERE user_id = ? AND stock_id = ? ORDER BY executed_at, id');
        $stmt->execute([$userId, $stockId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createLot(int $positionId, float $qty, float $price, float $fee, string $currency, DateTimeImmutable $openedAt): int
    {
        if ($this->driver === 'sqlite') {
            $stmt = $this->pdo->prepare('INSERT INTO stock_lots(position_id, qty_open, qty_closed, open_price, fee, currency, opened_at) VALUES(?,?,?,?,?,?,?)');
            $stmt->execute([$positionId, $qty, 0, $price, $fee, $currency, $openedAt->format('c')]);
            return (int) $this->pdo->lastInsertId();
        }
        $stmt = $this->pdo->prepare('INSERT INTO stock_lots(position_id, qty_open, qty_closed, open_price, fee, currency, opened_at) VALUES(?,?,?,?,?,?,?) RETURNING id');
        $stmt->execute([$positionId, $qty, 0, $price, $fee, $currency, $openedAt->format('c')]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            throw new RuntimeException('Failed to create lot');
        }
        return (int) $id;
    }

    public function closeLotPartial(int $lotId, float $qtyClosed, bool $fullyClosed, DateTimeImmutable $closedAt): void
    {
        $stmt = $this->pdo->prepare('UPDATE stock_lots SET qty_closed = qty_closed + ?, closed_at = CASE WHEN ? THEN ? ELSE closed_at END WHERE id = ?');
        $stmt->execute([$qtyClosed, $fullyClosed ? 1 : 0, $closedAt->format('c'), $lotId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function holdingsRaw(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT sp.*, s.symbol, s.exchange, s.name, s.currency, s.sector, s.industry FROM stock_positions sp JOIN stocks s ON s.id = sp.stock_id WHERE sp.user_id = ? ORDER BY s.symbol');
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function openLots(int $positionId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM stock_lots WHERE position_id = ? AND qty_open > qty_closed ORDER BY opened_at');
        $stmt->execute([$positionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function persistRealized(int $userId, int $stockId, int $sellTradeId, float $plBase, float $plCcy, string $method): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO stock_realized_pl(user_id, stock_id, sell_trade_id, realized_pl_base, realized_pl_ccy, method) VALUES(?,?,?,?,?,?)');
        $stmt->execute([$userId, $stockId, $sellTradeId, $plBase, $plCcy, $method]);
    }

    public function sumRealizedForStock(int $userId, int $stockId, ?DateTimeImmutable $from = null, ?DateTimeImmutable $to = null): float
    {
        $sql = 'SELECT COALESCE(SUM(realized_pl_base), 0) FROM stock_realized_pl WHERE user_id = ? AND stock_id = ?';
        $params = [$userId, $stockId];
        if ($from) {
            $sql .= ' AND created_at >= ?';
            $params[] = $from->format('c');
        }
        if ($to) {
            $sql .= ' AND created_at <= ?';
            $params[] = $to->format('c');
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (float) ($stmt->fetchColumn() ?: 0);
    }

    public function sumRealized(int $userId, ?DateTimeImmutable $from = null, ?DateTimeImmutable $to = null): float
    {
        $sql = 'SELECT COALESCE(SUM(realized_pl_base), 0) FROM stock_realized_pl WHERE user_id = ?';
        $params = [$userId];
        if ($from) {
            $sql .= ' AND created_at >= ?';
            $params[] = $from->format('c');
        }
        if ($to) {
            $sql .= ' AND created_at <= ?';
            $params[] = $to->format('c');
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (float) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function tradesForUser(int $userId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare('SELECT st.*, s.symbol, s.exchange, s.name FROM stock_trades st JOIN stocks s ON s.id = st.stock_id WHERE st.user_id = ? ORDER BY st.executed_at DESC, st.id DESC LIMIT ?');
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allTrades(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM stock_trades WHERE user_id = ? ORDER BY executed_at, id');
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
