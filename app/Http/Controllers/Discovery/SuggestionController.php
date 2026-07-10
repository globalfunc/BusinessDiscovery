<?php

namespace App\Http\Controllers\Discovery;

use App\Enums\AdvisoryBriefVerdict;
use App\Enums\DiscoveryPhase;
use App\Http\Controllers\Controller;
use App\Models\AdvisoryBrief;
use App\Models\BusinessOwner;
use App\Models\DiscoverySession;
use App\Services\Ai\Tools\Suggest\AbstractSuggestionAssembler;
use App\Services\Ai\Tools\Suggest\BrandingSuggestionAssembler;
use App\Services\Ai\Tools\Suggest\BriefContext;
use App\Services\Ai\Tools\Suggest\BriefGrader;
use App\Services\Ai\Tools\Suggest\ContentSocialSuggestionAssembler;
use App\Services\Ai\Tools\Suggest\GrowthSuggestionAssembler;
use App\Services\Ai\Tools\Suggest\ServiceSuggestionAssembler;
use App\Services\Ai\Tools\Suggest\SuggestionGenerator;
use App\Services\Ai\Tools\Suggest\SuggestionResult;
use App\Services\Ai\Tools\Suggest\SuggestionSchemaValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The synchronous ✨ suggestion endpoints (§7.1, 25s budget). Both delegate to
 * SuggestionGenerator, which never throws — on any failure/invalid output the
 * response degrades to niche suggestion_presets with status "unavailable", and
 * the phase never blocks (§7.7). The React panel renders preset cards with the
 * same Suggestion Card component as live AI cards.
 */
class SuggestionController extends Controller
{
    public function services(Request $request, SuggestionGenerator $generator, ServiceSuggestionAssembler $assembler): JsonResponse
    {
        [$businessOwner, $session] = $this->context($request);

        $result = $generator->generate(
            tool: 'suggest.services',
            assembler: $assembler,
            validator: new SuggestionSchemaValidator(requireCatalogKey: true),
            businessOwner: $businessOwner,
            discoverySession: $session,
        );

        return $this->respond($result, $assembler, $session);
    }

    public function branding(Request $request, SuggestionGenerator $generator, BrandingSuggestionAssembler $assembler): JsonResponse
    {
        [$businessOwner, $session] = $this->context($request);

        $result = $generator->generate(
            tool: 'suggest.branding',
            assembler: $assembler,
            validator: new SuggestionSchemaValidator(requireCatalogKey: false),
            businessOwner: $businessOwner,
            discoverySession: $session,
        );

        return $this->respond($result, $assembler, $session);
    }

    public function contentSocial(Request $request, SuggestionGenerator $generator, ContentSocialSuggestionAssembler $assembler): JsonResponse
    {
        [$businessOwner, $session] = $this->context($request);

        $result = $generator->generate(
            tool: 'suggest.content_social',
            assembler: $assembler,
            validator: new SuggestionSchemaValidator(requireCatalogKey: false),
            businessOwner: $businessOwner,
            discoverySession: $session,
            briefContext: new BriefContext(phase: DiscoveryPhase::Phase4),
        );

        return $this->respond($result, $assembler, $session);
    }

    /**
     * Phase 5 fires one call per enabled module (§3.6), so the module is a
     * route param and the assembler is scoped to it. An unknown module 404s
     * rather than falling through — the frontend only ever posts known keys.
     */
    public function growth(Request $request, string $module, SuggestionGenerator $generator, GrowthSuggestionAssembler $assembler): JsonResponse
    {
        abort_unless(in_array($module, GrowthSuggestionAssembler::modules(), true), 404);

        [$businessOwner, $session] = $this->context($request);

        $scoped = $assembler->withModule($module);

        $result = $generator->generate(
            tool: 'suggest.growth',
            assembler: $scoped,
            validator: new SuggestionSchemaValidator(requireCatalogKey: false),
            businessOwner: $businessOwner,
            discoverySession: $session,
            briefContext: new BriefContext(phase: DiscoveryPhase::Phase5, module: $module),
        );

        return $this->respond($result, $scoped, $session);
    }

    /**
     * S5.7 async-reveal: the second, per-brief request the panel fires after
     * the cards have rendered. Runs the brief.grade judge synchronously
     * (cheap model, tight cap) and returns the brief only if the rubric mode
     * lets it through — a failed or budget-gated grade in enforce mode simply
     * means no brief. Idempotent: an already-graded row returns its stored
     * outcome without a second judge call.
     */
    public function revealBrief(Request $request, AdvisoryBrief $advisoryBrief, BriefGrader $grader): JsonResponse
    {
        [$businessOwner] = $this->context($request);

        abort_unless($advisoryBrief->business_owner_id === $businessOwner->id, 404);

        if ($advisoryBrief->verdict !== AdvisoryBriefVerdict::Shown) {
            return response()->json(['brief' => null]);
        }

        if ($advisoryBrief->composite !== null) {
            return response()->json(['brief' => $advisoryBrief->brief]);
        }

        return response()->json(['brief' => $grader->gradeRecord($advisoryBrief)]);
    }

    private function respond(SuggestionResult $result, AbstractSuggestionAssembler $assembler, DiscoverySession $session): JsonResponse
    {
        if ($result->successful) {
            return response()->json([
                'status' => 'ok',
                'suggestions' => $result->cards,
                // S5.7 async-reveal: the brief itself is held back — the panel
                // posts to this URL after rendering the cards, the judge runs,
                // and the brief appears a beat later if it clears the gate.
                // Null when the tool doesn't produce a brief or the S5.6
                // deterministic gate dropped it; cards stand alone.
                'brief_url' => $result->pendingBriefId !== null
                    ? route('discovery.suggest.brief_reveal', ['advisoryBrief' => $result->pendingBriefId])
                    : null,
            ]);
        }

        // §7.7 graceful fallback: static niche presets (may be empty — the UI
        // then shows the "temporarily unavailable — continue manually" copy).
        return response()->json([
            'status' => 'unavailable',
            'suggestions' => $assembler->presetCards($session),
            'brief_url' => null,
        ]);
    }

    /**
     * @return array{0: BusinessOwner, 1: DiscoverySession}
     */
    private function context(Request $request): array
    {
        /** @var BusinessOwner $businessOwner */
        $businessOwner = $request->attributes->get('businessOwner');

        $session = DiscoverySession::where('business_owner_id', $businessOwner->id)->firstOrFail();

        abort_if($session->status === 'submitted', 403, 'This discovery has already been submitted.');

        return [$businessOwner, $session];
    }
}
