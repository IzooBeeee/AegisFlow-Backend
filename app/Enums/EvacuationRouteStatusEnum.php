<?php

namespace App\Enums;

enum EvacuationRouteStatusEnum: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case BLOCKED = 'blocked';
    case FLOODED = 'flooded';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Hoạt động',
            self::INACTIVE => 'Không hoạt động',
            self::BLOCKED => 'Bị chặn',
            self::FLOODED => 'Ngập nước',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => '#17b26a',
            self::INACTIVE => '#667085',
            self::BLOCKED => '#f79009',
            self::FLOODED => '#f04438',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
