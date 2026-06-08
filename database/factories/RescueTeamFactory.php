<?php

namespace Database\Factories;

use App\Enums\RescueTeamStatusEnum;
use App\Enums\RescueTeamTypeEnum;
use App\Models\RescueTeam;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RescueTeam>
 */
class RescueTeamFactory extends Factory
{
    protected $model = RescueTeam::class;

    public function definition(): array
    {
        return [
            'name' => 'Đội cứu hộ '.$this->faker->unique()->numberBetween(100, 999),
            'code' => 'RT-'.$this->faker->unique()->bothify('###??'),
            'team_type' => $this->faker->randomElement(RescueTeamTypeEnum::values()),
            'district_id' => null,
            'specializations' => ['flood_response'],
            'vehicle_count' => $this->faker->numberBetween(1, 4),
            'personnel_count' => $this->faker->numberBetween(3, 12),
            'equipment' => ['boat', 'radio'],
            'phone' => $this->faker->phoneNumber(),
            'status' => RescueTeamStatusEnum::AVAILABLE->value,
            'current_latitude' => 16.0544,
            'current_longitude' => 108.2022,
            'last_location_update' => now(),
        ];
    }
}
