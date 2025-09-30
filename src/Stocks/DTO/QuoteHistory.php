<?php

declare(strict_types=1);

namespace MyMoneyMap\Stocks\DTO;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;

final class QuoteHistory implements \IteratorAggregate
{
    /**
     * @param list<QuoteHistoryPoint> $points
     * @param DateTimeImmutable $from
     * @param DateTimeImmutable $to
     */
    public function __construct(
        public readonly array $points,
        public readonly DateTimeImmutable $from,
        public readonly DateTimeImmutable $to,
    ) {
    }

    /**
     * @return \Traversable<int, QuoteHistoryPoint>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->points);
    }

    public function isEmpty(): bool
    {
        return $this->points === [];
    }

    public function fillGaps(): QuoteHistory
    {
        if ($this->isEmpty()) {
            return $this;
        }

        $map = [];
        foreach ($this->points as $point) {
            $map[$point->date->format('Y-m-d')] = $point;
        }

        $filled = [];
        $period = new DatePeriod($this->from, new DateInterval('P1D'), $this->to->modify('+1 day'));
        $last = null;
        foreach ($period as $day) {
            $key = $day->format('Y-m-d');
            if (isset($map[$key])) {
                $filled[] = $map[$key];
                $last = $map[$key];
                continue;
            }

            if ($last) {
                $filled[] = new QuoteHistoryPoint($day, $last->open, $last->high, $last->low, $last->close, $last->volume, true);
                continue;
            }
        }

        return new QuoteHistory($filled, $this->from, $this->to);
    }
}
