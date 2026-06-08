<?php

namespace Database\Factories;

use App\Enums\UrgencyEnum;
use App\Enums\RescueCategoryEnum;
use App\Enums\RescueRequestStatusEnum;
use App\Models\RescueRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RescueRequest>
 */
class RescueRequestFactory extends Factory
{
    protected $model = RescueRequest::class;

    public function definition(): array
    {
        return [
            'caller_name' => fake()->name(),
            'caller_phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'district_id' => null,
            'urgency' => fake()->randomElement(UrgencyEnum::values()),
            'category' => fake()->randomElement(RescueCategoryEnum::values()),
            'people_count' => fake()->numberBetween(1, 20),
            'vulnerable_groups' => fake()->randomElements(
                ['children', 'elderly', 'disabled', 'pregnant'],
                fake()->numberBetween(0, 2)
            ),
            'description' => fake()->paragraph(),
            'status' => RescueRequestStatusEnum::PENDING->value,
            'reported_by' => User::factory(),
        ];
    }

    public function critical(): static
    {
        return $this->state(fn (array $attributes) => [
            'urgency' => UrgencyEnum::CRITICAL->value,
            'vulnerable_groups' => ['children', 'elderly'],
            'people_count' => fake()->numberBetween(5, 20),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RescueRequestStatusEnum::COMPLETED->value,
            'completed_at' => now(),
        ]);
    }
}
