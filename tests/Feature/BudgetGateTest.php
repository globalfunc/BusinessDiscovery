<?php

namespace Tests\Feature;

use Anthropic\Client;
use App\Enums\AiCallStatus;
use App\Models\AiCall;
use App\Models\BusinessOwner;
use App\Services\Ai\AiCallRequest;
use App\Services\Ai\AiCallResult;
use App\Services\Ai\AiClient;
use App\Services\Ai\VendorFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exercises the §7.7 pre-flight gate through the real AiClient::call()
 * orchestration, with the raw transport (dispatch) swapped for a counting
 * double — mirrors VendorFilterTest's approach so a blocked call can be
 * proven to never reach the network.
 */
class BudgetGateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * An AiClient whose dispatch() always succeeds and counts invocations,
     * so tests can assert zero (or one) real dispatch happened.
     */
    private function countingClient(int &$dispatchCount): AiClient
    {
        return new class(new VendorFilter, $dispatchCount) extends AiClient
        {
            public function __construct(VendorFilter $filter, private int &$dispatchCount)
            {
                parent::__construct(new Client(apiKey: 'test'), $filter);
            }

            protected function dispatch(AiCallRequest $request): AiCallResult
            {
                $this->dispatchCount++;

                $aiCall = AiCall::create([
                    'business_owner_id' => $request->businessOwner?->id,
                    'tool' => $request->tool,
                    'model' => 'claude-sonnet-5',
                    'input_tokens' => 100,
                    'output_tokens' => 50,
                    'status' => AiCallStatus::Success,
                    'vendor_leak' => false,
                ]);

                return new AiCallResult(successful: true, text: 'Use an online booking system.', aiCall: $aiCall);
            }
        };
    }

    private function request(?BusinessOwner $businessOwner = null): AiCallRequest
    {
        return new AiCallRequest(
            tool: 'suggest.services',
            messages: [['role' => 'user', 'content' => 'Suggest services.']],
            system: 'Be vendor neutral.',
            businessOwner: $businessOwner,
        );
    }

    public function test_call_proceeds_when_under_every_cap(): void
    {
        $businessOwner = BusinessOwner::factory()->create();
        $dispatches = 0;

        $result = $this->countingClient($dispatches)->call($this->request($businessOwner));

        $this->assertTrue($result->successful);
        $this->assertSame(1, $dispatches);
    }

    public function test_per_bo_cap_exhausted_blocks_the_call_without_dispatching(): void
    {
        config(['ai.per_bo_token_cap' => 100]);
        $businessOwner = BusinessOwner::factory()->create();
        AiCall::factory()->create([
            'business_owner_id' => $businessOwner->id,
            'input_tokens' => 80,
            'output_tokens' => 30,
        ]);
        $dispatches = 0;

        $result = $this->countingClient($dispatches)->call($this->request($businessOwner));

        $this->assertFalse($result->successful);
        $this->assertSame(0, $dispatches);
        $this->assertSame(AiCallStatus::BudgetExhausted, $result->aiCall->status);
        $this->assertSame(0, $result->aiCall->input_tokens);
    }

    public function test_per_bo_override_beats_the_global_default(): void
    {
        config(['ai.per_bo_token_cap' => 300000]);
        $businessOwner = BusinessOwner::factory()->create(['ai_token_cap' => 100]);
        AiCall::factory()->create([
            'business_owner_id' => $businessOwner->id,
            'input_tokens' => 80,
            'output_tokens' => 30,
        ]);
        $dispatches = 0;

        // Global default (300k) is nowhere near exhausted, but the BO's own
        // override (100) is — the override must win.
        $result = $this->countingClient($dispatches)->call($this->request($businessOwner));

        $this->assertFalse($result->successful);
        $this->assertSame(0, $dispatches);
    }

    public function test_global_monthly_cap_blocks_regardless_of_business_owner(): void
    {
        config(['ai.global_monthly_token_cap' => 100, 'ai.per_bo_token_cap' => null]);
        $other = BusinessOwner::factory()->create();
        AiCall::factory()->create(['business_owner_id' => $other->id, 'input_tokens' => 80, 'output_tokens' => 30]);

        $businessOwner = BusinessOwner::factory()->create();
        $dispatches = 0;

        $result = $this->countingClient($dispatches)->call($this->request($businessOwner));

        $this->assertFalse($result->successful);
        $this->assertSame(0, $dispatches);
        $this->assertSame(AiCallStatus::BudgetExhausted, $result->aiCall->status);
    }

    public function test_soft_warn_mode_lets_the_call_through_when_exhausted(): void
    {
        config(['ai.per_bo_token_cap' => 100, 'ai.budget_mode' => 'soft']);
        $businessOwner = BusinessOwner::factory()->create();
        AiCall::factory()->create([
            'business_owner_id' => $businessOwner->id,
            'input_tokens' => 80,
            'output_tokens' => 30,
        ]);
        $dispatches = 0;

        $result = $this->countingClient($dispatches)->call($this->request($businessOwner));

        $this->assertTrue($result->successful);
        $this->assertSame(1, $dispatches);
    }

    public function test_rate_limit_blocks_after_the_configured_number_of_calls_per_minute(): void
    {
        config(['ai.rate_limit_per_minute' => 2]);
        $businessOwner = BusinessOwner::factory()->create();
        $dispatches = 0;
        $client = $this->countingClient($dispatches);

        $client->call($this->request($businessOwner));
        $client->call($this->request($businessOwner));
        $result = $client->call($this->request($businessOwner));

        $this->assertFalse($result->successful);
        $this->assertSame(2, $dispatches);
        $this->assertSame(AiCallStatus::RateLimited, $result->aiCall->status);
    }

    public function test_rate_limit_is_scoped_per_business_owner(): void
    {
        config(['ai.rate_limit_per_minute' => 1]);
        $first = BusinessOwner::factory()->create();
        $second = BusinessOwner::factory()->create();
        $dispatches = 0;
        $client = $this->countingClient($dispatches);

        $client->call($this->request($first));
        $result = $client->call($this->request($second));

        $this->assertTrue($result->successful);
        $this->assertSame(2, $dispatches);
    }
}
