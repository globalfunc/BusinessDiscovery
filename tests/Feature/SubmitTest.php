<?php

namespace Tests\Feature;

use App\Enums\DiscoveryPhase;
use App\Enums\PipelineStage;
use App\Enums\ReferralTokenState;
use App\Models\ActivityEvent;
use App\Models\BusinessOwner;
use App\Models\DiscoverySession;
use App\Models\ReferralToken;
use App\Services\Ai\AiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmitTest extends TestCase
{
    use RefreshDatabase;

    private BusinessOwner $businessOwner;

    private DiscoverySession $session;

    private ReferralToken $referralToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->businessOwner = BusinessOwner::factory()->create(['current_stage' => PipelineStage::DiscoveryInProgress]);
        $this->session = DiscoverySession::factory()->create([
            'business_owner_id' => $this->businessOwner->id,
            'current_phase' => DiscoveryPhase::Review,
        ]);
        $this->referralToken = ReferralToken::factory()->create([
            'business_owner_id' => $this->businessOwner->id,
            'state' => ReferralTokenState::InProgress,
        ]);
    }

    private function withBoSession()
    {
        return $this->withSession([
            'referral_token_id' => $this->referralToken->id,
            'business_owner_id' => $this->businessOwner->id,
            'referral_confirmed_'.$this->referralToken->id => true,
        ]);
    }

    public function test_submit_transitions_stage_stamps_timestamp_and_marks_token_submitted(): void
    {
        $response = $this->withBoSession()->post(route('discovery.submit'));

        $response->assertRedirect(route('discovery.show', ['phase' => DiscoveryPhase::Review->value]));

        $this->session->refresh();
        $this->businessOwner->refresh();
        $this->referralToken->refresh();

        $this->assertSame('submitted', $this->session->status);
        $this->assertNotNull($this->session->submitted_at);
        $this->assertSame(PipelineStage::DiscoveryComplete, $this->businessOwner->current_stage);
        $this->assertSame(ReferralTokenState::Submitted, $this->referralToken->state);

        $this->assertTrue(
            ActivityEvent::where('business_owner_id', $this->businessOwner->id)
                ->where('type', 'discovery_submitted')
                ->exists()
        );
    }

    public function test_resubmitting_is_idempotent_and_does_not_duplicate_activity_events(): void
    {
        $this->withBoSession()->post(route('discovery.submit'))->assertRedirect();
        $firstSubmittedAt = $this->session->refresh()->submitted_at;

        $this->travel(5)->minutes();
        $this->withBoSession()->post(route('discovery.submit'))->assertRedirect();

        $this->session->refresh();
        $this->assertSame(1, ActivityEvent::where('type', 'discovery_submitted')->count());
        $this->assertTrue($firstSubmittedAt->equalTo($this->session->submitted_at));
    }

    public function test_post_submit_visit_to_any_phase_renders_readonly_review_and_makes_no_ai_calls(): void
    {
        $this->mock(AiClient::class, function ($mock) {
            $mock->shouldReceive('call')->never();
        });

        $this->withBoSession()->post(route('discovery.submit'))->assertRedirect();

        $response = $this->withBoSession()->get(route('discovery.show', ['phase' => DiscoveryPhase::Phase2->value]));

        $response->assertRedirect(route('discovery.show', ['phase' => DiscoveryPhase::Review->value]));

        $response = $this->withBoSession()->get(route('discovery.show', ['phase' => DiscoveryPhase::Review->value]));

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->where('session.status', 'submitted')
            ->where('phase', DiscoveryPhase::Review->value));
    }

    public function test_post_submit_answer_update_is_blocked(): void
    {
        $this->withBoSession()->post(route('discovery.submit'))->assertRedirect();

        $this->withBoSession()->patchJson(route('discovery.answers.update'), [
            'phase' => DiscoveryPhase::Phase4->value,
            'field_key' => 'notes',
            'value' => 'trying to edit after submit',
        ])->assertForbidden();
    }

    public function test_post_submit_navigate_is_blocked(): void
    {
        $this->withBoSession()->post(route('discovery.submit'))->assertRedirect();

        $this->withBoSession()->post(route('discovery.navigate'), [
            'to' => DiscoveryPhase::Phase4->value,
        ])->assertForbidden();
    }

    public function test_post_submit_spec_compile_and_amend_are_blocked(): void
    {
        $this->withBoSession()->post(route('discovery.submit'))->assertRedirect();

        $this->withBoSession()->postJson(route('discovery.spec.compile'))->assertForbidden();
        $this->withBoSession()->postJson(route('discovery.spec.amend'), [
            'instruction' => 'Make it shorter.',
        ])->assertForbidden();
    }

    public function test_post_submit_suggestion_endpoints_are_blocked(): void
    {
        $this->withBoSession()->post(route('discovery.submit'))->assertRedirect();

        $this->withBoSession()->postJson(route('discovery.suggest.services'))->assertForbidden();
    }

    public function test_all_non_hard_gated_phases_remain_reachable_before_submission(): void
    {
        // Sanity check the read-only lock doesn't leak into the in-progress state:
        // Review is the furthest-reached phase here, so every earlier phase URL
        // should render normally (not bounce) while status is still in_progress.
        foreach ([DiscoveryPhase::Phase2, DiscoveryPhase::Phase3, DiscoveryPhase::Phase4, DiscoveryPhase::Phase5] as $phase) {
            $this->withBoSession()->get(route('discovery.show', ['phase' => $phase->value]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->where('phase', $phase->value));
        }
    }
}
