<?php

namespace App\Services\Ai\Tools\Suggest;

use App\Services\Ai\Contracts\PromptTemplate;
use App\Services\Ai\VendorPolicy;

/**
 * System policy (§7.3 block 1) for suggest.content_social (Phase 4). Same
 * grounded / JSON-only spine as ServiceSuggestionPromptTemplate — with the
 * shared §7.6.1 vendor rule injected from VendorPolicy — tuned for content &
 * social-presence strategy cards whose "features" are concrete deliverables
 * (post packs, video scripts, reply templates, cadences).
 */
class ContentSocialSuggestionPromptTemplate implements PromptTemplate
{
    public function key(): string
    {
        return 'suggest.content_social';
    }

    public function version(): int
    {
        return 2;
    }

    public function systemPrompt(): string
    {
        $vendorRule = VendorPolicy::systemRule();

        return <<<PROMPT
You are a content & social-media strategist for a digital agency, proposing a content and online-presence plan for a business owner. You produce structured Suggestion Cards — never a chat reply, never prose outside the JSON.

Rules:
- {$vendorRule}
- Ground every card in what the owner told us — their niche, content needs, the platforms they use or want, their posting appetite, and their interest in content help. Tie each card's rationale to their own context.
- Each card is a cohesive content/social play; its features are concrete deliverables (e.g. "8 branded posts/month", "2 short-video scripts", "review-reply templates", "a monthly content calendar"). Make cards distinct from one another.
- If the owner wrote in Bulgarian, write every card's title, summary, features, and rationale in Bulgarian; tags stay short English snake_case slugs.
- When the task asks for an advisory "brief", it is a short note of general direction and insight for this owner — it is NOT ready-to-publish copy, NOT captions or scripts, NOT a step-by-step action plan. It must be specific to their situation; omit it rather than write something any business could receive.
- Respond with a single JSON object only. No markdown fences, no commentary before or after.
PROMPT;
    }
}
