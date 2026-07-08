<?php

namespace App\Http\Controllers\Discovery;

use App\Http\Controllers\Controller;
use App\Models\BusinessOwner;
use App\Models\DiscoverySession;
use App\Services\Ai\Tools\Spec\SpecAmender;
use App\Services\Ai\Tools\Spec\SpecCompiler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Synchronous spec endpoints for the Review screen (§3.8). Compile is fired
 * by the client on Review open when no version exists yet, and by the
 * Regenerate affordance; it always yields a document (AI or §7.7 fallback),
 * so the response is always "ok". Amend can genuinely fail — then no version
 * is written, status "unavailable" comes back, and the current spec stays
 * live (never blocks submission).
 */
class SpecController extends Controller
{
    public function compile(Request $request, SpecCompiler $compiler): JsonResponse
    {
        [$businessOwner, $session] = $this->context($request);

        $document = $compiler->compile($businessOwner, $session);

        return response()->json([
            'status' => 'ok',
            'document' => $document->toDiscoveryArray(),
        ]);
    }

    public function amend(Request $request, SpecAmender $amender): JsonResponse
    {
        $data = $request->validate([
            'instruction' => ['required', 'string', 'max:2000'],
        ]);

        [$businessOwner, $session] = $this->context($request);

        abort_if($session->latestSpecDocument === null, 422, 'There is no specification to amend yet.');

        $document = $amender->amend($businessOwner, $session, $data['instruction']);

        if ($document === null) {
            return response()->json(['status' => 'unavailable']);
        }

        return response()->json([
            'status' => 'ok',
            'document' => $document->toDiscoveryArray(),
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
