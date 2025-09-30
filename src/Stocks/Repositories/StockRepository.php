<?php

declare(strict_types=1);

namespace MyMoneyMap\Stocks\Repositories;

use PDO;
use PDOException;
use RuntimeException;

final class StockRepository
{
    private string $driver;

    public function __construct(private readonly PDO $pdo)
    {
        $this->driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) ?: 'pgsql';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySymbol(string $symbol, string $exchange): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM stocks WHERE symbol = ? AND exchange = ? LIMIT 1');
        $stmt->execute([$symbol, $exchange]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public function create(string $symbol, string $exchange, string $name, string $currency, array $overrides = []): int
    {
        if ($this->driver === 'sqlite') {
            $stmt = $this->pdo->prepare('INSERT INTO stocks(symbol, exchange, name, currency, sector, industry) VALUES(?,?,?,?,?,?)');
            $stmt->execute([
                strtoupper($symbol),
                strtoupper($exchange),
                $name,
                strtoupper($currency),
                $overrides['sector'] ?? null,
                $overrides['industry'] ?? null,
            ]);
            return (int) $this->pdo->lastInsertId();
        }

        $stmt = $this->pdo->prepare('INSERT INTO stocks(symbol, exchange, name, currency, sector, industry) VALUES(?,?,?,?,?,?) RETURNING id');
        $stmt->execute([
            strtoupper($symbol),
            strtoupper($exchange),
            $name,
            strtoupper($currency),
            $overrides['sector'] ?? null,
            $overrides['industry'] ?? null,
        ]);

        $id = $stmt->fetchColumn();
        if ($id === false) {
            throw new RuntimeException('Failed to create stock');
        }

        return (int) $id;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function upsert(array $payload): int
    {
        $symbol = strtoupper((string) ($payload['symbol'] ?? ''));
        $exchange = strtoupper((string) ($payload['exchange'] ?? ''));
        $name = (string) ($payload['name'] ?? $symbol);
        $currency = strtoupper((string) ($payload['currency'] ?? 'USD'));

        if ($symbol === '' || $exchange === '') {
            throw new RuntimeException('Symbol and exchange are required');
        }

        $existing = $this->findBySymbol($symbol, $exchange);
        if ($existing) {
            $stmt = $this->pdo->prepare('UPDATE stocks SET name = ?, currency = ?, sector = COALESCE(?, sector), industry = COALESCE(?, industry), updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->execute([
                $name,
                $currency,
                $payload['sector'] ?? null,
                $payload['industry'] ?? null,
                $existing['id'],
            ]);
            return (int) $existing['id'];
        }

        return $this->create($symbol, $exchange, $name, $currency, $payload);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(string $term): array
    {
        if ($this->driver === 'sqlite') {
            $stmt = $this->pdo->prepare('SELECT * FROM stocks WHERE UPPER(symbol) LIKE UPPER(?) OR UPPER(name) LIKE UPPER(?) ORDER BY symbol LIMIT 20');
            $stmt->execute(['%' . $term . '%', '%' . $term . '%']);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $stmt = $this->pdo->prepare('SELECT * FROM stocks WHERE symbol ILIKE ? OR name ILIKE ? ORDER BY symbol LIMIT 20');
        $stmt->execute(['%' . $term . '%', '%' . $term . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findOneBySymbol(string $symbol): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM stocks WHERE symbol = ? LIMIT 1');
        $stmt->execute([strtoupper($symbol)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT * FROM stocks WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<int>
     */
    public function watchlistStockIds(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT stock_id FROM watchlist WHERE user_id = ? ORDER BY created_at');
        $stmt->execute([$userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    public function toggleWatchlist(int $userId, int $stockId): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT 1 FROM watchlist WHERE user_id = ? AND stock_id = ?');
            $stmt->execute([$userId, $stockId]);
            if ($stmt->fetchColumn()) {
                $del = $this->pdo->prepare('DELETE FROM watchlist WHERE user_id = ? AND stock_id = ?');
                $del->execute([$userId, $stockId]);
                $this->pdo->commit();
                return false;
            }

            $ins = $this->pdo->prepare('INSERT INTO watchlist(user_id, stock_id) VALUES(?, ?)');
            $ins->execute([$userId, $stockId]);
            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
