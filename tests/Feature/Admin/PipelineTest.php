<?php

namespace Tests\Feature\Admin;

use App\Enums\PipelineStage;
use App\Models\BusinessOwner;
use App\Models\LeadStage;
use App\Models\TaxonomyCategory;
use App\Models\TaxonomyNiche;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * S4.3 — Kanban pipeline: drag-and-drop stage persistence + history row,
 * category/niche/date filters, and stage-history exposed on the BO page.
 */
class PipelineTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    public function test_moving_a_card_updates_current_stage_and_writes_history_row(): void
    {
        $admin = $this->admin();
        $businessOwner = BusinessOwner::factory()->create(['current_stage' => PipelineStage::Prospect]);

        $response = $this->actingAs($admin)->patch(
            route('admin.pipeline.update-stage', $businessOwner),
            ['stage' => PipelineStage::ReferralSent->value, 'note' => 'Sent the referral link.'],
        );

        $response->assertRedirect();
        $this->assertSame(PipelineStage::ReferralSent, $businessOwner->fresh()->current_stage);

        $this->assertDatabaseHas('lead_stages', [
            'business_owner_id' => $businessOwner->id,
            'stage' => PipelineStage::ReferralSent->value,
            'note' => 'Sent the referral link.',
            'changed_by' => $admin->id,
        ]);
    }

    public function test_moving_a_card_without_a_note_is_allowed(): void
    {
        $businessOwner = BusinessOwner::factory()->create(['current_stage' => PipelineStage::Prospect]);

        $this->actingAs($this->admin())->patch(
            route('admin.pipeline.update-stage', $businessOwner),
            ['stage' => PipelineStage::LinkVisited->value],
        )->assertRedirect();

        $this->assertSame(PipelineStage::LinkVisited, $businessOwner->fresh()->current_stage);
        $this->assertDatabaseHas('lead_stages', [
            'business_owner_id' => $businessOwner->id,
            'stage' => PipelineStage::LinkVisited->value,
            'note' => null,
        ]);
    }

    public function test_rejects_an_invalid_stage(): void
    {
        $businessOwner = BusinessOwner::factory()->create();

        $this->actingAs($this->admin())->patch(
            route('admin.pipeline.update-stage', $businessOwner),
            ['stage' => 'not_a_real_stage'],
        )->assertSessionHasErrors('stage');
    }

    public function test_niche_filter_returns_only_matching_business_owners(): void
    {
        $category = TaxonomyCategory::factory()->create();
        $matchingNiche = TaxonomyNiche::factory()->create(['taxonomy_category_id' => $category->id]);
        $otherNiche = TaxonomyNiche::factory()->create(['taxonomy_category_id' => $category->id]);

        $matching = BusinessOwner::factory()->create(['pre_selected_niche_id' => $matchingNiche->id]);
        BusinessOwner::factory()->create(['pre_selected_niche_id' => $otherNiche->id]);

        $response = $this->actingAs($this->admin())->get(
            route('admin.pipeline.index', ['taxonomy_niche_id' => $matchingNiche->id]),
        );

        $names = collect($response->viewData('page')['props']['businessOwners'])->pluck('id');

        $this->assertEqualsCanonicalizing([$matching->id], $names->all());
    }

    public function test_category_filter_returns_business_owners_across_its_niches(): void
    {
        $category = TaxonomyCategory::factory()->create();
        $otherCategory = TaxonomyCategory::factory()->create();
        $nicheA = TaxonomyNiche::factory()->create(['taxonomy_category_id' => $category->id]);
        $nicheB = TaxonomyNiche::factory()->create(['taxonomy_category_id' => $category->id]);
        $otherNiche = TaxonomyNiche::factory()->create(['taxonomy_category_id' => $otherCategory->id]);

        $boA = BusinessOwner::factory()->create(['pre_selected_niche_id' => $nicheA->id]);
        $boB = BusinessOwner::factory()->create(['pre_selected_niche_id' => $nicheB->id]);
        BusinessOwner::factory()->create(['pre_selected_niche_id' => $otherNiche->id]);

        $response = $this->actingAs($this->admin())->get(
            route('admin.pipeline.index', ['taxonomy_category_id' => $category->id]),
        );

        $ids = collect($response->viewData('page')['props']['businessOwners'])->pluck('id');

        $this->assertEqualsCanonicalizing([$boA->id, $boB->id], $ids->all());
    }

    public function test_date_filter_restricts_by_created_at_range(): void
    {
        $inRange = BusinessOwner::factory()->create(['created_at' => now()->subDays(2)]);
        BusinessOwner::factory()->create(['created_at' => now()->subDays(10)]);

        $response = $this->actingAs($this->admin())->get(route('admin.pipeline.index', [
            'date_from' => now()->subDays(3)->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

        $ids = collect($response->viewData('page')['props']['businessOwners'])->pluck('id');

        $this->assertEqualsCanonicalizing([$inRange->id], $ids->all());
    }

    public function test_stage_history_is_scoped_to_its_own_business_owner_and_shows_from_to(): void
    {
        $businessOwner = BusinessOwner::factory()->create(['current_stage' => PipelineStage::Prospect]);
        $otherBo = BusinessOwner::factory()->create();

        LeadStage::factory()->create([
            'business_owner_id' => $businessOwner->id,
            'stage' => PipelineStage::Prospect,
            'changed_at' => now()->subMinutes(10),
        ]);
        LeadStage::factory()->create([
            'business_owner_id' => $businessOwner->id,
            'stage' => PipelineStage::ReferralSent,
            'note' => 'Sent via WhatsApp.',
            'changed_at' => now()->subMinutes(5),
        ]);
        LeadStage::factory()->create([
            'business_owner_id' => $otherBo->id,
            'stage' => PipelineStage::LinkVisited,
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.business-owners.show', $businessOwner));

        $history = $response->viewData('page')['props']['stageHistory'];

        $this->assertCount(2, $history);
        $this->assertSame(PipelineStage::ReferralSent->value, $history[0]['to_stage']);
        $this->assertSame(PipelineStage::Prospect->value, $history[0]['from_stage']);
        $this->assertSame('Sent via WhatsApp.', $history[0]['note']);
        $this->assertNull($history[1]['from_stage']);
        $this->assertSame(PipelineStage::Prospect->value, $history[1]['to_stage']);
    }
}
