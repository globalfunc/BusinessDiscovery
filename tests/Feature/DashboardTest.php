<?php

namespace Tests\Feature;

use App\Enums\AiCallStatus;
use App\Enums\PipelineStage;
use App\Models\ActivityEvent;
use App\Models\AiCall;
use App\Models\BusinessOwner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * S4.1 — dashboard KPI tiles, AI usage summary, and activity feed, all backed
 * by real query aggregates instead of the S1.2 placeholder widgets.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    public function test_kpi_tiles_count_business_owners_by_funnel_stage(): void
    {
        BusinessOwner::factory()->create(['current_stage' => PipelineStage::Prospect]);
        BusinessOwner::factory()->create(['current_stage' => PipelineStage::ReferralSent]);
        BusinessOwner::factory()->create(['current_stage' => PipelineStage::LinkVisited]);
        BusinessOwner::factory()->count(2)->create(['current_stage' => PipelineStage::DiscoveryInProgress]);
        BusinessOwner::factory()->create(['current_stage' => PipelineStage::DiscoveryComplete]);
        BusinessOwner::factory()->create(['current_stage' => PipelineStage::ProposalSent]);
        BusinessOwner::factory()->create(['current_stage' => PipelineStage::Won]);
        BusinessOwner::factory()->create(['current_stage' => PipelineStage::Lost]);

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $response->assertOk();
        $kpis = $response->viewData('page')['props']['kpis'];

        $this->assertSame(9, $kpis['total']);
        // link_visited or later: everything except prospect/referral_sent = 7
        $this->assertSame(7, $kpis['links_visited']);
        $this->assertSame(2, $kpis['in_progress']);
        // discovery_complete or later = 4 (complete, proposal_sent, won, lost)
        $this->assertSame(4, $kpis['submitted']);
        // proposal_sent or later = 3 (proposal_sent, won, lost)
        $this->assertSame(3, $kpis['proposals_sent']);
        $this->assertSame(2, $kpis['closed']);
    }

    public function test_ai_usage_summary_aggregates_overall_monthly_and_top_consumers(): void
    {
        $heavyUser = BusinessOwner::factory()->create(['name' => 'Heavy User']);
        $lightUser = BusinessOwner::factory()->create(['name' => 'Light User']);

        AiCall::factory()->create([
            'business_owner_id' => $heavyUser->id,
            'input_tokens' => 1000,
            'output_tokens' => 500,
            'cost_estimate' => 0.10,
            'status' => AiCallStatus::Success,
            'created_at' => now()->subMonths(2),
        ]);
        AiCall::factory()->create([
            'business_owner_id' => $heavyUser->id,
            'input_tokens' => 2000,
            'output_tokens' => 1000,
            'cost_estimate' => 0.20,
            'status' => AiCallStatus::Success,
            'created_at' => now(),
        ]);
        AiCall::factory()->create([
            'business_owner_id' => $lightUser->id,
            'input_tokens' => 100,
            'output_tokens' => 50,
            'cost_estimate' => 0.01,
            'status' => AiCallStatus::Success,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $response->assertOk();
        $usage = $response->viewData('page')['props']['aiUsage'];

        $this->assertSame(4650, $usage['overall']['tokens']);
        $this->assertEqualsWithDelta(0.31, $usage['overall']['cost'], 0.0001);

        $this->assertSame(3150, $usage['this_month']['tokens']);
        $this->assertEqualsWithDelta(0.21, $usage['this_month']['cost'], 0.0001);

        $this->assertSame($heavyUser->id, $usage['top_consumers'][0]['business_owner_id']);
        $this->assertSame(4500, $usage['top_consumers'][0]['tokens']);
        $this->assertSame($lightUser->id, $usage['top_consumers'][1]['business_owner_id']);
    }

    public function test_activity_feed_orders_recent_first_and_paginates(): void
    {
        $businessOwner = BusinessOwner::factory()->create();

        foreach (range(1, 20) as $i) {
            ActivityEvent::factory()->create([
                'business_owner_id' => $businessOwner->id,
                'type' => 'note',
                'created_at' => now()->subMinutes(20 - $i),
            ]);
        }

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $response->assertOk();
        $activity = $response->viewData('page')['props']['activity'];

        $this->assertCount(15, $activity['data']);
        $this->assertSame(1, $activity['current_page']);
        $this->assertSame(2, $activity['last_page']);

        // most recent event (created 1 minute ago) should be first
        $this->assertTrue(
            $activity['data'][0]['created_at'] > $activity['data'][1]['created_at']
        );

        $secondPage = $this->actingAs($this->admin())->get(route('admin.dashboard', ['page' => 2]));
        $secondPage->assertOk();
        $secondPageActivity = $secondPage->viewData('page')['props']['activity'];

        $this->assertCount(5, $secondPageActivity['data']);
        $this->assertSame(2, $secondPageActivity['current_page']);
    }
}
