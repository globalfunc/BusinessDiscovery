<?php

namespace Tests\Feature;

use App\Enums\AiCallStatus;
use App\Enums\DiscoveryPhase;
use App\Models\AiCall;
use App\Models\BusinessOwner;
use App\Models\DiscoverySession;
use App\Models\ReferralToken;
use App\Models\SpecAmendment;
use App\Models\SpecDocument;
use App\Services\Ai\AiCallRequest;
use App\Services\Ai\AiCallResult;
use App\Services\Ai\AiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecAmendTest extends TestCase
{
    use RefreshDatabase;

    private BusinessOwner $businessOwner;

    private DiscoverySession $session;

    private SpecDocument $document;

    protected function setUp(): void
    {
        parent::setUp();

        $this->businessOwner = BusinessOwner::factory()->create();
        $this->session = DiscoverySession::factory()->create([
            'business_owner_id' => $this->businessOwner->id,
            'current_phase' => DiscoveryPhase::Review,
        ]);
        $this->document = SpecDocument::factory()->create([
            'discovery_session_id' => $this->session->id,
            'markdown' => $this->specMarkdown('- Reduce no-shows'),
        ]);
    }

    private function specMarkdown(string $goalsLine): string
    {
        $sections = [
            '1. Business overview & context' => '- Company: Test Barbershop',
            '2. Goals, pains & strengths' => $goalsLine,
            '3. Selected services' => '- **Online booking**',
            '4. Custom & suggested services' => 'Nothing recorded yet.',
            '5. Branding & look/feel brief' => '- Warm, modern',
            '6. Content & social presence plan' => 'Nothing recorded yet.',
            '7. Growth, retention & operations' => 'Nothing recorded yet.',
            '8. Billing model, budget & timeline' => '- Budget: €2,000 – €4,000',
            '9. Uploaded assets inventory' => 'Nothing recorded yet.',
            '10. Open questions for the discovery call' => '- Confirm the launch window',
        ];

        return implode("\n\n", array_map(
            fn ($title, $body) => "## {$title}\n\n{$body}",
            array_keys($sections),
            $sections,
        ));
    }

    private function validAmendJson(): string
    {
        return json_encode([
            'markdown' => $this->specMarkdown('- Reduce no-shows and grow repeat visits'),
            'change_summary' => 'Added growing repeat visits to the goals section.',
            'amended_sections' => [2],
        ]);
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

    private function postAmend(array $payload = ['instruction' => 'Also mention growing repeat visits as a goal.'])
    {
        $referralToken = ReferralToken::factory()->create(['business_owner_id' => $this->businessOwner->id]);

        return $this->withSession([
            'referral_token_id' => $referralToken->id,
            'business_owner_id' => $this->businessOwner->id,
            'referral_confirmed_'.$referralToken->id => true,
        ])->postJson(route('discovery.spec.amend'), $payload);
    }

    public function test_successful_amend_writes_new_version_with_change_summary_and_audit_row(): void
    {
        $this->fakeAiClient(successful: true, text: $this->validAmendJson());

        $response = $this->postAmend();

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('document.version', 2)
            ->assertJsonPath('document.change_summary', 'Added growing repeat visits to the goals section.');

        $revised = SpecDocument::where('version', 2)->sole();
        $this->assertSame('ai', $revised->generated_by);
        $this->assertStringContainsString('grow repeat visits', $revised->markdown);
        $this->assertSame([2], $revised->model_meta['amended_sections']);

        $amendment = SpecAmendment::sole();
        $this->assertSame($this->document->id, $amendment->spec_document_id);
        $this->assertSame('Also mention growing repeat visits as a goal.', $amendment->instruction);
        $this->assertSame(2, $amendment->resulting_version);
    }

    public function test_failed_ai_call_writes_nothing_and_reports_unavailable(): void
    {
        $this->fakeAiClient(successful: false, text: null, status: AiCallStatus::Failed);

        $this->postAmend()->assertOk()->assertJsonPath('status', 'unavailable');

        $this->assertSame(1, SpecDocument::count());
        $this->assertSame(0, SpecAmendment::count());
    }

    public function test_unparseable_ai_output_writes_nothing_and_reports_unavailable(): void
    {
        $this->fakeAiClient(successful: true, text: 'Here is the revised spec: ...');

        $this->postAmend()->assertOk()->assertJsonPath('status', 'unavailable');

        $this->assertSame(1, SpecDocument::count());
        $this->assertSame(0, SpecAmendment::count());
    }

    public function test_amend_without_existing_spec_is_rejected(): void
    {
        $this->document->delete();

        $this->postAmend()->assertStatus(422);
    }

    public function test_amend_requires_an_instruction(): void
    {
        $this->postAmend(['instruction' => ''])->assertStatus(422);
    }
}
