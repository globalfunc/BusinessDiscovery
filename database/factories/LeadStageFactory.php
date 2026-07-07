<?php

namespace Database\Factories;

use App\Enums\PipelineStage;
use App\Models\BusinessOwner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LeadStage>
 */
class LeadStageFactory extends Factory
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
            'stage' => PipelineStage::Prospect,
            'note' => null,
            'changed_by' => null,
            'changed_at' => now(),
        ];
    }
}
