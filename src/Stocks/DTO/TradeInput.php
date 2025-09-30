<?php

declare(strict_types=1);

namespace MyMoneyMap\Stocks\DTO;

use DateTimeImmutable;

final class TradeInput
{
    public function __construct(
        public readonly int $userId,
        public readonly string $symbol,
        public readonly string $exchange,
        public readonly string $name,
        public readonly string $currency,
        public readonly string $side,
        public readonly float $quantity,
        public readonly float $price,
        public readonly float $fee,
        public readonly DateTimeImmutable $executedAt,
        public readonly ?string $note = null,
    ) {
    }
}
