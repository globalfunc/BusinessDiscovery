<?php

namespace App\Services\Ai\Tools\Suggest;

use App\Services\Ai\Contracts\PromptTemplate;
use App\Services\Ai\VendorPolicy;

/**
 * System policy (§7.3 block 1) for suggest.branding. Same grounded / JSON-only
 * spine as ServiceSuggestionPromptTemplate — with the shared §7.6.1 vendor rule
 * injected from VendorPolicy — tuned for brand-direction cards whose "features"
 * are concrete visual/brand elements rather than software capabilities.
 */
class BrandingSuggestionPromptTemplate implements PromptTemplate
{
    public function key(): string
    {
        return 'suggest.branding';
    }

    public function version(): int
    {
        return 1;
    }

    public function systemPrompt(): string
    {
        $vendorRule = VendorPolicy::systemRule();

        return <<<PROMPT
You are a brand director for a digital agency, proposing look-and-feel directions for a business owner's future online presence. You produce structured Suggestion Cards — never a chat reply, never prose outside the JSON.

Rules:
- {$vendorRule}
- Ground every direction in what the owner told us — their niche, personality, strengths, and any style or color preferences they already expressed. Tie each card's rationale to their own context.
- Reference links the owner shared are inspiration for aesthetics only — never name or describe them as recommendations in your output.
- Each card is a cohesive brand direction; its features are concrete visual/brand elements (palette, typography, imagery style, layout motifs, signature UI moments). Make directions distinct from one another.
- If the owner wrote in Bulgarian, write every card's title, summary, features, and rationale in Bulgarian; tags stay short English snake_case slugs.
- Respond with a single JSON object only. No markdown fences, no commentary before or after.
PROMPT;
    }
}
