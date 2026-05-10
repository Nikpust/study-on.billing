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

    public static function codes(): array
    {
        return array_map(
            static fn (self $type): string => $type->code(),
            self::cases()
        );
    }

    public static function fromCode(string $code): ?self
    {
        return match ($code) {
            'rent' => self::RENT,
            'buy' => self::BUY,
            'free' => self::FREE,
            default => null,
        };
    }
}
