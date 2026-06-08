<?php

namespace App\Enums;

enum RecommendationTypeEnum: string
{
    case REROUTE = 'reroute';
    case PRIORITY_ROUTE = 'priority_route';
    case ALERT = 'alert';
    case SIGNAL_CONTROL = 'signal_control';
    case EVACUATION = 'evacuation';
    case SUPPLY_DISPATCH = 'supply_dispatch';

    public function label(): string
    {
        return match ($this) {
            self::REROUTE => 'Đổi tuyến',
            self::PRIORITY_ROUTE => 'Tuyến ưu tiên',
            self::ALERT => 'Cảnh báo',
            self::SIGNAL_CONTROL => 'Điều khiển tín hiệu',
            self::EVACUATION => 'Sơ tán',
            self::SUPPLY_DISPATCH => 'Điều vật tư',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
