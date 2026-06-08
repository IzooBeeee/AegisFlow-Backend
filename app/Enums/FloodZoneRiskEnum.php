<?php

namespace App\Enums;

enum FloodZoneRiskEnum: string
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
            self::LOW => '#12b5cb',
            self::MEDIUM => '#f79009',
            self::HIGH => '#f04438',
            self::CRITICAL => '#920000',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
