<?php

namespace App\Enums;

enum RescueTeamTypeEnum: string
{
    case FIRE = 'fire';
    case MEDICAL = 'medical';
    case MILITARY = 'military';
    case VOLUNTEER = 'volunteer';
    case POLICE = 'police';
    case CIVIL_DEFENSE = 'civil_defense';

    public function label(): string
    {
        return match ($this) {
            self::FIRE => 'PCCC',
            self::MEDICAL => 'Y tế',
            self::MILITARY => 'Quân đội',
            self::VOLUNTEER => 'Tình nguyện',
            self::POLICE => 'Công an',
            self::CIVIL_DEFENSE => 'Quốc phòng',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
