<?php

namespace Tests\Feature;

use App\Enums\AdvisoryBriefVerdict;
use App\Enums\AiCallStatus;
use App\Enums\DiscoveryPhase;
use App\Models\AdvisoryBrief;
use App\Models\AiCall;
use App\Models\BriefExemplar;
use App\Models\BusinessOwner;
use App\Models\DiscoverySession;
use App\Models\ReferralToken;
use App\Models\User;
use App\Services\Ai\AiCallRequest;
use App\Services\Ai\AiCallResult;
use App\Services\Ai\AiClient;
use App\Services\Ai\AiSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * S5.7 — the brief-quality calibration layer: the brief.grade LLM judge and
 * async-reveal endpoint (log_only vs enforce semantics, idempotency,
 * ownership), the admin review/label surface, the rubric editor's version
 * bumping, the exemplar editor's version bumping, and the offline eval
 * harness.
 */
class BriefGradingTest extends TestCase
{
    use RefreshDatabase;

    private BusinessOwner $businessOwner;

    private DiscoverySession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->businessOwner = BusinessOwner::factory()->create();
        $this->session = DiscoverySession::factory()->create([
            'business_owner_id' => $this->businessOwner->id,
            'current_phase' => DiscoveryPhase::Phase4,
        ]);
    }

    private function makeBrief(array $attributes = []): AdvisoryBrief
    {
        return AdvisoryBrief::create([
            'business_owner_id' => $this->businessOwner->id,
            'phase' => DiscoveryPhase::Phase4->value,
            'module' => null,
            'brief' => [
                'paragraph' => 'Your barber shop already has loyal regulars — the gap is online visibility.',
                'bullets' => ['Before-and-after cuts beat promotional graphics.'],
            ],
            'verdict' => AdvisoryBriefVerdict::Shown,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, int>  $scores  dimension key => 1-5
     */
    private function judgeJson(array $scores = ['specificity' => 4, 'insight' => 4, 'non_deliverable' => 5, 'credibility' => 4]): string
    {
        return json_encode([
            'scores' => array_map(fn (int $score) => ['score' => $score, 'reason' => 'Because.'], $scores),
        ]);
    }

    private function fakeAiClient(bool $successful, ?string $text, int $times = 1): void
    {
        $businessOwner = $this->businessOwner;

        $this->mock(AiClient::class, function ($mock) use ($successful, $text, $times, $businessOwner) {
            $mock->shouldReceive('call')
                ->times($times)
                ->andReturnUsing(fn (AiCallRequest $request) => new AiCallResult(
                    successful: $successful,
                    text: $text,
                    aiCall: AiCall::factory()->create([
                        'business_owner_id' => $businessOwner->id,
                        'tool' => $request->tool,
                        'status' => $successful ? AiCallStatus::Success : AiCallStatus::Failed,
                    ]),
                ));
        });
    }

    private function postReveal(AdvisoryBrief $brief, ?BusinessOwner $owner = null)
    {
        $owner ??= $this->businessOwner;
        $referralToken = ReferralToken::factory()->create(['business_owner_id' => $owner->id]);

        return $this->withSession([
            'referral_token_id' => $referralToken->id,
            'business_owner_id' => $owner->id,
            'referral_confirmed_'.$referralToken->id => true,
        ])->postJson(route('discovery.suggest.brief_reveal', $brief));
    }

    private function setRubric(string $mode, float $threshold = 3.5): void
    {
        $rubric = config('ai.brief_rubric');
        $rubric['mode'] = $mode;
        $rubric['threshold'] = $threshold;
        app(AiSettings::class)->setBriefRubric($rubric);
    }

    public function test_log_only_reveals_the_brief_and_persists_scores(): void
    {
        $this->fakeAiClient(true, $this->judgeJson());
        $brief = $this->makeBrief();

        $response = $this->postReveal($brief);

        $response->assertOk();
        $response->assertJsonPath('brief.paragraph', $brief->brief['paragraph']);

        $brief->refresh();
        // Weighted mean: (4*.3 + 4*.3 + 5*.2 + 4*.2) = 4.2
        $this->assertSame(4.2, $brief->composite);
        $this->assertSame(4, $brief->scores['specificity']['score']);
        $this->assertSame(1, $brief->rubric_version);
        $this->assertNotNull($brief->judge_model);
        $this->assertSame(AdvisoryBriefVerdict::Shown, $brief->verdict);

        // The judge logged its own ai_calls row under its own tool key.
        $this->assertDatabaseHas('ai_calls', ['tool' => 'brief.grade', 'business_owner_id' => $this->businessOwner->id]);
    }

    public function test_log_only_reveals_even_when_composite_is_below_threshold(): void
    {
        $this->fakeAiClient(true, $this->judgeJson(['specificity' => 1, 'insight' => 1, 'non_deliverable' => 2, 'credibility' => 2]));
        $brief = $this->makeBrief();

        $this->postReveal($brief)->assertJsonPath('brief.paragraph', $brief->brief['paragraph']);

        $brief->refresh();
        $this->assertSame(1.4, $brief->composite);
        $this->assertSame(AdvisoryBriefVerdict::Shown, $brief->verdict);
    }

    public function test_log_only_reveals_when_the_judge_call_fails(): void
    {
        $this->fakeAiClient(false, null);
        $brief = $this->makeBrief();

        $this->postReveal($brief)->assertJsonPath('brief.paragraph', $brief->brief['paragraph']);

        $brief->refresh();
        $this->assertNull($brief->composite);
        $this->assertSame(AdvisoryBriefVerdict::Shown, $brief->verdict);
    }

    public function test_enforce_hides_a_below_threshold_brief_as_hidden_low_value(): void
    {
        $this->setRubric('enforce');
        $this->fakeAiClient(true, $this->judgeJson(['specificity' => 2, 'insight' => 2, 'non_deliverable' => 3, 'credibility' => 3]));
        $brief = $this->makeBrief();

        $this->postReveal($brief)->assertJsonPath('brief', null);

        $brief->refresh();
        $this->assertSame(AdvisoryBriefVerdict::HiddenLowValue, $brief->verdict);
        $this->assertSame(2.4, $brief->composite);
        // The payload stays persisted for the admin review loop.
        $this->assertNotNull($brief->brief);
    }

    public function test_enforce_reveals_a_passing_brief(): void
    {
        $this->setRubric('enforce');
        $this->fakeAiClient(true, $this->judgeJson());
        $brief = $this->makeBrief();

        $this->postReveal($brief)->assertJsonPath('brief.paragraph', $brief->brief['paragraph']);

        $this->assertSame(AdvisoryBriefVerdict::Shown, $brief->refresh()->verdict);
    }

    public function test_enforce_hides_the_brief_when_the_judge_call_fails(): void
    {
        $this->setRubric('enforce');
        $this->fakeAiClient(false, null);
        $brief = $this->makeBrief();

        $this->postReveal($brief)->assertJsonPath('brief', null);

        $brief->refresh();
        $this->assertSame(AdvisoryBriefVerdict::HiddenLowValue, $brief->verdict);
        $this->assertSame('grade_failed', $brief->drop_reason);
    }

    public function test_enforce_hides_the_brief_when_the_judge_returns_garbage(): void
    {
        $this->setRubric('enforce');
        $this->fakeAiClient(true, 'not json at all');
        $brief = $this->makeBrief();

        $this->postReveal($brief)->assertJsonPath('brief', null);

        $this->assertSame(AdvisoryBriefVerdict::HiddenLowValue, $brief->refresh()->verdict);
    }

    public function test_reveal_is_idempotent_once_graded(): void
    {
        // times(1): the second request must not trigger a second judge call.
        $this->fakeAiClient(true, $this->judgeJson(), times: 1);
        $brief = $this->makeBrief();

        $this->postReveal($brief)->assertOk();
        $this->postReveal($brief)->assertJsonPath('brief.paragraph', $brief->brief['paragraph']);
    }

    public function test_reveal_rejects_another_owners_brief(): void
    {
        $this->fakeAiClient(true, $this->judgeJson(), times: 0);
        $brief = $this->makeBrief();

        $other = BusinessOwner::factory()->create();
        DiscoverySession::factory()->create(['business_owner_id' => $other->id]);

        $this->postReveal($brief, $other)->assertNotFound();
    }

    public function test_reveal_returns_null_for_a_dropped_brief_without_grading(): void
    {
        $this->fakeAiClient(true, $this->judgeJson(), times: 0);
        $brief = $this->makeBrief(['verdict' => AdvisoryBriefVerdict::Dropped, 'drop_reason' => 'platitude']);

        $this->postReveal($brief)->assertJsonPath('brief', null);
    }

    public function test_admin_can_list_filter_and_view_briefs(): void
    {
        $admin = User::factory()->create();
        $shown = $this->makeBrief(['composite' => 4.2, 'scores' => ['specificity' => ['score' => 4, 'reason' => 'r']]]);
        $this->makeBrief(['verdict' => AdvisoryBriefVerdict::HiddenLowValue, 'composite' => 2.1]);

        $this->actingAs($admin)->get(route('admin.advisory-briefs.index'))->assertOk();
        $this->actingAs($admin)
            ->get(route('admin.advisory-briefs.index', ['verdict' => 'hidden_low_value', 'max_composite' => 3]))
            ->assertOk();
        $this->actingAs($admin)->get(route('admin.advisory-briefs.show', $shown))->assertOk();
    }

    public function test_admin_can_label_and_clear_a_brief(): void
    {
        $admin = User::factory()->create();
        $brief = $this->makeBrief();

        $this->actingAs($admin)->patch(route('admin.advisory-briefs.label', $brief), ['label' => 'good']);
        $this->assertSame('good', $brief->refresh()->label);

        $this->actingAs($admin)->patch(route('admin.advisory-briefs.label', $brief), ['label' => null]);
        $this->assertNull($brief->refresh()->label);

        $this->actingAs($admin)
            ->patch(route('admin.advisory-briefs.label', $brief), ['label' => 'meh'])
            ->assertSessionHasErrors('label');
    }

    public function test_rubric_editor_bumps_version_only_when_dimensions_change(): void
    {
        $admin = User::factory()->create();
        $settings = app(AiSettings::class);
        $dimensions = config('ai.brief_rubric.dimensions');

        // Threshold/mode-only change: version stays.
        $this->actingAs($admin)->patch(route('admin.advisory-briefs.rubric.update'), [
            'mode' => 'enforce',
            'threshold' => 4.0,
            'dimensions' => $dimensions,
        ]);

        $rubric = $settings->briefRubric();
        $this->assertSame(1, $rubric['version']);
        $this->assertSame('enforce', $rubric['mode']);
        // JSON round-trip stores 4.0 as int 4 — compare loosely.
        $this->assertEquals(4.0, $rubric['threshold']);

        // Weight change: version bumps.
        $dimensions[0]['weight'] = 0.5;
        $this->actingAs($admin)->patch(route('admin.advisory-briefs.rubric.update'), [
            'mode' => 'enforce',
            'threshold' => 4.0,
            'dimensions' => $dimensions,
        ]);

        $this->assertSame(2, $settings->briefRubric()['version']);
    }

    public function test_exemplar_editor_bumps_version_on_content_change_only(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.brief-exemplars.store'), [
            'context_tags' => ['barber'],
            'dcp_excerpt' => 'Neighbourhood barber shop.',
            'paragraph' => 'Exemplar paragraph about the barber shop.',
            'bullets' => ['A bullet.'],
            'quality_notes' => 'Gold.',
            'active' => true,
        ]);

        $exemplar = BriefExemplar::query()->firstOrFail();
        $this->assertSame(1, $exemplar->version);

        // Toggling active only: no version bump.
        $this->actingAs($admin)->patch(route('admin.brief-exemplars.update', $exemplar), [
            'context_tags' => ['barber'],
            'dcp_excerpt' => 'Neighbourhood barber shop.',
            'paragraph' => 'Exemplar paragraph about the barber shop.',
            'bullets' => ['A bullet.'],
            'quality_notes' => 'Gold.',
            'active' => false,
        ]);
        $this->assertSame(1, $exemplar->refresh()->version);
        $this->assertFalse($exemplar->active);

        // Rewriting the brief: version bumps.
        $this->actingAs($admin)->patch(route('admin.brief-exemplars.update', $exemplar), [
            'context_tags' => ['barber'],
            'dcp_excerpt' => 'Neighbourhood barber shop.',
            'paragraph' => 'A sharper exemplar paragraph.',
            'bullets' => ['A bullet.'],
            'active' => false,
        ]);
        $this->assertSame(2, $exemplar->refresh()->version);
    }

    public function test_eval_harness_reports_judge_calibration_against_the_gold_set(): void
    {
        Storage::fake();

        // The mocked judge scores every brief identically (4/4/5/4 → 4.2),
        // so pass-expected cases match and fail-expected cases are misses —
        // which exercises both branches of the report without a live API.
        $this->mock(AiClient::class, function ($mock) {
            $mock->shouldReceive('call')->andReturnUsing(fn (AiCallRequest $request) => new AiCallResult(
                successful: true,
                text: $this->judgeJson(),
                aiCall: AiCall::factory()->create(['tool' => $request->tool]),
            ));
        });

        $this->artisan('briefs:eval')
            ->expectsOutputToContain('Judge calibration: 3/5 cases match the human grade.')
            ->assertExitCode(0);

        $this->assertTrue(Storage::exists('brief-eval/last-run.json'));
    }
}
