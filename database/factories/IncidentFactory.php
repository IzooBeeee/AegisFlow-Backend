<?php

namespace Database\Factories;

use App\Enums\IncidentSourceEnum;
use App\Enums\IncidentStatusEnum;
use App\Enums\IncidentTypeEnum;
use App\Enums\SeverityEnum;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    protected $model = Incident::class;

    public function definition(): array
    {
        $types = IncidentTypeEnum::values();
        $severities = SeverityEnum::values();

        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(2),
            'type' => fake()->randomElement($types),
            'severity' => fake()->randomElement($severities),
            'status' => IncidentStatusEnum::REPORTED->value,
            'source' => fake()->randomElement(IncidentSourceEnum::values()),
            'address' => fake()->address(),
            'district_id' => null,
            'water_level_m' => fake()->randomFloat(2, 0, 5),
            'reported_by' => User::factory(),
        ];
    }

    public function critical(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => SeverityEnum::CRITICAL->value,
            'water_level_m' => fake()->randomFloat(2, 3, 6),
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => IncidentStatusEnum::RESOLVED->value,
            'resolved_at' => now(),
        ]);
    }
}
