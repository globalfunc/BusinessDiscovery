<?php

namespace Database\Factories;

use App\Enums\DiscoveryPhase;
use App\Models\BusinessOwner;
use App\Models\DiscoverySession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscoverySession>
 */
class DiscoverySessionFactory extends Factory
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
            'current_phase' => DiscoveryPhase::Phase0,
            'language' => 'en',
            'status' => 'in_progress',
            'started_at' => now(),
        ];
    }
}
