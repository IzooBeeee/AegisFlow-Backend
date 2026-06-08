<?php

namespace App\Enums;

enum NotificationTypeEnum: string
{
    case ALERT = 'alert';
    case UPDATE = 'update';
    case RESCUE = 'rescue';
    case SYSTEM = 'system';
    case EVACUATION = 'evacuation';

    public function label(): string
    {
        return match ($this) {
            self::ALERT => 'Cảnh báo',
            self::UPDATE => 'Cập nhật',
            self::RESCUE => 'Cứu hộ',
            self::SYSTEM => 'Hệ thống',
            self::EVACUATION => 'Sơ tán',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
