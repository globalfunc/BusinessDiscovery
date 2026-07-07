<?php

namespace Database\Factories;

use App\Enums\BusinessOwnerStatus;
use App\Enums\PipelineStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BusinessOwner>
 */
class BusinessOwnerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'company' => $this->faker->company(),
            'logo_path' => null,
            'greeting_override' => null,
            'language' => null,
            'admin_context' => null,
            'pre_selected_niche_id' => null,
            'status' => BusinessOwnerStatus::Active,
            'current_stage' => PipelineStage::Prospect,
        ];
    }
}
