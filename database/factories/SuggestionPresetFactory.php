<?php

namespace Database\Factories;

use App\Enums\DiscoveryPhase;
use App\Models\SuggestionPreset;
use App\Models\TaxonomyNiche;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SuggestionPreset>
 */
class SuggestionPresetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'taxonomy_niche_id' => TaxonomyNiche::factory(),
            'phase' => DiscoveryPhase::Phase2->value,
            'cards' => [
                [
                    'title' => 'Online Booking',
                    'summary' => 'Let clients book 24/7.',
                    'features' => ['Automatic reminders', 'Waitlist auto-fill', 'Deposit on booking'],
                    'rationale' => 'A common starting point for this kind of business.',
                    'tags' => ['retention', 'time_saving'],
                    'saas_eligible' => true,
                    'related_catalog_key' => 'online_booking',
                ],
            ],
        ];
    }
}
