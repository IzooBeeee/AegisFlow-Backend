<?php

namespace App\Enums;

enum ShelterStatusEnum: string
{
    case OPEN = 'open';
    case FULL = 'full';
    case CLOSED = 'closed';
    case PREPARING = 'preparing';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Mở cửa',
            self::FULL => 'Đã đầy',
            self::CLOSED => 'Đóng cửa',
            self::PREPARING => 'Đang chuẩn bị',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
