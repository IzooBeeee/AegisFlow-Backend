<?php

namespace App\Enums;

enum SensorTypeEnum: string
{
    case WATER_LEVEL = 'water_level';
    case RAINFALL = 'rainfall';
    case CAMERA = 'camera';
    case WIND = 'wind';
    case TEMPERATURE = 'temperature';
    case HUMIDITY = 'humidity';
    case COMBINED = 'combined';

    public function label(): string
    {
        return match ($this) {
            self::WATER_LEVEL => 'Mực nước',
            self::RAINFALL => 'Lượng mưa',
            self::CAMERA => 'Camera',
            self::WIND => 'Gió',
            self::TEMPERATURE => 'Nhiệt độ',
            self::HUMIDITY => 'Độ ẩm',
            self::COMBINED => 'Đa cảm biến',
        };
    }

    public function unit(): string
    {
        return match ($this) {
            self::WATER_LEVEL => 'm',
            self::RAINFALL => 'mm',
            self::WIND => 'km/h',
            self::TEMPERATURE => '°C',
            self::HUMIDITY => '%',
            self::CAMERA, self::COMBINED => '',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
