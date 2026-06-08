<?php

namespace App\Enums;

enum RecommendationStatusEnum: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case EXECUTED = 'executed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Chờ duyệt',
            self::APPROVED => 'Đã duyệt',
            self::REJECTED => 'Từ chối',
            self::EXECUTED => 'Đã thực hiện',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
