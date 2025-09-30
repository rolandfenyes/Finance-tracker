<?php

declare(strict_types=1);

namespace MyMoneyMap\Stocks\Services;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use MyMoneyMap\Stocks\Repositories\TradeRepository;
use PDO;

use function fx_convert;
use function fx_user_main;

final class ChartsService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly TradeRepository $trades,
        private readonly PriceDataService $prices,
    ) {
    }

    /**
     * @return array{labels: list<string>, values: list<float>}
     */
    public function portfolioValueSeries(int $userId, string $range = '3M'): array
    {
        $baseCurrency = fx_user_main($this->pdo, $userId);
        [$from, $to] = $this->rangeToDates($range);
        $holdings = $this->trades->holdingsRaw($userId);
        $dates = $this->buildDateRange($from, $to);

        $priceCache = [];
        foreach ($holdings as $row) {
            $stockId = (int) $row['stock_id'];
            $symbol = (string) $row['symbol'];
            $history = $this->prices->getDailyHistory($stockId, $symbol, $from, $to);
            $map = [];
            foreach ($history as $point) {
                $map[$point->date->format('Y-m-d')] = $point->close;
            }
            $priceCache[$stockId] = $map;
        }

        $labels = [];
        $values = [];
        $totals = [];
        foreach ($dates as $date) {
            $dateKey = $date->format('Y-m-d');
            $labels[] = $date->format('M j');
            $dailyTotal = 0.0;
            foreach ($holdings as $row) {
                $qty = (float) $row['qty'];
                if ($qty <= 0) {
                    continue;
                }
                $stockId = (int) $row['stock_id'];
                $currency = strtoupper((string) $row['currency']);
                $pricesMap = $priceCache[$stockId] ?? [];
                $price = $pricesMap[$dateKey] ?? $this->nearestPrice($pricesMap, $dateKey);
                if ($price === null) {
                    continue;
                }
                $dailyTotal += fx_convert($this->pdo, $qty * $price, $currency, $baseCurrency, $dateKey);
            }
            $values[] = $dailyTotal;
            $totals[$dateKey] = $dailyTotal;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @return array{labels: list<string>, values: list<float>}
     */
    public function stockPriceSeries(int $stockId, string $symbol, string $range = '6M'): array
    {
        [$from, $to] = $this->rangeToDates($range);
        $history = $this->prices->getDailyHistory($stockId, $symbol, $from, $to);
        $labels = [];
        $values = [];
        foreach ($history as $point) {
            $labels[] = $point->date->format('M j');
            $values[] = $point->close;
        }
        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @return array{labels: list<string>, values: list<float>}
     */
    public function positionValueSeries(int $userId, int $stockId, string $symbol, string $currency, string $range = '6M'): array
    {
        $baseCurrency = fx_user_main($this->pdo, $userId);
        [$from, $to] = $this->rangeToDates($range);
        $history = $this->prices->getDailyHistory($stockId, $symbol, $from, $to);
        $priceMap = [];
        foreach ($history as $point) {
            $priceMap[$point->date->format('Y-m-d')] = $point->close;
        }

        $trades = $this->trades->tradesForStock($userId, $stockId);
        usort($trades, static function (array $a, array $b): int {
            return strcmp((string) $a['executed_at'], (string) $b['executed_at']);
        });

        $dates = $this->buildDateRange($from, $to);
        $labels = [];
        $values = [];
        $quantity = 0.0;
        $index = 0;
        $count = count($trades);

        foreach ($dates as $date) {
            while ($index < $count && new DateTimeImmutable((string) $trades[$index]['executed_at']) <= $date) {
                $side = strtoupper((string) $trades[$index]['side']);
                $qty = (float) $trades[$index]['qty'];
                if ($side === 'BUY') {
                    $quantity += $qty;
                } elseif ($side === 'SELL') {
                    $quantity -= $qty;
                }
                $index++;
            }

            $dateKey = $date->format('Y-m-d');
            $price = $priceMap[$dateKey] ?? $this->nearestPrice($priceMap, $dateKey);
            if ($price === null) {
                continue;
            }
            $valueBase = fx_convert($this->pdo, $quantity * $price, $currency, $baseCurrency, $dateKey);
            $labels[] = $date->format('M j');
            $values[] = $valueBase;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @return list<DateTimeImmutable>
     */
    private function buildDateRange(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $period = new DatePeriod($from, new DateInterval('P1D'), $to->modify('+1 day'));
        $dates = [];
        foreach ($period as $day) {
            $dates[] = DateTimeImmutable::createFromInterface($day);
        }
        return $dates;
    }

    /**
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    private function rangeToDates(string $range): array
    {
        $range = strtoupper($range);
        $to = new DateTimeImmutable();
        return match ($range) {
            '1W' => [$to->sub(new DateInterval('P7D')), $to],
            '1M' => [$to->sub(new DateInterval('P1M')), $to],
            '3M' => [$to->sub(new DateInterval('P3M')), $to],
            '6M' => [$to->sub(new DateInterval('P6M')), $to],
            '1Y' => [$to->sub(new DateInterval('P1Y')), $to],
            '5Y' => [$to->sub(new DateInterval('P5Y')), $to],
            default => [$to->sub(new DateInterval('P3M')), $to],
        };
    }

    /**
     * @param array<string, float> $prices
     */
    private function nearestPrice(array $prices, string $dateKey): ?float
    {
        if (isset($prices[$dateKey])) {
            return $prices[$dateKey];
        }
        $keys = array_keys($prices);
        sort($keys);
        $candidate = null;
        foreach (array_reverse($keys) as $key) {
            if ($key <= $dateKey) {
                $candidate = $prices[$key];
                break;
            }
        }
        if ($candidate !== null) {
            return $candidate;
        }
        foreach ($keys as $key) {
            if ($key >= $dateKey) {
                return $prices[$key];
            }
        }
        return null;
    }
}
