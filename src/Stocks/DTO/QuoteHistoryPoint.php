<?php

declare(strict_types=1);

namespace MyMoneyMap\Stocks\DTO;

use DateTimeImmutable;

final class QuoteHistoryPoint
{
    public function __construct(
        public readonly DateTimeImmutable $date,
        public readonly float $open,
        public readonly float $high,
        public readonly float $low,
        public readonly float $close,
        public readonly float $volume,
        public readonly bool $synthetic = false,
    ) {
    }
}
