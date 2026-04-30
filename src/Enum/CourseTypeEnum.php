<?php

namespace App\Enum;

enum CourseTypeEnum: int
{
    case RENT = 1;
    case BUY = 2;
    case FREE = 3;

    public function code(): string
    {
        return match ($this) {
            self::RENT => 'rent',
            self::BUY => 'buy',
            self::FREE => 'free',
        };
    }
}
