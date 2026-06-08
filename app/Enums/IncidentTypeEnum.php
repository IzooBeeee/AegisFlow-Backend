<?php

namespace App\Enums;

enum IncidentTypeEnum: string
{
    case FLOOD = 'flood';
    case HEAVY_RAIN = 'heavy_rain';
    case LANDSLIDE = 'landslide';
    case DAM_FAILURE = 'dam_failure';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::FLOOD => 'Ngập lụt',
            self::HEAVY_RAIN => 'Mưa lớn',
            self::LANDSLIDE => 'Sạt lở đất',
            self::DAM_FAILURE => 'Vỡ đập',
            self::OTHER => 'Khác',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
