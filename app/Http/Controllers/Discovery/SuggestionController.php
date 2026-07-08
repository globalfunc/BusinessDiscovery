<?php

namespace App\Http\Controllers\Discovery;

use App\Http\Controllers\Controller;
use App\Models\BusinessOwner;
use App\Models\DiscoverySession;
use App\Services\Ai\Tools\Suggest\AbstractSuggestionAssembler;
use App\Services\Ai\Tools\Suggest\BrandingSuggestionAssembler;
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

    private function respond(SuggestionResult $result, AbstractSuggestionAssembler $assembler, DiscoverySession $session): JsonResponse
    {
        if ($result->successful) {
            return response()->json([
                'status' => 'ok',
                'suggestions' => $result->cards,
            ]);
        }

        // §7.7 graceful fallback: static niche presets (may be empty — the UI
        // then shows the "temporarily unavailable — continue manually" copy).
        return response()->json([
            'status' => 'unavailable',
            'suggestions' => $assembler->presetCards($session),
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

        return [$businessOwner, $session];
    }
}
