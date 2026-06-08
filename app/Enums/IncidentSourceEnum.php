<?php

namespace App\Enums;

enum IncidentSourceEnum: string
{
    case CITIZEN = 'citizen';
    case CAMERA = 'camera';
    case SENSOR = 'sensor';
    case OPERATOR = 'operator';
    case AI = 'ai';

    public function label(): string
    {
        return match ($this) {
            self::CITIZEN => 'Công dân',
            self::CAMERA => 'Camera',
            self::SENSOR => 'Cảm biến',
            self::OPERATOR => 'Điều hành viên',
            self::AI => 'Hệ thống AI',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
