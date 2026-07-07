<?php

namespace Database\Factories;

use App\Enums\AiCallStatus;
use App\Models\AiCall;
use App\Models\BusinessOwner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiCall>
 */
class AiCallFactory extends Factory
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
            'tool' => $this->faker->randomElement(['dcp.generate', 'suggest.services', 'spec.compile']),
            'model' => config('ai.default_model'),
            'input_tokens' => $this->faker->numberBetween(200, 2000),
            'output_tokens' => $this->faker->numberBetween(50, 1000),
            'latency_ms' => $this->faker->numberBetween(400, 6000),
            'cost_estimate' => $this->faker->randomFloat(6, 0.001, 0.5),
            'status' => AiCallStatus::Success,
            'vendor_leak' => false,
        ];
    }
}
