<?php

namespace Database\Factories;

use App\Enums\EmailKind;
use App\Models\BusinessOwner;
use App\Models\EmailDraft;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailDraft>
 */
class EmailDraftFactory extends Factory
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
            'kind' => EmailKind::WarmTease,
            'language' => 'en',
            'subject' => 'A few ideas for your business',
            'body' => 'Hi — it was great meeting you. We put together a few ideas...',
            'model_meta' => [
                'model' => config('ai.default_model'),
                'prompt_version' => 1,
            ],
        ];
    }
}
