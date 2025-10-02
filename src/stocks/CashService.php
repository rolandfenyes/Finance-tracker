<?php

namespace Stocks;

use DateTime;
use PDO;
use PDOException;
use RuntimeException;

class CashService
{
    private PDO $pdo;

    /**
     * Cached flag for table existence check.
     */
    private ?bool $hasTable = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function recordMovement(int $userId, float $amount, string $currency, DateTime $executedAt, ?string $note = null): int
    {
        if (!$this->tableExists()) {
            throw new RuntimeException('Stock cash movements table is missing');
        }

        $amount = round($amount, 2);

        if ($amount === 0.0) {
            throw new RuntimeException('Amount must be non-zero');
        }

        $currency = strtoupper($currency);
        if ($currency === '') {
            $currency = 'USD';
        }

        $stmt = $this->pdo->prepare('INSERT INTO stock_cash_movements(user_id, amount, currency, executed_at, note, created_at)
            VALUES(?,?,?,?,?, NOW()) RETURNING id');
        try {
            $stmt->execute([
                $userId,
                $amount,
                $currency,
                $executedAt->format('Y-m-d H:i:sP'),
                $note,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Failed to record cash movement: ' . $e->getMessage(), 0, $e);
        }

        return (int)$stmt->fetchColumn();
    }

    /**
     * @return array<string,float> keyed by currency code
     */
    public function sumByCurrency(int $userId): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        $stmt = $this->pdo->prepare('SELECT currency, COALESCE(SUM(amount),0) AS total
            FROM stock_cash_movements WHERE user_id = ? GROUP BY currency');
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $totals = [];
        foreach ($rows as $row) {
            $currency = strtoupper($row['currency'] ?? '');
            if ($currency === '') {
                $currency = 'USD';
            }
            $totals[$currency] = (float)$row['total'];
        }
        return $totals;
    }

    private function tableExists(): bool
    {
        if ($this->hasTable !== null) {
            return $this->hasTable;
        }

        $stmt = $this->pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = ?");
        $stmt->execute(['stock_cash_movements']);
        $this->hasTable = (bool)$stmt->fetchColumn();
        return $this->hasTable;
    }
}
