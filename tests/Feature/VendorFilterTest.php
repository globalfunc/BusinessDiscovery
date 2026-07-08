<?php

namespace Tests\Feature;

use Anthropic\Client;
use App\Enums\AiCallStatus;
use App\Models\AiCall;
use App\Models\VendorBlocklistTerm;
use App\Services\Ai\AiCallRequest;
use App\Services\Ai\AiCallResult;
use App\Services\Ai\AiClient;
use App\Services\Ai\VendorFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Exercises the §7.6 output filter through the real AiClient::call()
 * orchestration — clean pass-through, single-regeneration-on-hit, and redact +
 * vendor_leak on a second hit — with the raw transport (dispatch) swapped for a
 * queued double. The scan/regex/redaction primitives are covered directly
 * against VendorFilter at the bottom.
 */
class VendorFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * An AiClient whose dispatch() returns the queued responses in order: a
     * string is a successful turn, null is a failed one (API error). The last
     * entry repeats if call() dispatches more than provided.
     */
    private function clientReturning(?string ...$responses): AiClient
    {
        return new class(new VendorFilter, $responses) extends AiClient
        {
            private int $index = 0;

            /** @param  array<int, string|null>  $responses */
            public function __construct(VendorFilter $filter, private array $responses)
            {
                parent::__construct(new Client(apiKey: 'test'), $filter);
            }

            protected function dispatch(AiCallRequest $request): AiCallResult
            {
                $response = $this->responses[$this->index] ?? $this->responses[array_key_last($this->responses)];
                $this->index++;

                $aiCall = AiCall::create([
                    'tool' => $request->tool,
                    'model' => 'claude-sonnet-5',
                    'status' => $response === null ? AiCallStatus::Failed : AiCallStatus::Success,
                    'vendor_leak' => false,
                ]);

                return new AiCallResult(successful: $response !== null, text: $response, aiCall: $aiCall);
            }
        };
    }

    private function request(): AiCallRequest
    {
        return new AiCallRequest(
            tool: 'suggest.services',
            messages: [['role' => 'user', 'content' => 'Suggest services.']],
            system: 'Be vendor neutral.',
        );
    }

    public function test_clean_output_passes_through_without_regeneration(): void
    {
        VendorBlocklistTerm::factory()->create(['term' => 'Calendly']);

        $result = $this->clientReturning('Use an online booking system.')->call($this->request());

        $this->assertTrue($result->successful);
        $this->assertSame('Use an online booking system.', $result->text);
        $this->assertFalse($result->aiCall->vendor_leak);
        // Only one ai_calls row — no regeneration happened.
        $this->assertDatabaseCount('ai_calls', 1);
    }

    public function test_first_hit_regenerates_once_and_returns_the_clean_retry(): void
    {
        VendorBlocklistTerm::factory()->create(['term' => 'Calendly']);

        $result = $this->clientReturning(
            'Set up Calendly for bookings.',
            'Set up an online booking system.',
        )->call($this->request());

        $this->assertTrue($result->successful);
        $this->assertSame('Set up an online booking system.', $result->text);
        $this->assertStringNotContainsString('Calendly', $result->text);
        $this->assertFalse($result->aiCall->vendor_leak);
        // Two rows: the leaked first attempt + the clean regeneration.
        $this->assertDatabaseCount('ai_calls', 2);
        $this->assertDatabaseMissing('ai_calls', ['vendor_leak' => true]);
    }

    public function test_second_hit_redacts_and_flags_vendor_leak(): void
    {
        VendorBlocklistTerm::factory()->create(['term' => 'Calendly', 'replacement' => 'an online booking system']);

        $result = $this->clientReturning(
            'Use Calendly.',
            'Definitely use Calendly again.',
        )->call($this->request());

        $this->assertTrue($result->successful, 'A persisted leak is redacted, not blocked.');
        $this->assertSame('Definitely use an online booking system again.', $result->text);
        $this->assertTrue($result->aiCall->fresh()->vendor_leak);
        // The regenerated (second) call is the one flagged.
        $this->assertDatabaseCount('ai_calls', 2);
        $this->assertDatabaseHas('ai_calls', ['id' => $result->aiCall->id, 'vendor_leak' => true]);
    }

    public function test_default_label_used_when_term_has_no_replacement(): void
    {
        config(['ai.vendor_redaction_label' => 'a custom solution']);
        VendorBlocklistTerm::factory()->create(['term' => 'Shopify', 'replacement' => null]);

        $result = $this->clientReturning('Build on Shopify.', 'Really, Shopify.')->call($this->request());

        $this->assertSame('Really, a custom solution.', $result->text);
        $this->assertTrue($result->aiCall->fresh()->vendor_leak);
    }

    public function test_regex_term_triggers_the_filter(): void
    {
        VendorBlocklistTerm::factory()->regex()->create([
            'term' => 'Google\s+Ads',
            'replacement' => 'a search & advertising platform',
        ]);

        $result = $this->clientReturning('Run Google  Ads campaigns.', 'Run search campaigns.')->call($this->request());

        $this->assertSame('Run search campaigns.', $result->text);
        $this->assertDatabaseCount('ai_calls', 2);
    }

    public function test_failed_regeneration_redacts_first_attempt_and_never_blocks(): void
    {
        VendorBlocklistTerm::factory()->create(['term' => 'Calendly', 'replacement' => 'an online booking system']);

        // Second dispatch fails (null) → never block: redact + flag the first attempt.
        $result = $this->clientReturning('Use Calendly.', null)->call($this->request());

        $this->assertTrue($result->successful);
        $this->assertSame('Use an online booking system.', $result->text);
        $this->assertTrue($result->aiCall->fresh()->vendor_leak);
    }

    public function test_scan_respects_word_boundaries_and_active_flag(): void
    {
        VendorBlocklistTerm::factory()->create(['term' => 'Wix']);
        VendorBlocklistTerm::factory()->inactive()->create(['term' => 'Squarespace']);

        $filter = app(VendorFilter::class);

        // Substring inside another word must not match.
        $this->assertSame([], $filter->scan('The Wixom bakery reopened.'));
        // Standalone token matches.
        $this->assertNotSame([], $filter->scan('Built on Wix last year.'));
        // Inactive terms are ignored.
        $this->assertSame([], $filter->scan('They used Squarespace before.'));
    }
}
