<?php

declare(strict_types=1);

namespace MyMoneyMap\Stocks\DTO;

use DateTimeImmutable;

final class PositionValuePoint
{
    public function __construct(
        public readonly DateTimeImmutable $date,
        public readonly float $valueBase,
    ) {
    }
}
