<?php

declare(strict_types=1);

namespace MyMoneyMap\Stocks\Repositories;

use DateTimeImmutable;
use PDO;

final class PriceRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lastPrice(int $stockId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM stock_prices_last WHERE stock_id = ? LIMIT 1');
        $stmt->execute([$stockId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function upsertLastPrice(int $stockId, array $payload): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO stock_prices_last(stock_id, last, prev_close, day_high, day_low, volume, provider_ts) VALUES(?,?,?,?,?,?,?) ON CONFLICT (stock_id) DO UPDATE SET last = EXCLUDED.last, prev_close = EXCLUDED.prev_close, day_high = EXCLUDED.day_high, day_low = EXCLUDED.day_low, volume = EXCLUDED.volume, provider_ts = EXCLUDED.provider_ts, updated_at = CURRENT_TIMESTAMP');
        $stmt->execute([
            $stockId,
            $payload['last'] ?? 0,
            $payload['prev_close'] ?? 0,
            $payload['day_high'] ?? 0,
            $payload['day_low'] ?? 0,
            $payload['volume'] ?? 0,
            $payload['provider_ts'] ?? (new DateTimeImmutable())->format('c'),
        ]);
    }

    public function insertDailyPrice(int $stockId, DateTimeImmutable $date, array $payload): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO price_daily(stock_id, date, open, high, low, close, volume, provider) VALUES(?,?,?,?,?,?,?,?) ON CONFLICT (stock_id, date) DO UPDATE SET open = EXCLUDED.open, high = EXCLUDED.high, low = EXCLUDED.low, close = EXCLUDED.close, volume = EXCLUDED.volume, provider = EXCLUDED.provider, updated_at = CURRENT_TIMESTAMP');
        $stmt->execute([
            $stockId,
            $date->format('Y-m-d'),
            $payload['open'] ?? 0,
            $payload['high'] ?? 0,
            $payload['low'] ?? 0,
            $payload['close'] ?? 0,
            $payload['volume'] ?? 0,
            $payload['provider'] ?? 'local',
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function dailySeries(int $stockId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM price_daily WHERE stock_id = ? AND date BETWEEN ? AND ? ORDER BY date');
        $stmt->execute([$stockId, $from->format('Y-m-d'), $to->format('Y-m-d')]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
