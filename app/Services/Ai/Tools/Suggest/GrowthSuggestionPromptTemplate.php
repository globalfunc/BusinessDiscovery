<?php

namespace App\Services\Ai\Tools\Suggest;

use App\Services\Ai\Contracts\PromptTemplate;
use App\Services\Ai\VendorPolicy;

/**
 * System policy (§7.3 block 1) for suggest.growth (Phase 5). One template
 * serves all four modules (notifications, marketing/retention, lead-gen,
 * admin/ops) — the module focus is carried in the per-call task instruction.
 * Same grounded / JSON-only spine as the other suggest.* tools, with the shared
 * §7.6.1 vendor rule injected from VendorPolicy.
 */
class GrowthSuggestionPromptTemplate implements PromptTemplate
{
    public function key(): string
    {
        return 'suggest.growth';
    }

    public function version(): int
    {
        return 2;
    }

    public function systemPrompt(): string
    {
        $vendorRule = VendorPolicy::systemRule();

        return <<<PROMPT
You are a growth & operations consultant for a digital agency, proposing ideas for one specific area of a business owner's operation. You produce structured Suggestion Cards — never a chat reply, never prose outside the JSON.

Rules:
- {$vendorRule}
- Stay within the area named in the task instruction (e.g. only notifications, only marketing & retention). Do not stray into other modules.
- Ground every card in what the owner told us — their niche, the sub-options they already toggled in this area, and their broader context. Tie each card's rationale to their own situation.
- Each card is a cohesive, actionable idea; its features are concrete capabilities or steps. Make cards distinct from one another.
- If the owner wrote in Bulgarian, write every card's title, summary, features, and rationale in Bulgarian; tags stay short English snake_case slugs.
- When the task asks for an advisory "brief", it is a short note of general direction and insight for this owner — it is NOT ready-to-publish copy, NOT captions or scripts, NOT a step-by-step action plan. It must be specific to their situation; omit it rather than write something any business could receive.
- Respond with a single JSON object only. No markdown fences, no commentary before or after.
PROMPT;
    }
}
