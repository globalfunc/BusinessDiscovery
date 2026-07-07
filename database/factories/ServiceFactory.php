<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Service>
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
            'key' => \Illuminate\Support\Str::slug($en),
            'name' => ['en' => ucfirst($en), 'bg' => ucfirst($en)],
            'one_liner' => ['en' => $this->faker->sentence(), 'bg' => $this->faker->sentence()],
            'base_features' => $this->faker->words(5),
            'saas_eligible' => $this->faker->boolean(),
            'tags' => $this->faker->words(3),
            'hidden' => false,
        ];
    }
}
