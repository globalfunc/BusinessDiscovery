<?php

namespace Tests\Feature;

use App\Enums\AiCallStatus;
use App\Enums\DiscoveryPhase;
use App\Models\AiCall;
use App\Models\BusinessOwner;
use App\Models\DiscoverySession;
use App\Models\ReferralToken;
use App\Models\SpecDocument;
use App\Services\Ai\AiCallRequest;
use App\Services\Ai\AiCallResult;
use App\Services\Ai\AiClient;
use App\Services\Ai\Tools\Spec\SpecCompiler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecCompileTest extends TestCase
{
    use RefreshDatabase;

    private BusinessOwner $businessOwner;

    private DiscoverySession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->businessOwner = BusinessOwner::factory()->create(['admin_context' => 'Barber referred by a friend.']);
        $this->session = DiscoverySession::factory()->create([
            'business_owner_id' => $this->businessOwner->id,
            'current_phase' => DiscoveryPhase::Review,
        ]);
    }

    private function validSpecMarkdown(): string
    {
        $sections = [
            '1. Business overview & context', '2. Goals, pains & strengths', '3. Selected services',
            '4. Custom & suggested services', '5. Branding & look/feel brief', '6. Content & social presence plan',
            '7. Growth, retention & operations', '8. Billing model, budget & timeline',
            '9. Uploaded assets inventory', '10. Open questions for the discovery call',
        ];

        return implode("\n\n", array_map(fn ($title) => "## {$title}\n\n- Something concrete", $sections));
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

    private function postAs(string $routeName, array $payload = [])
    {
        $referralToken = ReferralToken::factory()->create(['business_owner_id' => $this->businessOwner->id]);

        return $this->withSession([
            'referral_token_id' => $referralToken->id,
            'business_owner_id' => $this->businessOwner->id,
            'referral_confirmed_'.$referralToken->id => true,
        ])->postJson(route($routeName), $payload);
    }

    private function getReview()
    {
        $referralToken = ReferralToken::factory()->create(['business_owner_id' => $this->businessOwner->id]);

        return $this->withSession([
            'referral_token_id' => $referralToken->id,
            'business_owner_id' => $this->businessOwner->id,
            'referral_confirmed_'.$referralToken->id => true,
        ])->get(route('discovery.show', ['phase' => DiscoveryPhase::Review->value]));
    }

    public function test_successful_compile_stores_versioned_ai_document(): void
    {
        $this->fakeAiClient(successful: true, text: $this->validSpecMarkdown());

        $response = $this->postAs('discovery.spec.compile');

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('document.version', 1)
            ->assertJsonPath('document.generated_by', 'ai');

        $document = SpecDocument::sole();
        $this->assertSame(1, $document->version);
        $this->assertSame('ai', $document->generated_by);
        $this->assertStringContainsString('## 1. Business overview & context', $document->markdown);
        $this->assertSame('ok', $document->model_meta['status']);
        $this->assertNotNull($document->model_meta['ai_call_id']);
        $this->assertSame('en', $document->model_meta['language']);
    }

    public function test_regenerate_appends_a_new_version(): void
    {
        $this->fakeAiClient(successful: true, text: $this->validSpecMarkdown());

        $this->postAs('discovery.spec.compile')->assertJsonPath('document.version', 1);
        $this->postAs('discovery.spec.compile')->assertJsonPath('document.version', 2);

        $this->assertSame(2, SpecDocument::count());
    }

    public function test_failed_ai_call_stores_deterministic_fallback_document(): void
    {
        $this->fakeAiClient(successful: false, text: null, status: AiCallStatus::Failed);

        $response = $this->postAs('discovery.spec.compile');

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('document.generated_by', 'fallback');

        $document = SpecDocument::sole();
        $this->assertSame('fallback', $document->generated_by);
        $this->assertSame('ai_failed', $document->model_meta['status']);
        // Deterministic renderer output: the localized §7.5 skeleton headings.
        $this->assertStringContainsString('## 1. Business overview & context', $document->markdown);
        $this->assertStringContainsString('## 10. Open questions for the discovery call', $document->markdown);
    }

    public function test_degenerate_ai_output_falls_back_to_deterministic_renderer(): void
    {
        $this->fakeAiClient(successful: true, text: 'Sorry, I cannot help with that.');

        $this->postAs('discovery.spec.compile')->assertJsonPath('document.generated_by', 'fallback');
    }

    public function test_fallback_renderer_makes_zero_ai_calls(): void
    {
        $this->mock(AiClient::class, function ($mock) {
            $mock->shouldReceive('call')->never();
        });

        $document = app(SpecCompiler::class)->compileFallback($this->businessOwner, $this->session);

        $this->assertSame(0, AiCall::count());
        $this->assertSame('fallback', $document->generated_by);
        $this->assertSame(1, $document->version);
        $this->assertStringContainsString('## 1. Business overview & context', $document->markdown);
    }

    public function test_review_screen_serves_latest_stored_version_without_compiling(): void
    {
        $this->mock(AiClient::class, function ($mock) {
            $mock->shouldReceive('call')->never();
        });

        SpecDocument::factory()->create(['discovery_session_id' => $this->session->id, 'version' => 1]);
        $latest = SpecDocument::factory()->create(['discovery_session_id' => $this->session->id, 'version' => 2]);

        $this->getReview()->assertInertia(fn ($page) => $page
            ->where('specDocument.version', 2)
            ->where('specDocument.markdown', $latest->markdown)
            ->where('specStale', false));
    }

    public function test_review_screen_flags_spec_stale_when_answers_changed_after_generation(): void
    {
        $document = SpecDocument::factory()->create(['discovery_session_id' => $this->session->id]);
        $document->timestamps = false;
        $document->forceFill(['created_at' => now()->subHour()])->save();

        $this->session->answers()->create([
            'phase' => DiscoveryPhase::Phase4->value,
            'field_key' => 'notes',
            'value' => 'Changed my mind about the posting cadence.',
        ]);

        $this->getReview()->assertInertia(fn ($page) => $page->where('specStale', true));
    }

    public function test_review_screen_has_no_document_before_first_compile(): void
    {
        $this->getReview()->assertInertia(fn ($page) => $page
            ->where('specDocument', null)
            ->where('specStale', false));
    }
}
