<?php

namespace Database\Factories;

use App\Enums\ReferralTokenState;
use App\Models\BusinessOwner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReferralToken>
 */
class ReferralTokenFactory extends Factory
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
            'token_hash' => hash('sha256', Str::random(40)),
            'expires_at' => now()->addDays(14),
            'state' => ReferralTokenState::Created,
            'sent_at' => null,
            'first_visited_at' => null,
            'revoked_at' => null,
        ];
    }
}
