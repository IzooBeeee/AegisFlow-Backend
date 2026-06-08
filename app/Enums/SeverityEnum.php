<?php

namespace App\Enums;

enum SeverityEnum: string
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
            self::CRITICAL => 'Nghiêm trọng',
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

    public function weight(): int
    {
        return match ($this) {
            self::LOW => 1,
            self::MEDIUM => 2,
            self::HIGH => 3,
            self::CRITICAL => 4,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
