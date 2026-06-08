<?php

namespace App\Enums;

enum MapNodeTypeEnum: string
{
    case INTERSECTION = 'intersection';
    case ROUNDABOUT = 'roundabout';
    case BRIDGE = 'bridge';
    case HIGHWAY_ENTRY = 'highway_entry';
    case TERMINAL = 'terminal';
    case SHELTER = 'shelter';
    case BUILDING = 'building';

    public function label(): string
    {
        return match ($this) {
            self::INTERSECTION => 'Nút giao',
            self::ROUNDABOUT => 'Vòng xuyến',
            self::BRIDGE => 'Cầu',
            self::HIGHWAY_ENTRY => 'Đầu vào cao tốc',
            self::TERMINAL => 'Điểm cuối',
            self::SHELTER => 'Điểm trú ẩn',
            self::BUILDING => 'Tòa nhà',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
