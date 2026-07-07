<?php

namespace App\Http\Controllers\Referral;

use App\Enums\PipelineStage;
use App\Enums\ReferralTokenState;
use App\Http\Controllers\Controller;
use App\Models\ActivityEvent;
use App\Models\BusinessOwner;
use App\Models\ReferralToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ReferralLandingController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        /** @var ReferralToken $referralToken */
        $referralToken = $request->attributes->get('referralToken');
        /** @var BusinessOwner $businessOwner */
        $businessOwner = $request->attributes->get('businessOwner');

        $confirmed = (bool) session('referral_confirmed_'.$referralToken->id);

        return Inertia::render('Referral/Landing', [
            'token' => $token,
            'confirmed' => $confirmed,
            'businessOwner' => [
                'name' => $businessOwner->name,
                'company' => $businessOwner->company,
                'logo_path' => $businessOwner->logo_path ? Storage::disk('public')->url($businessOwner->logo_path) : null,
                'greeting_override' => $businessOwner->greeting_override,
                'language' => $businessOwner->language?->value ?? 'en',
            ],
        ]);
    }

    public function confirm(Request $request, string $token): RedirectResponse
    {
        /** @var ReferralToken $referralToken */
        $referralToken = $request->attributes->get('referralToken');
        /** @var BusinessOwner $businessOwner */
        $businessOwner = $request->attributes->get('businessOwner');

        $request->validate([
            'company' => ['required', 'string', 'max:255'],
        ]);

        session(['referral_confirmed_'.$referralToken->id => true]);

        if ($referralToken->state !== ReferralTokenState::Submitted) {
            $referralToken->update(['state' => ReferralTokenState::InProgress]);
        }

        if ($businessOwner->current_stage !== PipelineStage::DiscoveryComplete) {
            $businessOwner->update(['current_stage' => PipelineStage::DiscoveryInProgress]);
        }

        ActivityEvent::create([
            'business_owner_id' => $businessOwner->id,
            'type' => 'referral_company_confirmed',
            'payload' => ['referral_token_id' => $referralToken->id],
        ]);

        return redirect()->route('referral.show', $token);
    }
}
