<?php

namespace App\Services\Ai;

use App\Enums\AiCallStatus;
use App\Models\AiCall;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The §7.7 pre-flight check, called once per AiClient::call() before any
 * network dispatch. Order: rate limit first (cheap, in-memory, always
 * enforced — it exists purely to blunt referral-link abuse), then the token
 * budget (global monthly cap, then per-BO cap with its admin override).
 * A non-null return blocks the call; AiClient turns that straight into an
 * unsuccessful AiCallResult, which every tool already treats as "fall back"
 * (presets / empty DCP / deterministic spec renderer) — this class never
 * needs to know which tool it's gating.
 */
class BudgetGate
{
    public function check(AiCallRequest $request): ?AiCallStatus
    {
        if ($this->rateLimited($request)) {
            return AiCallStatus::RateLimited;
        }

        if ($this->budgetExhausted($request)) {
            if (config('ai.budget_mode', 'hard') !== 'hard') {
                Log::warning('AI token budget exceeded (soft-warn mode: call allowed through).', [
                    'tool' => $request->tool,
                    'business_owner_id' => $request->businessOwner?->id,
                ]);

                return null;
            }

            return AiCallStatus::BudgetExhausted;
        }

        return null;
    }

    /**
     * Max N calls/minute per BO session (§7.7, default 6) — applies even
     * without a BO on the request (keyed "anon") since a bare token/session
     * is still the abuse surface the cap targets.
     */
    private function rateLimited(AiCallRequest $request): bool
    {
        $key = 'ai-calls:'.($request->businessOwner?->id ?? 'anon');
        $max = (int) config('ai.rate_limit_per_minute', 6);

        if (RateLimiter::tooManyAttempts($key, $max)) {
            return true;
        }

        RateLimiter::hit($key, 60);

        return false;
    }

    private function budgetExhausted(AiCallRequest $request): bool
    {
        $globalCap = config('ai.global_monthly_token_cap');

        if ($globalCap !== null && $this->tokensUsed(since: now()->startOfMonth()) >= $globalCap) {
            return true;
        }

        $businessOwner = $request->businessOwner;

        if ($businessOwner === null) {
            return false;
        }

        $perBoCap = $businessOwner->ai_token_cap ?? config('ai.per_bo_token_cap');

        return $perBoCap !== null && $this->tokensUsed(businessOwnerId: $businessOwner->id) >= $perBoCap;
    }

    private function tokensUsed(?int $businessOwnerId = null, ?Carbon $since = null): int
    {
        $query = AiCall::query();

        if ($businessOwnerId !== null) {
            $query->where('business_owner_id', $businessOwnerId);
        }

        if ($since !== null) {
            $query->where('created_at', '>=', $since);
        }

        return (int) $query->sum('input_tokens') + (int) $query->sum('output_tokens');
    }
}
