<?php

namespace Database\Factories;

use App\Models\SpecAmendment;
use App\Models\SpecDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpecAmendment>
 */
class SpecAmendmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'spec_document_id' => SpecDocument::factory(),
            'instruction' => 'Add a note that the summer season deadline is firm.',
            'resulting_version' => 2,
        ];
    }
}
