<?php

namespace App\Enums;

enum UrgencyEnum: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case CRITICAL = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::LOW => 'Thấp',
            self::MEDIUM => 'Trung bình',
            self::HIGH => 'Cao',
            self::CRITICAL => 'Khẩn cấp',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::LOW => '#17b26a',
            self::MEDIUM => '#f79009',
            self::HIGH => '#f04438',
            self::CRITICAL => '#920000',
        };
    }

    public function priorityWeight(): int
    {
        return match ($this) {
            self::CRITICAL => 4,
            self::HIGH => 3,
            self::MEDIUM => 2,
            self::LOW => 1,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
