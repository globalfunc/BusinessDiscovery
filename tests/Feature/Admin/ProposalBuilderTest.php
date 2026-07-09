<?php

namespace Tests\Feature\Admin;

use Anthropic\Client;
use App\Enums\AiCallStatus;
use App\Enums\DiscoveryPhase;
use App\Models\AiCall;
use App\Models\AssessmentDocument;
use App\Models\BusinessOwner;
use App\Models\DiscoverySession;
use App\Models\ProposalDocument;
use App\Models\ReferralToken;
use App\Models\SpecDocument;
use App\Models\User;
use App\Models\VendorBlocklistTerm;
use App\Services\Ai\AiCallRequest;
use App\Services\Ai\AiCallResult;
use App\Services\Ai\AiClient;
use App\Services\Ai\PromptTemplateRegistry;
use App\Services\Ai\VendorFilter;
use App\Services\Ai\VendorPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * S4.5 — Proposal, assessment & email generators (§6.4/§6.5): versioned
 * drafts through the shared pipeline, the assessment-before-proposal
 * ordering gate, the §7.6.4 vendor-filter opt-out for these three tools,
 * and — critically — that the admin-only assessment is unreachable from any
 * BO-facing route.
 */
class ProposalBuilderTest extends TestCase
{
    use RefreshDatabase;

    private BusinessOwner $businessOwner;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->businessOwner = BusinessOwner::factory()->create([
            'admin_context' => 'Barber referred by a friend.',
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create();
    }

    /**
     * Swap the container's AiClient for one whose raw transport returns the
     * given text (null = failed call), leaving the real call() orchestration
     * — budget gate, vendor filter, opt-out — in place.
     */
    private function fakeTransport(?string $text): void
    {
        $this->app->instance(AiClient::class, $this->clientReturning($text));
    }

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
                    'business_owner_id' => $request->businessOwner?->id,
                    'tool' => $request->tool,
                    'model' => 'claude-sonnet-5',
                    'status' => $response === null ? AiCallStatus::Failed : AiCallStatus::Success,
                    'vendor_leak' => false,
                ]);

                return new AiCallResult(successful: $response !== null, text: $response, aiCall: $aiCall);
            }
        };
    }

    public function test_page_serves_assessments_proposals_and_email_drafts(): void
    {
        AssessmentDocument::factory()->create(['business_owner_id' => $this->businessOwner->id, 'version' => 1]);
        ProposalDocument::factory()->create(['business_owner_id' => $this->businessOwner->id, 'version' => 1]);

        $response = $this->actingAs($this->admin())->get(route('admin.business-owners.proposal', $this->businessOwner));

        $response->assertOk();
        $props = $response->viewData('page')['props'];
        $this->assertCount(1, $props['assessments']);
        $this->assertCount(1, $props['proposals']);
        $this->assertCount(0, $props['emailDrafts']);
    }

    public function test_assessment_generate_stores_versioned_ai_document(): void
    {
        $this->fakeTransport("## Suggested tech stack\n\n- Laravel");

        $this->actingAs($this->admin())
            ->post(route('admin.business-owners.assessment.generate', $this->businessOwner), ['notes' => 'Keep it cheap.'])
            ->assertRedirect();

        $this->assertDatabaseHas('assessment_documents', [
            'business_owner_id' => $this->businessOwner->id,
            'version' => 1,
            'generated_by' => 'ai',
        ]);
        $this->assertDatabaseHas('ai_calls', ['tool' => 'assessment.generate']);
    }

    public function test_assessment_manual_save_appends_next_version(): void
    {
        AssessmentDocument::factory()->create(['business_owner_id' => $this->businessOwner->id, 'version' => 1]);

        $this->actingAs($this->admin())
            ->post(route('admin.business-owners.assessment.store', $this->businessOwner), ['markdown' => '## Edited by hand'])
            ->assertRedirect();

        $this->assertDatabaseHas('assessment_documents', [
            'business_owner_id' => $this->businessOwner->id,
            'version' => 2,
            'generated_by' => 'manual',
            'markdown' => '## Edited by hand',
        ]);
    }

    public function test_proposal_generate_is_blocked_until_an_assessment_exists(): void
    {
        $this->fakeTransport('## Your proposal');

        $this->actingAs($this->admin())
            ->post(route('admin.business-owners.proposal.generate', $this->businessOwner))
            ->assertStatus(422);

        $this->assertDatabaseCount('proposal_documents', 0);
        $this->assertDatabaseCount('ai_calls', 0);
    }

    public function test_proposal_generate_grounds_in_the_latest_assessment(): void
    {
        AssessmentDocument::factory()->create(['business_owner_id' => $this->businessOwner->id, 'version' => 1]);
        $latest = AssessmentDocument::factory()->create([
            'business_owner_id' => $this->businessOwner->id,
            'version' => 2,
            'markdown' => '## Pricing bands — edited by admin',
            'generated_by' => 'manual',
        ]);

        $client = new class(new VendorFilter) extends AiClient
        {
            public ?AiCallRequest $lastRequest = null;

            public function __construct(VendorFilter $filter)
            {
                parent::__construct(new Client(apiKey: 'test'), $filter);
            }

            protected function dispatch(AiCallRequest $request): AiCallResult
            {
                $this->lastRequest = $request;

                return new AiCallResult(
                    successful: true,
                    text: '## Your proposal',
                    aiCall: AiCall::create([
                        'business_owner_id' => $request->businessOwner?->id,
                        'tool' => $request->tool,
                        'model' => 'claude-sonnet-5',
                        'status' => AiCallStatus::Success,
                    ]),
                );
            }
        };
        $this->app->instance(AiClient::class, $client);

        $this->actingAs($this->admin())
            ->post(route('admin.business-owners.proposal.generate', $this->businessOwner))
            ->assertRedirect();

        // The latest (admin-edited) assessment is in the prompt context…
        $this->assertStringContainsString('## Pricing bands — edited by admin', $client->lastRequest->messages[0]['content']);

        // …and the stored version records which assessment grounded it.
        $proposal = ProposalDocument::sole();
        $this->assertSame('ai', $proposal->generated_by);
        $this->assertSame($latest->id, $proposal->model_meta['assessment_document_id']);
        $this->assertSame(2, $proposal->model_meta['assessment_version']);
    }

    public function test_external_proposal_upload_becomes_a_version_of_its_own(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin())
            ->post(route('admin.business-owners.proposal.upload', $this->businessOwner), [
                'file' => UploadedFile::fake()->create('offer.pdf', 200, 'application/pdf'),
            ])
            ->assertRedirect();

        $proposal = ProposalDocument::sole();
        $this->assertSame('uploaded', $proposal->generated_by);
        $this->assertNull($proposal->markdown);
        $this->assertNotNull($proposal->upload_id);
        $this->assertDatabaseHas('uploads', [
            'business_owner_id' => $this->businessOwner->id,
            'kind' => 'proposal',
            'original_name' => 'offer.pdf',
        ]);
    }

    public function test_email_generate_parses_the_subject_body_envelope(): void
    {
        $this->fakeTransport('{"subject": "A few ideas for your barbershop", "body": "Hi Ivan,\nGreat meeting you."}');

        $this->actingAs($this->admin())
            ->post(route('admin.business-owners.emails.generate', $this->businessOwner), [
                'kind' => 'warm_tease',
                'language' => 'bg',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('email_drafts', [
            'business_owner_id' => $this->businessOwner->id,
            'kind' => 'warm_tease',
            'language' => 'bg',
            'subject' => 'A few ideas for your barbershop',
        ]);
    }

    public function test_email_generate_with_unparseable_output_stores_nothing(): void
    {
        $this->fakeTransport('Sorry, here is your email: Dear Ivan...');

        $this->actingAs($this->admin())
            ->post(route('admin.business-owners.emails.generate', $this->businessOwner), [
                'kind' => 'follow_up',
                'language' => 'en',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('email_drafts', 0);
    }

    public function test_assessment_output_skips_the_vendor_filter_but_bo_facing_tools_keep_it(): void
    {
        VendorBlocklistTerm::factory()->create(['term' => 'Shopify', 'replacement' => 'an e-commerce platform']);

        // §7.6.4 opt-out: assessment.generate may name real vendors — one
        // dispatch, no regeneration, no redaction, no vendor_leak flag.
        $result = $this->clientReturning('Integrate with their existing Shopify store.')->call(new AiCallRequest(
            tool: 'assessment.generate',
            messages: [['role' => 'user', 'content' => 'Assess.']],
        ));
        $this->assertSame('Integrate with their existing Shopify store.', $result->text);
        $this->assertDatabaseCount('ai_calls', 1);
        $this->assertDatabaseMissing('ai_calls', ['vendor_leak' => true]);

        // Control: the same leaked text from a BO-facing tool still triggers
        // the §7.6.2 regeneration path exactly as before.
        $result = $this->clientReturning(
            'Integrate with their existing Shopify store.',
            'Integrate with their existing online store.',
        )->call(new AiCallRequest(
            tool: 'suggest.services',
            messages: [['role' => 'user', 'content' => 'Suggest.']],
        ));
        $this->assertSame('Integrate with their existing online store.', $result->text);
    }

    public function test_generator_prompts_do_not_carry_the_vendor_neutrality_system_rule(): void
    {
        $registry = app(PromptTemplateRegistry::class);

        foreach (['assessment.generate', 'proposal.generate', 'email.generate'] as $tool) {
            $this->assertStringNotContainsString(
                VendorPolicy::systemRule(),
                $registry->get($tool)->systemPrompt(),
                "{$tool} must not inject VendorPolicy::systemRule() (§7.6.4).",
            );
        }
    }

    public function test_assessment_is_unreachable_from_bo_facing_routes(): void
    {
        $session = DiscoverySession::factory()->create([
            'business_owner_id' => $this->businessOwner->id,
            'current_phase' => DiscoveryPhase::Review,
        ]);
        // A spec already exists, so opening Review does not trigger a compile.
        SpecDocument::factory()->create(['discovery_session_id' => $session->id, 'version' => 1]);

        $secret = '## INTERNAL: charge 5000, stack is Laravel';
        AssessmentDocument::factory()->create([
            'business_owner_id' => $this->businessOwner->id,
            'markdown' => $secret,
        ]);

        $referralToken = ReferralToken::factory()->create(['business_owner_id' => $this->businessOwner->id]);
        $boSession = [
            'referral_token_id' => $referralToken->id,
            'business_owner_id' => $this->businessOwner->id,
            'referral_confirmed_'.$referralToken->id => true,
        ];

        // The BO's own review screen serves no assessment content.
        $review = $this->withSession($boSession)->get(route('discovery.show', ['phase' => DiscoveryPhase::Review->value]));
        $review->assertOk();
        $this->assertStringNotContainsString($secret, $review->getContent());
        $this->assertStringNotContainsString('assessment', strtolower(json_encode($review->viewData('page')['props'])));

        // A BO referral session is not an authenticated admin: every builder
        // route bounces to login without leaking anything.
        $this->withSession($boSession)
            ->get(route('admin.business-owners.proposal', $this->businessOwner))
            ->assertRedirect('/admin/login');
        $this->withSession($boSession)
            ->post(route('admin.business-owners.assessment.generate', $this->businessOwner))
            ->assertRedirect('/admin/login');
    }
}
