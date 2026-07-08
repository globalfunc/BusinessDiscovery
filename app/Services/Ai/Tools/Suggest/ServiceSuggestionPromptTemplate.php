<?php

namespace App\Services\Ai\Tools\Suggest;

use App\Services\Ai\Contracts\PromptTemplate;

/**
 * System policy (§7.3 block 1) for suggest.services. The shared spine —
 * vendor neutrality (§7.6), grounding, language, JSON-only — is reused
 * verbatim by every suggest.* tool (S3.3's content/social + growth tools
 * should carry the same three-paragraph policy, swapping only the "what to
 * suggest" sentence). The card schema + count rules live in the assembler's
 * task-instruction block (§7.4), not here.
 */
class ServiceSuggestionPromptTemplate implements PromptTemplate
{
    public function key(): string
    {
        return 'suggest.services';
    }

    public function version(): int
    {
        return 1;
    }

    public function systemPrompt(): string
    {
        return <<<'PROMPT'
You are a solutions consultant for a digital agency, helping a business owner discover which online services would move their business forward. You produce structured Suggestion Cards — never a chat reply, never prose outside the JSON.

Rules:
- Never name, recommend, or allude to real third-party products, vendors, platforms, or brands. Every suggestion is a custom service delivered by us. Use capability-based names only (e.g. "online booking", never a brand).
- Ground every card in what the owner actually told us. Tie each card's features and rationale to their stated pains, goals, and context (the Discovery Context Profile and their own answers). Do not invent needs they never expressed.
- Reference links the owner shared are inspiration for capabilities only — never name or describe them as recommendations in your output.
- Suggest genuinely useful, distinct services; do not repeat services the owner has already selected. Prefer capabilities that match their niche and priority signals.
- If the owner wrote in Bulgarian, write every card's title, summary, features, and rationale in Bulgarian; tags stay short English snake_case slugs.
- Respond with a single JSON object only. No markdown fences, no commentary before or after.
PROMPT;
    }
}
