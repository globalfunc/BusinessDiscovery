<?php

namespace Database\Factories;

use App\Models\DiscoverySession;
use App\Models\SpecDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpecDocument>
 */
class SpecDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'discovery_session_id' => DiscoverySession::factory(),
            'version' => 1,
            'markdown' => "## 1. Business overview & context\n\n- Company: Test Barbershop\n\n## 2. Goals, pains & strengths\n\n- Reduce no-shows",
            'generated_by' => 'ai',
            'change_summary' => null,
            'model_meta' => [
                'model' => config('ai.default_model'),
                'prompt_version' => 1,
            ],
        ];
    }

    /** The §7.7 deterministic renderer output — no AI involved. */
    public function fallback(): static
    {
        return $this->state(fn () => ['generated_by' => 'fallback', 'model_meta' => ['status' => 'fallback']]);
    }
}
