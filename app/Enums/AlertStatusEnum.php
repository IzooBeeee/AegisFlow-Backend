<?php

namespace App\Enums;

enum AlertStatusEnum: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case UPDATED = 'updated';
    case RESOLVED = 'resolved';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Nháp',
            self::ACTIVE => 'Đang hoạt động',
            self::UPDATED => 'Cập nhật',
            self::RESOLVED => 'Đã giải quyết',
            self::EXPIRED => 'Hết hạn',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
