<?php

declare(strict_types=1);

namespace MyMoneyMap\Stocks\DTO;

use DateTimeImmutable;

final class LiveQuote
{
    public function __construct(
        public readonly string $symbol,
        public readonly float $last,
        public readonly float $previousClose,
        public readonly float $dayHigh,
        public readonly float $dayLow,
        public readonly float $volume,
        public readonly DateTimeImmutable $asOf,
        public readonly bool $stale = false,
    ) {
    }

    public function change(): float
    {
        return $this->last - $this->previousClose;
    }

    public function percentChange(): float
    {
        if ($this->previousClose == 0.0) {
            return 0.0;
        }

        return ($this->change() / $this->previousClose) * 100.0;
    }
}
