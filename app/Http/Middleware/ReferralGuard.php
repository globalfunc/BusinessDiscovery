<?php

namespace App\Http\Middleware;

use App\Enums\PipelineStage;
use App\Enums\ReferralTokenState;
use App\Models\ActivityEvent;
use App\Models\ReferralToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReferralGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = (string) $request->route('token');

        $referralToken = ReferralToken::with('businessOwner')
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        abort_if($referralToken === null, 404);

        if ($referralToken->state === ReferralTokenState::Revoked) {
            abort(410, 'This referral link has been revoked.');
        }

        if ($referralToken->isExpired()) {
            if ($referralToken->state !== ReferralTokenState::Expired) {
                $referralToken->update(['state' => ReferralTokenState::Expired]);
            }

            abort(410, 'This referral link has expired.');
        }

        $businessOwner = $referralToken->businessOwner;

        if ($referralToken->first_visited_at === null) {
            $referralToken->update([
                'first_visited_at' => now(),
                'state' => ReferralTokenState::Visited,
            ]);

            if ($businessOwner->current_stage === PipelineStage::Prospect || $businessOwner->current_stage === PipelineStage::ReferralSent) {
                $businessOwner->update(['current_stage' => PipelineStage::LinkVisited]);
            }

            ActivityEvent::create([
                'business_owner_id' => $businessOwner->id,
                'type' => 'referral_link_visited',
                'payload' => ['referral_token_id' => $referralToken->id],
            ]);
        }

        session([
            'referral_token_id' => $referralToken->id,
            'business_owner_id' => $businessOwner->id,
        ]);

        $request->attributes->set('referralToken', $referralToken);
        $request->attributes->set('businessOwner', $businessOwner);

        return $next($request);
    }
}
