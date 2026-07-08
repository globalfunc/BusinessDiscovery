<?php

namespace Tests\Feature;

use Anthropic\Client;
use App\Enums\AiCallStatus;
use App\Enums\DiscoveryPhase;
use App\Models\AiCall;
use App\Models\BusinessOwner;
use App\Models\DcpProfile;
use App\Models\DiscoverySession;
use App\Models\ReferralToken;
use App\Models\SpecDocument;
use App\Services\Ai\AiCallRequest;
use App\Services\Ai\AiCallResult;
use App\Services\Ai\AiClient;
use App\Services\Ai\VendorFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end proof that an exhausted §7.7 budget routes every AI touchpoint
 * built in S3.1–S3.5 to its existing fallback with zero real Anthropic
 * dispatches — the gate lives inside AiClient::call(), so nothing downstream
 * (DcpGenerator, SuggestionGenerator, SpecCompiler) needed to change.
 */
class TokenBudgetFallbackTest extends TestCase
{
    use RefreshDatabase;

    private BusinessOwner $businessOwner;

    private DiscoverySession $session;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.per_bo_token_cap' => 100]);

        $this->businessOwner = BusinessOwner::factory()->create(['admin_context' => 'Barber referred by a friend.']);
        $this->session = DiscoverySession::factory()->create([
            'business_owner_id' => $this->businessOwner->id,
            'current_phase' => DiscoveryPhase::Review,
        ]);

        // Already over the (lowered) per-BO cap before the test even calls in.
        AiCall::factory()->create([
            'business_owner_id' => $this->businessOwner->id,
            'input_tokens' => 80,
            'output_tokens' => 30,
        ]);

        // Real AiClient, but dispatch() fails the test if it's ever reached —
        // proves the gate short-circuits before any network call.
        $this->app->instance(AiClient::class, new class(new Client(apiKey: 'test'), new VendorFilter) extends AiClient
        {
            protected function dispatch(AiCallRequest $request): AiCallResult
            {
                throw new \RuntimeException("dispatch() must not be reached once the budget is exhausted (tool: {$request->tool}).");
            }
        });
    }

    private function withBoSession(string $routeName, array $payload = [])
    {
        $referralToken = ReferralToken::factory()->create(['business_owner_id' => $this->businessOwner->id]);

        return $this->withSession([
            'referral_token_id' => $referralToken->id,
            'business_owner_id' => $this->businessOwner->id,
            'referral_confirmed_'.$referralToken->id => true,
        ])->postJson(route($routeName), $payload);
    }

    public function test_spec_compile_falls_back_to_deterministic_renderer(): void
    {
        $response = $this->withBoSession('discovery.spec.compile');

        $response->assertOk()->assertJsonPath('status', 'ok')->assertJsonPath('document.generated_by', 'fallback');

        $document = SpecDocument::sole();
        $this->assertSame('ai_failed', $document->model_meta['status']);
        $this->assertTrue(AiCall::where('status', AiCallStatus::BudgetExhausted)->exists());
    }

    public function test_suggestion_endpoint_falls_back_to_presets(): void
    {
        $response = $this->withBoSession('discovery.suggest.services');

        $response->assertOk()->assertJsonPath('status', 'unavailable');
        $this->assertIsArray($response->json('suggestions'));
        $this->assertTrue(AiCall::where('status', AiCallStatus::BudgetExhausted)->exists());
    }

    public function test_intake_stores_empty_dcp_and_still_advances_the_phase(): void
    {
        $this->session->update(['current_phase' => DiscoveryPhase::Phase0]);

        $referralToken = ReferralToken::factory()->create(['business_owner_id' => $this->businessOwner->id]);

        $this->withSession([
            'referral_token_id' => $referralToken->id,
            'business_owner_id' => $this->businessOwner->id,
            'referral_confirmed_'.$referralToken->id => true,
        ])->post(route('discovery.intake.store'))->assertRedirect();

        $profile = DcpProfile::sole();
        $this->assertSame([], $profile->payload);
        $this->assertSame('failed', $profile->model_meta['status']);
        $this->assertSame(DiscoveryPhase::Phase1, $this->session->fresh()->current_phase);
    }
}
