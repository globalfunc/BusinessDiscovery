<?php

namespace Tests\Feature;

use App\Enums\AiCallStatus;
use App\Enums\DiscoveryPhase;
use App\Models\AiCall;
use App\Models\BusinessOwner;
use App\Models\DiscoverySession;
use App\Models\ReferralToken;
use App\Models\TaxonomyCategory;
use App\Models\TaxonomyNiche;
use App\Services\Ai\AiCallRequest;
use App\Services\Ai\AiCallResult;
use App\Services\Ai\AiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DcpGenerationTest extends TestCase
{
    use RefreshDatabase;

    private BusinessOwner $businessOwner;

    private DiscoverySession $session;

    private TaxonomyNiche $niche;

    protected function setUp(): void
    {
        parent::setUp();

        $this->businessOwner = BusinessOwner::factory()->create(['admin_context' => 'Long-time barber, referred by a friend.']);
        $this->session = DiscoverySession::factory()->create([
            'business_owner_id' => $this->businessOwner->id,
            'current_phase' => DiscoveryPhase::Phase0,
        ]);

        $category = TaxonomyCategory::factory()->create();
        $this->niche = TaxonomyNiche::factory()->create(['taxonomy_category_id' => $category->id]);

        $this->session->answers()->create([
            'phase' => DiscoveryPhase::Phase0->value,
            'field_key' => 'free_prompt',
            'value' => 'I run a barbershop, clients keep missing appointments.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validDcpPayload(): array
    {
        return [
            'detected_niche' => ['category' => 'personal_care', 'niche' => 'barbershop', 'niche_id' => $this->niche->id, 'confidence' => 0.9],
            'pain_points' => [['id' => 'no_shows', 'label' => 'Missed appointments', 'evidence' => 'clients keep missing appointments']],
            'goals' => [['id' => 'online_booking', 'label' => 'Let clients book online']],
            'strengths' => ['loyal clients'],
            'digital_maturity' => 'low',
            'priority_signals' => ['retention'],
            'tone_hints' => ['language' => 'en', 'formality' => 'casual'],
            'summary' => 'A barbershop losing revenue to no-shows.',
        ];
    }

    private function fakeAiClient(bool $successful, ?string $text, AiCallStatus $status = AiCallStatus::Success): void
    {
        $businessOwner = $this->businessOwner;

        $this->mock(AiClient::class, function ($mock) use ($successful, $text, $status, $businessOwner) {
            $mock->shouldReceive('call')
                ->andReturnUsing(fn (AiCallRequest $request) => new AiCallResult(
                    successful: $successful,
                    text: $text,
                    aiCall: AiCall::factory()->create([
                        'business_owner_id' => $businessOwner->id,
                        'tool' => $request->tool,
                        'status' => $status,
                    ]),
                ));
        });
    }

    private function postIntake()
    {
        $referralToken = ReferralToken::factory()->create(['business_owner_id' => $this->businessOwner->id]);

        return $this->withSession([
            'referral_token_id' => $referralToken->id,
            'business_owner_id' => $this->businessOwner->id,
            'referral_confirmed_'.$referralToken->id => true,
        ])->post(route('discovery.intake.store'));
    }

    public function test_successful_generation_stores_versioned_dcp_and_advances_to_phase_1(): void
    {
        $this->fakeAiClient(successful: true, text: json_encode($this->validDcpPayload()));

        $response = $this->postIntake();

        $response->assertRedirect(route('discovery.show', ['phase' => 'phase_1']));

        $profile = $this->session->refresh()->latestDcpProfile;
        $this->assertNotNull($profile);
        $this->assertSame(1, $profile->version);
        $this->assertSame($this->niche->id, $profile->payload['detected_niche']['niche_id']);
        $this->assertSame('ok', $profile->model_meta['status']);
        $this->assertSame(DiscoveryPhase::Phase1, $this->session->current_phase);
    }

    public function test_markdown_fenced_json_output_is_still_accepted(): void
    {
        $this->fakeAiClient(successful: true, text: "```json\n".json_encode($this->validDcpPayload())."\n```");

        $this->postIntake();

        $this->assertFalse($this->session->refresh()->latestDcpProfile->isEmpty());
    }

    public function test_failed_ai_call_stores_empty_dcp_and_still_advances(): void
    {
        $this->fakeAiClient(successful: false, text: null, status: AiCallStatus::Failed);

        $response = $this->postIntake();

        $response->assertRedirect(route('discovery.show', ['phase' => 'phase_1']));

        $profile = $this->session->refresh()->latestDcpProfile;
        $this->assertNotNull($profile);
        $this->assertTrue($profile->isEmpty());
        $this->assertSame('failed', $profile->model_meta['status']);
        $this->assertSame(DiscoveryPhase::Phase1, $this->session->current_phase);
    }

    public function test_schema_invalid_output_stores_empty_dcp(): void
    {
        $this->fakeAiClient(successful: true, text: json_encode(['detected_niche' => 'barbershop']));

        $this->postIntake();

        $this->assertTrue($this->session->refresh()->latestDcpProfile->isEmpty());
    }

    public function test_hallucinated_niche_id_is_nulled(): void
    {
        $payload = $this->validDcpPayload();
        $payload['detected_niche']['niche_id'] = 999999;
        $this->fakeAiClient(successful: true, text: json_encode($payload));

        $this->postIntake();

        $profile = $this->session->refresh()->latestDcpProfile;
        $this->assertFalse($profile->isEmpty());
        $this->assertNull($profile->payload['detected_niche']['niche_id']);
    }

    public function test_retry_writes_next_version_and_does_not_block_submission_path(): void
    {
        $this->fakeAiClient(successful: false, text: null, status: AiCallStatus::Failed);
        $this->postIntake();

        // Retry from Phase 1: session already advanced, a new version is written.
        $this->fakeAiClient(successful: true, text: json_encode($this->validDcpPayload()));
        $response = $this->postIntake();

        $response->assertRedirect(route('discovery.show', ['phase' => 'phase_1']));

        $this->session->refresh();
        $this->assertSame(2, $this->session->dcpProfiles()->count());
        $this->assertSame(2, $this->session->latestDcpProfile->version);
        $this->assertFalse($this->session->latestDcpProfile->isEmpty());
        // Still Phase 1 — a retry never rewinds or re-advances the machine.
        $this->assertSame(DiscoveryPhase::Phase1, $this->session->current_phase);
    }
}
