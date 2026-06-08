<?php

namespace App\Enums;

enum IncidentStatusEnum: string
{
    case REPORTED = 'reported';
    case VERIFIED = 'verified';
    case RESPONDING = 'responding';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::REPORTED => 'Đã báo cáo',
            self::VERIFIED => 'Đã xác minh',
            self::RESPONDING => 'Đang xử lý',
            self::RESOLVED => 'Đã xử lý',
            self::CLOSED => 'Đã đóng',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
