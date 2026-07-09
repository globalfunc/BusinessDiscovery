<?php

namespace Database\Factories;

use App\Models\AssessmentDocument;
use App\Models\BusinessOwner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentDocument>
 */
class AssessmentDocumentFactory extends Factory
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
            'version' => 1,
            'markdown' => "## Suggested tech stack\n\n- Laravel + React\n\n## Pricing bands\n\n- Build: 3000-5000 EUR",
            'generated_by' => 'ai',
            'model_meta' => [
                'model' => config('ai.default_model'),
                'prompt_version' => 1,
            ],
        ];
    }
}
