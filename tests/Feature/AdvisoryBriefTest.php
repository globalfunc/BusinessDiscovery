<?php

namespace Tests\Feature;

use App\Enums\AiCallStatus;
use App\Enums\DiscoveryPhase;
use App\Models\AdvisoryBrief;
use App\Models\AiCall;
use App\Models\BriefExemplar;
use App\Models\BusinessOwner;
use App\Models\DiscoverySession;
use App\Models\ReferralToken;
use App\Models\TaxonomyCategory;
use App\Models\TaxonomyNiche;
use App\Models\User;
use App\Services\Ai\AiCallRequest;
use App\Services\Ai\AiCallResult;
use App\Services\Ai\AiClient;
use App\Services\Ai\ContextBlockType;
use App\Services\Ai\Tools\Suggest\ContentSocialSuggestionAssembler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * S5.6 — advisory briefs for suggest.content_social / suggest.growth: the
 * same-call `brief` field, the deterministic quality gate (length cap,
 * platitude blocklist, DCP-grounding), advisory_briefs persistence with
 * reproducibility metadata, and the exemplar context block.
 */
class AdvisoryBriefTest extends TestCase
{
    use RefreshDatabase;

    private BusinessOwner $businessOwner;

    private DiscoverySession $session;

    private TaxonomyNiche $niche;

    protected function setUp(): void
    {
        parent::setUp();

        $this->businessOwner = BusinessOwner::factory()->create();
        $this->session = DiscoverySession::factory()->create([
            'business_owner_id' => $this->businessOwner->id,
            'current_phase' => DiscoveryPhase::Phase4,
        ]);

        $category = TaxonomyCategory::factory()->create();
        $this->niche = TaxonomyNiche::factory()->create([
            'taxonomy_category_id' => $category->id,
            'name' => ['en' => 'Barber shop', 'bg' => 'Бръснарница'],
        ]);

        $this->session->answers()->create([
            'phase' => DiscoveryPhase::Phase1->value,
            'field_key' => 'niche_id',
            'value' => $this->niche->id,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $brief  null = omit the field
     * @return array<string, mixed>
     */
    private function payload(?array $brief): array
    {
        $card = fn (string $title) => [
            'title' => $title,
            'summary' => 'One-line value.',
            'features' => ['Feature one', 'Feature two', 'Feature three'],
            'rationale' => 'Grounded in your context.',
            'tags' => ['engagement'],
            'saas_eligible' => false,
            'related_catalog_key' => null,
        ];

        $payload = ['suggestions' => [$card('Play A'), $card('Play B'), $card('Play C')]];

        if ($brief !== null) {
            $payload = ['brief' => $brief] + $payload;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function groundedBrief(): array
    {
        return [
            'paragraph' => 'Your barber shop already has loyal regulars — the gap is that new clients have nowhere to look you up online.',
            'bullets' => ['Before-and-after cuts will do more for you than promotional graphics.'],
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

    private function postSuggest(string $routeName, array $params = [])
    {
        $referralToken = ReferralToken::factory()->create(['business_owner_id' => $this->businessOwner->id]);

        return $this->withSession([
            'referral_token_id' => $referralToken->id,
            'business_owner_id' => $this->businessOwner->id,
            'referral_confirmed_'.$referralToken->id => true,
        ])->postJson(route($routeName, $params));
    }

    public function test_grounded_brief_is_returned_and_persisted_as_shown(): void
    {
        $this->fakeAiClient(true, json_encode($this->payload($this->groundedBrief())));

        $response = $this->postSuggest('discovery.suggest.content_social');

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonPath('brief.paragraph', $this->groundedBrief()['paragraph']);

        $this->assertDatabaseHas('advisory_briefs', [
            'business_owner_id' => $this->businessOwner->id,
            'phase' => DiscoveryPhase::Phase4->value,
            'module' => null,
            'verdict' => 'shown',
            'drop_reason' => null,
            'prompt_version' => 2,
        ]);
    }

    public function test_platitude_brief_is_dropped_but_cards_still_return(): void
    {
        $brief = $this->groundedBrief();
        $brief['paragraph'] .= ' Remember: consistency is key.';

        $this->fakeAiClient(true, json_encode($this->payload($brief)));

        $response = $this->postSuggest('discovery.suggest.content_social');

        $response->assertJsonPath('status', 'ok');
        $response->assertJsonCount(3, 'suggestions');
        $response->assertJsonPath('brief', null);

        $this->assertDatabaseHas('advisory_briefs', [
            'verdict' => 'dropped',
            'drop_reason' => 'platitude',
        ]);
    }

    public function test_overlong_brief_is_dropped(): void
    {
        $brief = $this->groundedBrief();
        $brief['paragraph'] = 'Your barber shop needs attention. '.str_repeat('More words about many things. ', 40);

        $this->fakeAiClient(true, json_encode($this->payload($brief)));

        $this->postSuggest('discovery.suggest.content_social')->assertJsonPath('brief', null);

        $this->assertDatabaseHas('advisory_briefs', ['drop_reason' => 'too_long']);
    }

    public function test_brief_with_too_many_bullets_is_dropped(): void
    {
        $brief = $this->groundedBrief();
        $brief['bullets'] = array_fill(0, 5, 'A short bullet about your barber shop.');

        $this->fakeAiClient(true, json_encode($this->payload($brief)));

        $this->postSuggest('discovery.suggest.content_social')->assertJsonPath('brief', null);

        $this->assertDatabaseHas('advisory_briefs', ['drop_reason' => 'too_many_bullets']);
    }

    public function test_ungrounded_brief_is_dropped(): void
    {
        $brief = [
            'paragraph' => 'An online presence helps you reach new people and grow over time.',
            'bullets' => ['Good photos help.', 'Replying to reviews builds trust.'],
        ];

        $this->fakeAiClient(true, json_encode($this->payload($brief)));

        $this->postSuggest('discovery.suggest.content_social')->assertJsonPath('brief', null);

        $this->assertDatabaseHas('advisory_briefs', ['drop_reason' => 'ungrounded']);
    }

    public function test_missing_brief_is_recorded_as_dropped_missing(): void
    {
        $this->fakeAiClient(true, json_encode($this->payload(null)));

        $response = $this->postSuggest('discovery.suggest.content_social');

        $response->assertJsonPath('status', 'ok');
        $response->assertJsonPath('brief', null);

        $this->assertDatabaseHas('advisory_briefs', [
            'verdict' => 'dropped',
            'drop_reason' => 'missing',
            'brief' => null,
        ]);
    }

    public function test_growth_brief_is_persisted_per_module(): void
    {
        $this->fakeAiClient(true, json_encode($this->payload($this->groundedBrief())));

        $this->postSuggest('discovery.suggest.growth', ['module' => 'marketing'])
            ->assertJsonPath('brief.paragraph', $this->groundedBrief()['paragraph']);

        $this->assertDatabaseHas('advisory_briefs', [
            'phase' => DiscoveryPhase::Phase5->value,
            'module' => 'marketing',
            'verdict' => 'shown',
        ]);
    }

    public function test_services_endpoint_ignores_a_stray_brief(): void
    {
        $payload = $this->payload($this->groundedBrief());
        $payload['suggestions'][0]['related_catalog_key'] = null;

        $this->fakeAiClient(true, json_encode($payload));

        $response = $this->postSuggest('discovery.suggest.services');

        $response->assertJsonPath('status', 'ok');
        $response->assertJsonPath('brief', null);
        $this->assertDatabaseCount('advisory_briefs', 0);
    }

    public function test_failed_card_validation_records_no_brief(): void
    {
        // Only 2 cards → §7.4 violation; the brief must not be processed.
        $payload = $this->payload($this->groundedBrief());
        $payload['suggestions'] = array_slice($payload['suggestions'], 0, 2);

        $this->fakeAiClient(true, json_encode($payload));

        $this->postSuggest('discovery.suggest.content_social')->assertJsonPath('status', 'unavailable');

        $this->assertDatabaseCount('advisory_briefs', 0);
    }

    public function test_selected_exemplars_are_injected_and_persisted_for_reproducibility(): void
    {
        $exemplar = BriefExemplar::create([
            'context_tags' => ['barber'],
            'dcp_excerpt' => 'Neighbourhood barber shop with loyal regulars.',
            'exemplar_brief' => ['paragraph' => 'Exemplar paragraph.', 'bullets' => ['Exemplar bullet.']],
            'active' => true,
            'version' => 3,
        ]);
        BriefExemplar::create([
            'context_tags' => ['restaurant'],
            'dcp_excerpt' => 'Family restaurant.',
            'exemplar_brief' => ['paragraph' => 'Other paragraph.', 'bullets' => []],
            'active' => true,
            'version' => 1,
        ]);

        $assembler = app(ContentSocialSuggestionAssembler::class);
        $blocks = $assembler->assemble($this->businessOwner, $this->session);

        $exemplarBlocks = array_values(array_filter($blocks, fn ($b) => $b->type === ContextBlockType::BriefExemplars));
        $this->assertCount(1, $exemplarBlocks);
        $this->assertStringContainsString('Neighbourhood barber shop', $exemplarBlocks[0]->content);

        // The tag-matched exemplar ranks first in the memoized selection.
        $this->assertSame($exemplar->id, $assembler->selectedExemplars()->first()?->id);

        // End-to-end: the id+version set lands on the advisory_briefs row.
        $this->fakeAiClient(true, json_encode($this->payload($this->groundedBrief())));
        $this->postSuggest('discovery.suggest.content_social')->assertJsonPath('status', 'ok');

        $row = AdvisoryBrief::query()->latest('id')->first();
        $this->assertNotNull($row);
        $this->assertContains(['id' => $exemplar->id, 'version' => 3], $row->exemplars);
    }

    public function test_admin_can_view_the_readonly_exemplar_library(): void
    {
        BriefExemplar::create([
            'context_tags' => ['barber'],
            'dcp_excerpt' => 'Neighbourhood barber shop.',
            'exemplar_brief' => ['paragraph' => 'P.', 'bullets' => ['B.']],
            'active' => true,
            'version' => 1,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.brief-exemplars.index'))
            ->assertOk();
    }

    public function test_inactive_exemplars_are_never_selected(): void
    {
        BriefExemplar::create([
            'context_tags' => ['barber'],
            'dcp_excerpt' => 'Inactive exemplar.',
            'exemplar_brief' => ['paragraph' => 'P.', 'bullets' => []],
            'active' => false,
            'version' => 1,
        ]);

        $assembler = app(ContentSocialSuggestionAssembler::class);
        $blocks = $assembler->assemble($this->businessOwner, $this->session);

        $exemplarBlocks = array_values(array_filter($blocks, fn ($b) => $b->type === ContextBlockType::BriefExemplars));
        $this->assertSame('', $exemplarBlocks[0]->content);
        $this->assertTrue($assembler->selectedExemplars()->isEmpty());
    }
}
