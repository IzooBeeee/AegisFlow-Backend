<?php

namespace App\Enums;

enum FloodZoneStatusEnum: string
{
    case MONITORING = 'monitoring';
    case ALERT = 'alert';
    case DANGER = 'danger';
    case FLOODED = 'flooded';
    case RECEDED = 'receded';

    public function label(): string
    {
        return match ($this) {
            self::MONITORING => 'Đang theo dõi',
            self::ALERT => 'Cảnh báo',
            self::DANGER => 'Nguy hiểm',
            self::FLOODED => 'Ngập',
            self::RECEDED => 'Đã rút',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::MONITORING => '#17b26a',
            self::ALERT => '#f79009',
            self::DANGER => '#f04438',
            self::FLOODED => '#920000',
            self::RECEDED => '#12b5cb',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
