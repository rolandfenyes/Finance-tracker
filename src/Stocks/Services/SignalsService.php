<?php

declare(strict_types=1);

namespace MyMoneyMap\Stocks\Services;

use DateInterval;
use DateTimeImmutable;
use MyMoneyMap\Stocks\DTO\Holding;
use MyMoneyMap\Stocks\DTO\Insight;
use MyMoneyMap\Stocks\DTO\PortfolioSnapshot;
use MyMoneyMap\Stocks\DTO\QuoteHistoryPoint;
use MyMoneyMap\Stocks\Repositories\SettingsRepository;
use PDO;

final class SignalsService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly PriceDataService $prices,
        private readonly SettingsRepository $settings,
    ) {
    }

    /**
     * @return list<Insight>
     */
    public function portfolioInsights(int $userId, PortfolioSnapshot $snapshot): array
    {
        $insights = [];
        $total = $snapshot->totalMarketValue;
        $settings = $this->settings->userSettings($userId);
        $targetAlloc = $this->decodeTargetAllocations($settings['target_allocations'] ?? null);

        foreach ($snapshot->holdings as $holding) {
            if ($holding->concentrationWarning) {
                $insights[] = new Insight(
                    sprintf('Concentration: %s', $holding->symbol),
                    sprintf('%s is %.1f%% of equity — consider trimming or diversifying.', $holding->symbol, $holding->weight),
                    'warning'
                );
            }
            $target = $targetAlloc[$holding->symbol] ?? null;
            if ($target !== null && $holding->weight > $target + 5.0) {
                $insights[] = new Insight(
                    sprintf('Over target: %s', $holding->symbol),
                    sprintf('Current weight %.1f%% exceeds target %.1f%%.', $holding->weight, $target),
                    'info'
                );
            }
        }

        if ($total > 0 && abs($snapshot->dailyPL / max($total, 1)) > 0.02) {
            $insights[] = new Insight(
                'Elevated daily P/L',
                sprintf('Daily change %.2f %s (%.2f%%). Consider reviewing exposures.', $snapshot->dailyPL, $snapshot->baseCurrency, ($snapshot->dailyPL / $total) * 100),
                'info'
            );
        }

        return $insights;
    }

    /**
     * @return list<Insight>
     */
    public function positionInsights(int $userId, Holding $holding): array
    {
        $settings = $this->settings->userSettings($userId);
        $targetAlloc = $this->decodeTargetAllocations($settings['target_allocations'] ?? null);
        $targetWeight = $targetAlloc[$holding->symbol] ?? null;

        $today = new DateTimeImmutable();
        $from = $today->sub(new DateInterval('P120D'));
        $history = $this->prices->getDailyHistory($holding->stockId, $holding->symbol, $from, $today);
        $closes = array_map(static fn (QuoteHistoryPoint $point): float => $point->close, iterator_to_array($history));
        $indicators = $this->computeIndicators($closes);

        $insights = [];

        if ($indicators['sma20'] !== null && $indicators['sma50'] !== null) {
            $trendUp = $holding->lastPrice > $indicators['sma50'];
            $momentumText = $trendUp ? 'above' : 'below';
            $insights[] = new Insight(
                'Momentum',
                sprintf('Price is %s the 50D SMA (%.2f vs %.2f).', $momentumText, $holding->lastPrice, $indicators['sma50']),
                $trendUp ? 'success' : 'warning'
            );
            if ($trendUp && $targetWeight !== null && $holding->weight < $targetWeight - 2.0) {
                $insights[] = new Insight(
                    'Potential add',
                    sprintf('Trend is up and weight %.1f%% is below target %.1f%%.', $holding->weight, $targetWeight),
                    'info'
                );
            }
        }

        if ($targetWeight !== null && $holding->weight > $targetWeight + 3.0) {
            $insights[] = new Insight(
                'Trim candidate',
                sprintf('Weight %.1f%% exceeds target %.1f%% — consider partial trim.', $holding->weight, $targetWeight),
                'warning'
            );
        }

        if ($indicators['rsi'] !== null) {
            if ($indicators['rsi'] > 70) {
                $insights[] = new Insight(
                    'Overbought',
                    sprintf('RSI(14) %.1f > 70; consider tightening stops or trimming.', $indicators['rsi']),
                    'warning'
                );
            } elseif ($indicators['rsi'] < 35) {
                $insights[] = new Insight(
                    'Oversold watch',
                    sprintf('RSI(14) %.1f — monitor for reversal signals.', $indicators['rsi']),
                    'info'
                );
            }
        }

        if ($indicators['swingHigh']) {
            $insights[] = new Insight(
                'Consider stop update',
                'New swing high detected — review stop levels to lock gains.',
                'info'
            );
        } elseif ($indicators['swingLow']) {
            $insights[] = new Insight(
                'Watch only',
                'Recent swing low suggests caution; wait for confirmation before adding.',
                'warning'
            );
        }

        $distance = $holding->averageCost > 0 ? (($holding->lastPrice - $holding->averageCost) / $holding->averageCost) * 100.0 : 0.0;
        $insights[] = new Insight(
            'Position health',
            sprintf('Current price is %.1f%% %s average cost.', abs($distance), $distance >= 0 ? 'above' : 'below'),
            $distance >= 0 ? 'success' : 'warning'
        );

        return $insights;
    }

    /**
     * @return array{rsi: ?float, sma20: ?float, sma50: ?float, swingHigh: bool, swingLow: bool}
     */
    private function computeIndicators(array $closes): array
    {
        $count = count($closes);
        $sma20 = $count >= 20 ? array_sum(array_slice($closes, -20)) / 20.0 : null;
        $sma50 = $count >= 50 ? array_sum(array_slice($closes, -50)) / 50.0 : null;
        $rsi = $this->calculateRsi($closes, 14);

        $recent = $count >= 20 ? array_slice($closes, -20) : $closes;
        $last = $closes !== [] ? $closes[$count - 1] : null;
        $swingHigh = $last !== null && $recent !== [] && $last >= max($recent);
        $swingLow = $last !== null && $recent !== [] && $last <= min($recent);

        return [
            'rsi' => $rsi,
            'sma20' => $sma20,
            'sma50' => $sma50,
            'swingHigh' => $swingHigh,
            'swingLow' => $swingLow,
        ];
    }

    private function calculateRsi(array $closes, int $period): ?float
    {
        $count = count($closes);
        if ($count <= $period) {
            return null;
        }
        $gains = 0.0;
        $losses = 0.0;
        for ($i = $count - $period; $i < $count; $i++) {
            if ($i === 0) {
                continue;
            }
            $change = $closes[$i] - $closes[$i - 1];
            if ($change >= 0) {
                $gains += $change;
            } else {
                $losses += abs($change);
            }
        }
        if ($losses == 0.0) {
            return 100.0;
        }
        $rs = ($gains / $period) / ($losses / $period);
        return 100.0 - (100.0 / (1.0 + $rs));
    }

    /**
     * @return array<string, float>
     */
    private function decodeTargetAllocations(?string $json): array
    {
        if (!$json) {
            return [];
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }
        $map = [];
        foreach ($decoded as $symbol => $weight) {
            if (!is_numeric($weight)) {
                continue;
            }
            $map[strtoupper((string) $symbol)] = (float) $weight;
        }
        return $map;
    }
}
