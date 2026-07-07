<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $en = $this->faker->unique()->words(3, true);

        return [
            'key' => Str::slug($en),
            'name' => ['en' => ucfirst($en), 'bg' => ucfirst($en)],
            'one_liner' => ['en' => $this->faker->sentence(), 'bg' => $this->faker->sentence()],
            'base_features' => $this->faker->words(5),
            'saas_eligible' => $this->faker->boolean(),
            'tags' => $this->faker->words(3),
            'price_min' => $this->faker->numberBetween(100, 500),
            'price_max' => $this->faker->numberBetween(600, 2000),
            'hidden' => false,
        ];
    }
}
