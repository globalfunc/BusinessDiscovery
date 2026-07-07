<?php

namespace Database\Factories;

use App\Models\TaxonomyCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TaxonomyNiche>
 */
class TaxonomyNicheFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $en = $this->faker->words(2, true);

        return [
            'taxonomy_category_id' => TaxonomyCategory::factory(),
            'name' => ['en' => ucfirst($en), 'bg' => ucfirst($en)],
            'sort' => $this->faker->numberBetween(0, 100),
            'hidden' => false,
        ];
    }
}
