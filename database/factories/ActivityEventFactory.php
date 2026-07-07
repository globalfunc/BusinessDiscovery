<?php

namespace Database\Factories;

use App\Models\BusinessOwner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ActivityEvent>
 */
class ActivityEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_owner_id' => BusinessOwner::factory(),
            'type' => $this->faker->randomElement(['note', 'stage_change', 'login', 'upload']),
            'payload' => ['message' => $this->faker->sentence()],
        ];
    }
}
