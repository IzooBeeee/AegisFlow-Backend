<?php

namespace Database\Factories;

use App\Models\FloodZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FloodZone>
 */
class FloodZoneFactory extends Factory
{
    protected $model = FloodZone::class;

    public function definition(): array
    {
        return [
            'name' => 'Vùng ngập '.fake()->numberBetween(1, 100),
            'slug' => 'zone-'.fake()->unique()->slug(2),
            'description' => fake()->sentence(),
            'district_id' => fake()->numberBetween(1, 7),
            'risk_level' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'status' => 'monitoring',
            'current_water_level_m' => fake()->randomFloat(2, 0, 4),
            'alert_threshold_m' => 1.5,
            'danger_threshold_m' => 3.0,
            'color' => fake()->hexColor(),
            'opacity' => fake()->randomFloat(2, 0.2, 0.5),
            'is_active' => true,
        ];
    }

    public function flooded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'flooded',
            'current_water_level_m' => fake()->randomFloat(2, 3, 6),
            'risk_level' => 'critical',
        ]);
    }

    public function alert(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'alert',
            'current_water_level_m' => fake()->randomFloat(2, 1.5, 2.9),
            'risk_level' => 'high',
        ]);
    }
}
