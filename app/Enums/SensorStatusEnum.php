<?php

namespace App\Enums;

enum SensorStatusEnum: string
{
    case ONLINE = 'online';
    case OFFLINE = 'offline';
    case MAINTENANCE = 'maintenance';
    case ERROR = 'error';

    public function label(): string
    {
        return match ($this) {
            self::ONLINE => 'Hoạt động',
            self::OFFLINE => 'Ngoại tuyến',
            self::MAINTENANCE => 'Bảo trì',
            self::ERROR => 'Lỗi',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ONLINE => '#17b26a',
            self::OFFLINE => '#667085',
            self::MAINTENANCE => '#f79009',
            self::ERROR => '#f04438',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
