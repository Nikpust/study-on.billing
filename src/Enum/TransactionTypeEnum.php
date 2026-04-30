<?php

namespace App\Enum;

enum TransactionTypeEnum: int
{
    case PAYMENT = 1;
    case DEPOSIT = 2;

    public function code(): string
    {
        return match ($this) {
            self::PAYMENT => 'payment',
            self::DEPOSIT => 'deposit',
        };
    }
}
