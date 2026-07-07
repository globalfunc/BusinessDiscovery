<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PipelineStage;
use App\Enums\ReferralTokenState;
use App\Http\Controllers\Controller;
use App\Models\ActivityEvent;
use App\Models\BusinessOwner;
use App\Models\ReferralToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReferralTokenController extends Controller
{
    public function store(BusinessOwner $businessOwner): RedirectResponse
    {
        [$token, $plain] = ReferralToken::generateFor($businessOwner);

        ActivityEvent::create([
            'business_owner_id' => $businessOwner->id,
            'type' => 'referral_link_generated',
            'payload' => ['referral_token_id' => $token->id],
        ]);

        return back()->with([
            'success' => 'Referral link generated.',
            'plainReferralUrl' => url('/r/'.$plain),
        ]);
    }

    public function regenerate(BusinessOwner $businessOwner, ReferralToken $referralToken): RedirectResponse
    {
        $referralToken->update(['state' => ReferralTokenState::Revoked, 'revoked_at' => now()]);

        [$token, $plain] = ReferralToken::generateFor($businessOwner);

        ActivityEvent::create([
            'business_owner_id' => $businessOwner->id,
            'type' => 'referral_link_regenerated',
            'payload' => ['old_referral_token_id' => $referralToken->id, 'new_referral_token_id' => $token->id],
        ]);

        return back()->with([
            'success' => 'Referral link regenerated.',
            'plainReferralUrl' => url('/r/'.$plain),
        ]);
    }

    public function revoke(BusinessOwner $businessOwner, ReferralToken $referralToken): RedirectResponse
    {
        $referralToken->update(['state' => ReferralTokenState::Revoked, 'revoked_at' => now()]);

        ActivityEvent::create([
            'business_owner_id' => $businessOwner->id,
            'type' => 'referral_link_revoked',
            'payload' => ['referral_token_id' => $referralToken->id],
        ]);

        return back()->with('success', 'Referral link revoked.');
    }

    public function markSent(BusinessOwner $businessOwner, ReferralToken $referralToken): RedirectResponse
    {
        $referralToken->update(['state' => ReferralTokenState::Sent, 'sent_at' => now()]);

        if ($businessOwner->current_stage === PipelineStage::Prospect) {
            $businessOwner->update(['current_stage' => PipelineStage::ReferralSent]);
        }

        ActivityEvent::create([
            'business_owner_id' => $businessOwner->id,
            'type' => 'referral_link_marked_sent',
            'payload' => ['referral_token_id' => $referralToken->id],
        ]);

        return back()->with('success', 'Referral link marked as sent.');
    }

    public function setExpiry(Request $request, BusinessOwner $businessOwner, ReferralToken $referralToken): RedirectResponse
    {
        $data = $request->validate([
            'expires_at' => ['required', 'date', 'after:now'],
        ]);

        $referralToken->update(['expires_at' => $data['expires_at']]);

        return back()->with('success', 'Referral link expiry updated.');
    }
}
