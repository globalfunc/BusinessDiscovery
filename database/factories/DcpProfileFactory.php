<?php

namespace Database\Factories;

use App\Models\DcpProfile;
use App\Models\DiscoverySession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DcpProfile>
 */
class DcpProfileFactory extends Factory
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
            'payload' => [
                'detected_niche' => [
                    'category' => 'personal_care',
                    'niche' => 'barbershop',
                    'niche_id' => null,
                    'confidence' => 0.86,
                ],
                'pain_points' => [
                    ['id' => 'no_shows', 'label' => 'Missed appointments', 'evidence' => 'clients forget their slots'],
                ],
                'goals' => [
                    ['id' => 'online_booking', 'label' => 'Let clients book online'],
                ],
                'strengths' => ['loyal repeat clients', 'prime location'],
                'digital_maturity' => 'low',
                'priority_signals' => ['retention', 'time_saving'],
                'tone_hints' => ['language' => 'bg', 'formality' => 'casual'],
                'summary' => 'A neighborhood barbershop with loyal clients, losing time to no-shows and manual scheduling.',
            ],
            'version' => 1,
            'model_meta' => [
                'model' => config('ai.default_model'),
                'prompt_version' => 1,
            ],
        ];
    }

    /** The graceful-failure state: AI ran but produced nothing usable. */
    public function empty(): static
    {
        return $this->state(fn () => ['payload' => []]);
    }
}
