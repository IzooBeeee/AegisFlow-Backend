<?php

namespace App\Enums;

enum RescueCategoryEnum: string
{
    case MEDICAL = 'medical';
    case FOOD = 'food';
    case WATER = 'water';
    case RESCUE = 'rescue';
    case EVACUATION = 'evacuation';
    case SHELTER = 'shelter';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::MEDICAL => 'Y tế',
            self::FOOD => 'Lương thực',
            self::WATER => 'Nước uống',
            self::RESCUE => 'Cứu hộ',
            self::EVACUATION => 'Sơ tán',
            self::SHELTER => 'Nơi trú ẩn',
            self::OTHER => 'Khác',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
