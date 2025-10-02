<?php

namespace Stocks;

use DateTime;
use PDO;

class SignalsService
{
    private PDO $pdo;
    private PriceDataService $priceDataService;
    /** @var array<int,array<string,float>> */
    private array $targetAllocationCache = [];

    public function __construct(PDO $pdo, PriceDataService $priceDataService)
    {
        $this->pdo = $pdo;
        $this->priceDataService = $priceDataService;
    }

    /**
     * @param array{symbol:string,currency:string,avg_cost:float,qty:float,last_price:?float,weight_pct:float} $holding
     * @param array{prev_close:?float} $quote
     * @param array<string,float> $portfolioTotals expects keys target_allocation? etc.
     * @return array{signals:array<int,array{label:string,value:string}>,suggestions:array<int,string>}
     */
    public function analyze(int $userId, array $holding, array $quote, array $portfolioTotals = []): array
    {
        $symbol = $holding['symbol'];
        $endDate = new DateTime();
        $startDate = (clone $endDate)->modify('-180 days');
        $history = $this->priceDataService->getDailyHistory($symbol, $startDate->format('Y-m-d'), $endDate->format('Y-m-d'));
        $closes = array_map(static fn($candle) => $candle['close'], $history);
        $signals = [];

        $sma20 = $this->simpleMovingAverage($closes, 20);
        $sma50 = $this->simpleMovingAverage($closes, 50);
        $last = $holding['last_price'];
        if ($sma20 !== null) {
            $signals[] = [
                'label' => '20D SMA',
                'value' => sprintf('%0.2f (%s)', $sma20, $last !== null ? ($last >= $sma20 ? 'above' : 'below') : 'n/a'),
            ];
        }
        if ($sma50 !== null) {
            $signals[] = [
                'label' => '50D SMA',
                'value' => sprintf('%0.2f (%s)', $sma50, $last !== null ? ($last >= $sma50 ? 'above' : 'below') : 'n/a'),
            ];
        }

        $rsi = $this->computeRsi($closes, 14);
        if ($rsi !== null) {
            $signals[] = [
                'label' => 'RSI(14)',
                'value' => sprintf('%0.1f', $rsi),
            ];
        }

        $gap = null;
        if ($last !== null && !empty($quote['prev_close'])) {
            $gap = $quote['prev_close'] != 0.0 ? (($last - $quote['prev_close']) / $quote['prev_close']) * 100 : null;
            if ($gap !== null) {
                $signals[] = [
                    'label' => 'Gap vs Prev Close',
                    'value' => sprintf('%+.2f%%', $gap),
                ];
            }
        }

        $positionHealth = $this->positionHealth($holding, $closes);
        if ($positionHealth) {
            $signals[] = [
                'label' => 'Position Health',
                'value' => $positionHealth,
            ];
        }

        $suggestions = $this->buildSuggestions($holding, $sma50, $rsi, $gap, $portfolioTotals);

        return [
            'signals' => $signals,
            'suggestions' => $suggestions,
        ];
    }

    private function simpleMovingAverage(array $values, int $period): ?float
    {
        if (count($values) < $period) {
            return null;
        }
        $slice = array_slice($values, -$period);
        $slice = array_filter($slice, static fn($v) => $v !== null);
        if (count($slice) < $period / 2) {
            return null;
        }
        return array_sum($slice) / count($slice);
    }

    private function computeRsi(array $closes, int $period): ?float
    {
        if (count($closes) <= $period) {
            return null;
        }
        $gains = 0.0;
        $losses = 0.0;
        for ($i = count($closes) - $period; $i < count($closes); $i++) {
            if (!isset($closes[$i - 1]) || $closes[$i] === null || $closes[$i - 1] === null) {
                continue;
            }
            $change = $closes[$i] - $closes[$i - 1];
            if ($change >= 0) {
                $gains += $change;
            } else {
                $losses += abs($change);
            }
        }
        $avgGain = $gains / $period;
        $avgLoss = $losses / $period;
        if ($avgLoss == 0.0) {
            return 100.0;
        }
        $rs = $avgGain / $avgLoss;
        return 100 - (100 / (1 + $rs));
    }

    private function positionHealth(array $holding, array $closes): ?string
    {
        if (empty($closes) || $holding['last_price'] === null) {
            return null;
        }
        $last = $holding['last_price'];
        $avgCost = $holding['avg_cost'];
        $diff = $avgCost > 0 ? (($last - $avgCost) / $avgCost) * 100 : null;
        $valid = array_filter($closes, static fn($v) => $v !== null);
        if (empty($valid)) {
            return null;
        }
        $parts = [];
        $peak = max($valid);
        $drawdown = $peak > 0 ? (($last - $peak) / $peak) * 100 : null;
        if ($diff !== null) {
            $parts[] = sprintf('%.2f%% vs avg cost', $diff);
        }
        if ($drawdown !== null) {
            $parts[] = sprintf('%.2f%% from 6M peak', $drawdown);
        }
        return $parts ? implode(' · ', $parts) : null;
    }

    /**
     * @param array<string,mixed> $portfolioTotals
     * @return string[]
     */
    private function buildSuggestions(array $holding, ?float $sma50, ?float $rsi, ?float $gap, array $portfolioTotals): array
    {
        $suggestions = [];
        $last = $holding['last_price'];
        $weight = $holding['weight_pct'] ?? 0.0;
        $target = $this->targetAllocation($holding['symbol'], $portfolioTotals);

        if ($last !== null && $sma50 !== null && $last > $sma50 && $weight < $target) {
            $suggestions[] = sprintf('Potential add — price %.2f above 50D SMA %.2f, weight %.1f%% vs target %.1f%%', $last, $sma50, $weight, $target);
        }
        if (($weight > $target && $weight > 0) || ($rsi !== null && $rsi > 70)) {
            $reason = $weight > $target ? sprintf('weight %.1f%% > target %.1f%%', $weight, $target) : sprintf('RSI %.1f > 70', $rsi);
            $suggestions[] = 'Trim — ' . $reason;
        }
        if ($gap !== null && abs($gap) > 3) {
            $suggestions[] = sprintf('Consider stop update — gap %+.2f%% today', $gap);
        }
        if ($rsi !== null && $rsi > 40 && $rsi < 60 && $sma50 !== null && $last !== null && abs($last - $sma50) / $sma50 < 0.02) {
            $suggestions[] = 'Watch only — momentum mixed and RSI neutral';
        }
        if ($weight > 15) {
            $suggestions[] = sprintf('Concentration warning — %.1f%% of portfolio', $weight);
        }
        return array_values(array_unique($suggestions));
    }

    /**
     * @param array<string,mixed> $portfolioTotals
     */
    private function targetAllocation(string $symbol, array $portfolioTotals): float
    {
        $targets = $this->loadTargetAllocations((int)($portfolioTotals['user_id'] ?? 0));
        if (isset($targets[$symbol])) {
            return (float)$targets[$symbol];
        }
        return isset($portfolioTotals['default_target']) ? (float)$portfolioTotals['default_target'] : 10.0;
    }

    /**
     * @return array<string,float>
     */
    private function loadTargetAllocations(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        if (array_key_exists($userId, $this->targetAllocationCache)) {
            return $this->targetAllocationCache[$userId];
        }
        $stmt = $this->pdo->prepare('SELECT target_allocations FROM user_settings_stocks WHERE user_id=?');
        $stmt->execute([$userId]);
        $json = $stmt->fetchColumn();
        if (!$json) {
            $this->targetAllocationCache[$userId] = [];
            return [];
        }
        $decoded = json_decode((string)$json, true);
        if (!is_array($decoded)) {
            $this->targetAllocationCache[$userId] = [];
            return [];
        }
        $clean = [];
        foreach ($decoded as $key => $value) {
            $clean[strtoupper($key)] = (float)$value;
        }
        $this->targetAllocationCache[$userId] = $clean;

        return $clean;
    }
}
