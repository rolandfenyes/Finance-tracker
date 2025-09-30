<?php

declare(strict_types=1);

namespace MyMoneyMap\Stocks\DTO;

final class AllocationSlice
{
    public function __construct(
        public readonly string $label,
        public readonly float $value,
        public readonly float $weight,
    ) {
    }
}
