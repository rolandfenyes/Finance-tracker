<?php

declare(strict_types=1);

namespace MyMoneyMap\Stocks\DTO;

final class Insight
{
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly string $severity,
    ) {
    }
}
