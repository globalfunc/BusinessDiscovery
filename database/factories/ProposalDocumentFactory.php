<?php

namespace Database\Factories;

use App\Models\BusinessOwner;
use App\Models\ProposalDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProposalDocument>
 */
class ProposalDocumentFactory extends Factory
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
            'markdown' => "## Your proposal\n\n- Online booking service\n\n## Budget & timeline\n\n- 3000-5000 EUR, 6 weeks",
            'generated_by' => 'ai',
            'upload_id' => null,
            'attachments' => null,
            'model_meta' => [
                'model' => config('ai.default_model'),
                'prompt_version' => 1,
            ],
        ];
    }
}
