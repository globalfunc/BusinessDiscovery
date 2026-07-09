<?php

namespace Tests\Feature;

use App\Models\PhaseCopyOverride;
use App\Models\SuggestionPreset;
use App\Models\TaxonomyNiche;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin CRUD for §6.6 suggestion presets and phase-copy overrides.
 */
class ContentManagementAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    private function cardPayload(): array
    {
        return [
            'title' => 'Online Booking',
            'summary' => 'Let clients book 24/7.',
            'features' => ['Automatic reminders', 'Waitlist auto-fill', 'Deposit on booking'],
            'rationale' => 'A common starting point for this kind of business.',
            'tags' => ['retention'],
            'saas_eligible' => true,
            'related_catalog_key' => 'online_booking',
        ];
    }

    public function test_content_index_includes_presets_and_phase_copy(): void
    {
        SuggestionPreset::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.content.index'))
            ->assertOk();
    }

    public function test_store_creates_a_suggestion_preset(): void
    {
        $niche = TaxonomyNiche::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.content.suggestion-presets.store'), [
                'taxonomy_niche_id' => $niche->id,
                'phase' => 'phase_2',
                'cards' => [$this->cardPayload()],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('suggestion_presets', [
            'taxonomy_niche_id' => $niche->id,
            'phase' => 'phase_2',
        ]);
    }

    public function test_store_rejects_fewer_than_three_features(): void
    {
        $niche = TaxonomyNiche::factory()->create();
        $card = $this->cardPayload();
        $card['features'] = ['Only one'];

        $this->actingAs($this->admin())
            ->post(route('admin.content.suggestion-presets.store'), [
                'taxonomy_niche_id' => $niche->id,
                'phase' => 'phase_2',
                'cards' => [$card],
            ])
            ->assertSessionHasErrors('cards.0.features');

        $this->assertDatabaseCount('suggestion_presets', 0);
    }

    public function test_update_changes_preset_cards(): void
    {
        $preset = SuggestionPreset::factory()->create();
        $card = $this->cardPayload();
        $card['title'] = 'Updated title';

        $this->actingAs($this->admin())
            ->patch(route('admin.content.suggestion-presets.update', $preset), [
                'cards' => [$card],
            ])
            ->assertRedirect();

        $this->assertSame('Updated title', $preset->fresh()->cards[0]['title']);
    }

    public function test_destroy_removes_a_preset(): void
    {
        $preset = SuggestionPreset::factory()->create();

        $this->actingAs($this->admin())
            ->delete(route('admin.content.suggestion-presets.destroy', $preset))
            ->assertRedirect();

        $this->assertDatabaseMissing('suggestion_presets', ['id' => $preset->id]);
    }

    public function test_phase_copy_update_creates_an_override(): void
    {
        $this->actingAs($this->admin())
            ->patch(route('admin.content.phase-copy.update', ['phase_1', 'bg']), [
                'title' => 'Разкажи ни за бизнеса',
                'helper' => 'Кратък въпросник.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('phase_copy_overrides', [
            'phase' => 'phase_1',
            'language' => 'bg',
            'title' => 'Разкажи ни за бизнеса',
        ]);
    }

    public function test_phase_copy_update_with_all_blank_fields_clears_the_override(): void
    {
        PhaseCopyOverride::factory()->create(['phase' => 'phase_1', 'language' => 'en']);

        $this->actingAs($this->admin())
            ->patch(route('admin.content.phase-copy.update', ['phase_1', 'en']), [
                'title' => '',
                'helper' => '',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('phase_copy_overrides', ['phase' => 'phase_1', 'language' => 'en']);
    }

    public function test_phase_copy_update_rejects_an_unknown_phase(): void
    {
        $this->actingAs($this->admin())
            ->patch(route('admin.content.phase-copy.update', ['not_a_phase', 'en']), ['title' => 'x'])
            ->assertNotFound();
    }

    public function test_guest_cannot_manage_content(): void
    {
        $niche = TaxonomyNiche::factory()->create();

        $this->post(route('admin.content.suggestion-presets.store'), [
            'taxonomy_niche_id' => $niche->id,
            'phase' => 'phase_2',
            'cards' => [$this->cardPayload()],
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('suggestion_presets', 0);
    }
}
