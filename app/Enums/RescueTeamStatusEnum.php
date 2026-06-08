<?php

namespace App\Enums;

enum RescueTeamStatusEnum: string
{
    case AVAILABLE = 'available';
    case DISPATCHED = 'dispatched';
    case BUSY = 'busy';
    case OFFLINE = 'offline';

    public function label(): string
    {
        return match ($this) {
            self::AVAILABLE => 'Sẵn sàng',
            self::DISPATCHED => 'Đang di chuyển',
            self::BUSY => 'Đang làm nhiệm vụ',
            self::OFFLINE => 'Ngoại tuyến',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::AVAILABLE => '#17b26a',
            self::DISPATCHED => '#f79009',
            self::BUSY => '#f04438',
            self::OFFLINE => '#667085',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
