<?php

namespace Database\Factories;

use App\Models\VendorBlocklistTerm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorBlocklistTerm>
 */
class VendorBlocklistTermFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'term' => $this->faker->unique()->company(),
            'is_regex' => false,
            'replacement' => null,
            'active' => true,
            'category' => 'test',
        ];
    }

    public function regex(): static
    {
        return $this->state(fn () => ['is_regex' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
