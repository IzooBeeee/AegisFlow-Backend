<?php

namespace App\Enums;

enum PredictionStatusEnum: string
{
    case GENERATED = 'generated';
    case VERIFIED = 'verified';
    case ALERTED = 'alerted';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::GENERATED => 'Đã tạo',
            self::VERIFIED => 'Đã xác nhận',
            self::ALERTED => 'Đã cảnh báo',
            self::EXPIRED => 'Hết hạn',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
