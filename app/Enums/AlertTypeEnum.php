<?php

namespace App\Enums;

enum AlertTypeEnum: string
{
    case FLOOD_WARNING = 'flood_warning';
    case HEAVY_RAIN = 'heavy_rain';
    case DAM_WARNING = 'dam_warning';
    case EVACUATION = 'evacuation';
    case ALL_CLEAR = 'all_clear';
    case WEATHER = 'weather';

    public function label(): string
    {
        return match ($this) {
            self::FLOOD_WARNING => 'Cảnh báo ngập',
            self::HEAVY_RAIN => 'Mưa lớn',
            self::DAM_WARNING => 'Cảnh báo đập',
            self::EVACUATION => 'Lệnh sơ tán',
            self::ALL_CLEAR => 'Dỡ cảnh báo',
            self::WEATHER => 'Thời tiết',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::FLOOD_WARNING => 'flood_warning',
            self::HEAVY_RAIN => 'rain',
            self::DAM_WARNING => 'warning',
            self::EVACUATION => 'evacuation',
            self::ALL_CLEAR => 'check_circle',
            self::WEATHER => 'cloud',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
