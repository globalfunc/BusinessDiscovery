<?php

namespace App\Http\Controllers\Discovery;

use App\Enums\DiscoveryPhase;
use App\Http\Controllers\Controller;
use App\Models\BusinessOwner;
use App\Models\DiscoverySession;
use App\Services\Ai\Tools\Dcp\DcpGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class IntakeController extends Controller
{
    /**
     * Phase 0 "Continue" (and the Phase 1 retry banner) land here: run
     * dcp.generate synchronously, then move on to Phase 1 regardless of the
     * outcome — DcpGenerator stores an empty profile on failure and the
     * flow must never block on AI (§3.9). Retries simply write the next
     * DCP version.
     */
    public function store(Request $request, DcpGenerator $generator): RedirectResponse
    {
        /** @var BusinessOwner $businessOwner */
        $businessOwner = $request->attributes->get('businessOwner');

        $session = DiscoverySession::where('business_owner_id', $businessOwner->id)->firstOrFail();

        abort_if($session->status === 'submitted', 403, 'This discovery has already been submitted.');

        $generator->generate($businessOwner, $session);

        if ($session->current_phase === DiscoveryPhase::Phase0) {
            $session->update(['current_phase' => DiscoveryPhase::Phase1]);
        }

        return redirect()->route('discovery.show', ['phase' => DiscoveryPhase::Phase1->value]);
    }
}
