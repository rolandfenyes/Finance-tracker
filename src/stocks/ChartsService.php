<?php

namespace Stocks;

use DateInterval;
use DatePeriod;
use DateTime;
use PDO;

class ChartsService
{
    private PDO $pdo;
    private PriceDataService $priceDataService;

    public function __construct(PDO $pdo, PriceDataService $priceDataService)
    {
        $this->pdo = $pdo;
        $this->priceDataService = $priceDataService;
    }

    /**
     * @return array{labels:array<int,string>,series:array<int,float>}
     */
    public function portfolioValueSeries(int $userId, string $range = '1M'): array
    {
        $dates = $this->dateRangeFor($range);
        $stocks = $this->loadUserStocks($userId);
        if (empty($stocks)) {
            return ['labels' => [], 'series' => []];
        }
        require_once __DIR__ . '/../fx.php';
        $base = fx_user_main($this->pdo, $userId);
        $historyBySymbol = [];
        foreach ($stocks as $symbol => $info) {
            $historyBySymbol[$symbol] = $this->indexHistory($this->priceDataService->getDailyHistory($symbol, $dates['start'], $dates['end']));
        }

        $trades = $this->loadTrades($userId, $dates['end']);
        $positionQty = array_fill_keys(array_keys($stocks), 0.0);
        $series = [];
        $labels = [];
        $tradeIdx = 0;
        foreach ($this->iterateDates($dates['start'], $dates['end']) as $date) {
            while ($tradeIdx < count($trades) && $trades[$tradeIdx]['date'] <= $date) {
                $trade = $trades[$tradeIdx];
                $symbol = $trade['symbol'];
                $qty = (float)$trade['quantity'];
                if ($trade['side'] === 'buy') {
                    $positionQty[$symbol] += $qty;
                } else {
                    $positionQty[$symbol] -= $qty;
                }
                $tradeIdx++;
            }
            $portfolioValue = 0.0;
            foreach ($positionQty as $symbol => $qty) {
                if ($qty <= 0) {
                    continue;
                }
                $close = $historyBySymbol[$symbol][$date]['close'] ?? null;
                if ($close !== null) {
                    $currency = $stocks[$symbol]['currency'];
                    $portfolioValue += fx_convert($this->pdo, $qty * $close, $currency, $base, $date);
                }
            }
            $labels[] = $date;
            $series[] = $portfolioValue;
        }
        return ['labels' => $labels, 'series' => $series];
    }

    /**
     * @return array{labels:array<int,string>,series:array<int,float>}
     */
    public function positionValueSeries(int $userId, string $symbol, string $range = '6M'): array
    {
        $dates = $this->dateRangeFor($range);
        $history = $this->indexHistory($this->priceDataService->getDailyHistory($symbol, $dates['start'], $dates['end']));
        $trades = $this->loadTradesForSymbol($userId, $symbol, $dates['end']);
        $currency = $this->lookupCurrency($symbol);
        require_once __DIR__ . '/../fx.php';
        $base = fx_user_main($this->pdo, $userId);
        $qty = 0.0;
        $series = [];
        $labels = [];
        foreach ($this->iterateDates($dates['start'], $dates['end']) as $date) {
            if (isset($trades[$date])) {
                foreach ($trades[$date] as $trade) {
                    $qty += ($trade['side'] === 'buy') ? $trade['quantity'] : -$trade['quantity'];
                }
            }
            $close = $history[$date]['close'] ?? null;
            $labels[] = $date;
            $series[] = ($close !== null) ? fx_convert($this->pdo, $qty * $close, $currency, $base, $date) : null;
        }
        return ['labels' => $labels, 'series' => $series];
    }

    private function dateRangeFor(string $range): array
    {
        $end = new DateTime();
        $start = clone $end;
        switch (strtoupper($range)) {
            case '1D':
                $start->sub(new DateInterval('P1D'));
                break;
            case '5D':
                $start->sub(new DateInterval('P5D'));
                break;
            case '1M':
                $start->sub(new DateInterval('P1M'));
                break;
            case '6M':
                $start->sub(new DateInterval('P6M'));
                break;
            case '1Y':
                $start->sub(new DateInterval('P1Y'));
                break;
            case '5Y':
                $start->sub(new DateInterval('P5Y'));
                break;
            default:
                $start->sub(new DateInterval('P1M'));
        }
        return ['start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d')];
    }

    /**
     * @return array<string,array<string,float|null>>
     */
    private function indexHistory(array $history): array
    {
        $indexed = [];
        foreach ($history as $row) {
            $indexed[$row['date']] = $row;
        }
        return $indexed;
    }

    /**
     * @return array<string,int>
     */
    private function loadUserStocks(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT DISTINCT s.symbol, s.id, s.currency FROM stock_trades t JOIN stocks s ON s.id = t.stock_id WHERE t.user_id=? ORDER BY s.symbol');
        $stmt->execute([$userId]);
        $result = [];
        foreach ($stmt as $row) {
            $result[$row['symbol']] = ['id' => (int)$row['id'], 'currency' => $row['currency'] ?? 'USD'];
        }
        return $result;
    }

    /**
     * @return array<int,array{date:string,symbol:string,side:string,quantity:float}>
     */
    private function loadTrades(int $userId, string $endDate): array
    {
        $stmt = $this->pdo->prepare('SELECT executed_at::date AS trade_date, s.symbol, side, quantity FROM stock_trades t JOIN stocks s ON s.id = t.stock_id WHERE t.user_id=? AND executed_at::date <= ?::date ORDER BY executed_at ASC, t.id ASC');
        $stmt->execute([$userId, $endDate]);
        $rows = [];
        foreach ($stmt as $row) {
            $rows[] = [
                'date' => $row['trade_date'],
                'symbol' => $row['symbol'],
                'side' => $row['side'],
                'quantity' => (float)$row['quantity'],
            ];
        }
        return $rows;
    }

    /**
     * @return array<string,array<int,array{side:string,quantity:float}>> keyed by date
     */
    private function loadTradesForSymbol(int $userId, string $symbol, string $endDate): array
    {
        $stmt = $this->pdo->prepare('SELECT executed_at::date AS trade_date, side, quantity FROM stock_trades t JOIN stocks s ON s.id = t.stock_id WHERE t.user_id=? AND UPPER(s.symbol)=UPPER(?) AND executed_at::date <= ?::date ORDER BY executed_at ASC, t.id ASC');
        $stmt->execute([$userId, $symbol, $endDate]);
        $rows = [];
        foreach ($stmt as $row) {
            $date = $row['trade_date'];
            if (!isset($rows[$date])) {
                $rows[$date] = [];
            }
            $rows[$date][] = [
                'side' => $row['side'],
                'quantity' => (float)$row['quantity'],
            ];
        }
        return $rows;
    }

    /**
     * @return iterable<int,string>
     */
    private function iterateDates(string $start, string $end): iterable
    {
        $period = new DatePeriod(new DateTime($start), new DateInterval('P1D'), (new DateTime($end))->modify('+1 day'));
        foreach ($period as $date) {
            yield $date->format('Y-m-d');
        }
    }

    private function lookupCurrency(string $symbol): string
    {
        $stmt = $this->pdo->prepare('SELECT currency FROM stocks WHERE UPPER(symbol)=UPPER(?) LIMIT 1');
        $stmt->execute([$symbol]);
        $currency = $stmt->fetchColumn();
        return $currency ? (string)$currency : 'USD';
    }
}
