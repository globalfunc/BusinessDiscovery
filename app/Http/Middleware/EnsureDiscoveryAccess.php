<?php

namespace App\Http\Middleware;

use App\Models\BusinessOwner;
use App\Models\ReferralToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDiscoveryAccess
{
    /**
     * Authenticate discovery-flow requests using the session binding that
     * ReferralGuard established on the /r/{token} visit. These routes carry
     * no token in the URL, so we fall back to the session values.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $referralTokenId = $request->session()->get('referral_token_id');
        $businessOwnerId = $request->session()->get('business_owner_id');

        abort_if($referralTokenId === null || $businessOwnerId === null, 403);

        $referralToken = ReferralToken::find($referralTokenId);
        $businessOwner = BusinessOwner::find($businessOwnerId);

        abort_if($referralToken === null || $businessOwner === null, 403);
        abort_unless($referralToken->isUsable(), 410, 'This referral link is no longer active.');
        abort_unless($request->session()->get('referral_confirmed_'.$referralToken->id), 403);

        $request->attributes->set('referralToken', $referralToken);
        $request->attributes->set('businessOwner', $businessOwner);

        return $next($request);
    }
}
