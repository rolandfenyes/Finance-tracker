<?php

declare(strict_types=1);

namespace MyMoneyMap\Stocks\Services;

use DateTimeImmutable;
use MyMoneyMap\Stocks\DTO\TradeInput;
use MyMoneyMap\Stocks\DTO\TradeResult;
use MyMoneyMap\Stocks\Repositories\SettingsRepository;
use MyMoneyMap\Stocks\Repositories\StockRepository;
use MyMoneyMap\Stocks\Repositories\TradeRepository;
use PDO;
use RuntimeException;
use Throwable;

use function fx_convert;
use function fx_user_main;

final class TradeService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly StockRepository $stocks,
        private readonly TradeRepository $trades,
        private readonly SettingsRepository $settings,
    ) {
    }

    public function recordTrade(TradeInput $input): TradeResult
    {
        if ($input->quantity <= 0) {
            return new TradeResult(false, null, 'Quantity must be positive.');
        }
        if ($input->price <= 0) {
            return new TradeResult(false, null, 'Price must be positive.');
        }

        $symbol = strtoupper($input->symbol);
        $exchange = strtoupper($input->exchange);
        $currency = strtoupper($input->currency);

        $this->pdo->beginTransaction();
        try {
            $stockId = $this->stocks->upsert([
                'symbol' => $symbol,
                'exchange' => $exchange,
                'name' => $input->name,
                'currency' => $currency,
            ]);

            $tradeId = $this->trades->insertTrade(
                $input->userId,
                $stockId,
                strtoupper($input->side),
                $input->quantity,
                $input->price,
                $input->fee,
                $currency,
                $input->executedAt,
                $input->note
            );

            $this->rebuildPosition($input->userId, $stockId);

            $this->pdo->commit();
            return new TradeResult(true, $tradeId, null);
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return new TradeResult(false, null, $e->getMessage());
        }
    }

    public function deleteTrade(int $userId, int $tradeId): void
    {
        $row = $this->pdo->prepare('SELECT stock_id FROM stock_trades WHERE id = ? AND user_id = ?');
        $row->execute([$tradeId, $userId]);
        $stockId = $row->fetchColumn();
        if ($stockId === false) {
            return;
        }

        $this->pdo->beginTransaction();
        try {
            $this->trades->deleteTrade($userId, $tradeId);
            $this->rebuildPosition($userId, (int) $stockId);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function rebuildPosition(int $userId, int $stockId): void
    {
        $trades = $this->trades->tradesForStock($userId, $stockId);
        $position = $this->trades->findPosition($userId, $stockId);
        $currency = $position['avg_cost_currency'] ?? ($trades[0]['currency'] ?? 'USD');

        if ($position) {
            $positionId = (int) $position['id'];
        } else {
            $positionId = $this->trades->upsertPosition($userId, $stockId, 0.0, 0.0, $currency);
            $position = $this->trades->findPosition($userId, $stockId);
        }

        if ($positionId <= 0) {
            throw new RuntimeException('Could not resolve stock position.');
        }

        $this->trades->deleteLots($positionId);
        $this->trades->deleteRealized($userId, $stockId);

        $openLots = [];
        $qty = 0.0;
        $totalCost = 0.0;
        $baseCurrency = fx_user_main($this->pdo, $userId);

        foreach ($trades as $trade) {
            $side = strtoupper((string) $trade['side']);
            $tradeQty = (float) $trade['qty'];
            $tradePrice = (float) $trade['price'];
            $tradeFee = (float) $trade['fee'];
            $tradeCurrency = strtoupper((string) $trade['currency']);
            $executedAt = new DateTimeImmutable((string) $trade['executed_at']);
            $currency = $tradeCurrency;

            if ($side === 'BUY') {
                $lotId = $this->trades->createLot($positionId, $tradeQty, $tradePrice, $tradeFee, $tradeCurrency, $executedAt);
                $openLots[] = [
                    'id' => $lotId,
                    'qty' => $tradeQty,
                    'used' => 0.0,
                    'price' => $tradePrice,
                    'fee' => $tradeFee,
                ];
                $qty += $tradeQty;
                $totalCost += $tradeQty * $tradePrice + $tradeFee;
                continue;
            }

            if ($side !== 'SELL') {
                continue;
            }

            $available = 0.0;
            foreach ($openLots as $lot) {
                $available += max(0.0, $lot['qty'] - $lot['used']);
            }
            if ($tradeQty - $available > 1e-6) {
                throw new RuntimeException('Sell quantity exceeds available shares.');
            }

            $remaining = $tradeQty;
            $costConsumed = 0.0;
            foreach ($openLots as $index => $lot) {
                $lotRemaining = max(0.0, $lot['qty'] - $lot['used']);
                if ($lotRemaining <= 0.0) {
                    continue;
                }
                $take = min($remaining, $lotRemaining);
                if ($take <= 0.0) {
                    continue;
                }
                $feePerShare = $lot['qty'] > 0 ? $lot['fee'] / $lot['qty'] : 0.0;
                $costPerShare = $lot['price'] + $feePerShare;
                $costConsumed += $costPerShare * $take;
                $openLots[$index]['used'] += $take;
                $this->trades->closeLotPartial($lot['id'], $take, $openLots[$index]['used'] >= $lot['qty'] - 1e-6, $executedAt);
                $remaining -= $take;
                if ($remaining <= 0.0) {
                    break;
                }
            }

            $proceeds = $tradeQty * $tradePrice - $tradeFee;
            $realizedCcy = $proceeds - $costConsumed;
            $realizedBase = fx_convert($this->pdo, $realizedCcy, $tradeCurrency, $baseCurrency, $executedAt->format('Y-m-d'));
            $this->trades->persistRealized($userId, $stockId, (int) $trade['id'], $realizedBase, $realizedCcy, 'FIFO');

            $qty -= $tradeQty;
            $totalCost -= $costConsumed;
        }

        if ($qty <= 1e-6) {
            $qty = 0.0;
            $totalCost = 0.0;
        }
        $avgCost = $qty > 0 ? $totalCost / $qty : 0.0;
        $this->trades->upsertPosition($userId, $stockId, $qty, $avgCost, $currency);
    }
}
