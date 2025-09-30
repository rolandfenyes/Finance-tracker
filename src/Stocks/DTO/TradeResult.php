<?php

declare(strict_types=1);

namespace MyMoneyMap\Stocks\DTO;

final class TradeResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?int $tradeId = null,
        public readonly ?string $message = null,
    ) {
    }
}
