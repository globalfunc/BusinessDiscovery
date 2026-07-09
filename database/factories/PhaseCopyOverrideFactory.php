<?php

namespace Database\Factories;

use App\Enums\DiscoveryPhase;
use App\Models\PhaseCopyOverride;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PhaseCopyOverride>
 */
class PhaseCopyOverrideFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phase' => DiscoveryPhase::Phase1->value,
            'language' => 'en',
            'title' => $this->faker->sentence(3),
            'helper' => $this->faker->sentence(8),
            'body' => null,
        ];
    }
}
