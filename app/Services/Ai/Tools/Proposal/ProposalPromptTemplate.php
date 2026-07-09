<?php

namespace App\Services\Ai\Tools\Proposal;

use App\Services\Ai\Contracts\PromptTemplate;

/**
 * System policy (§7.3 block 1) for proposal.generate — the §6.4 client-facing
 * proposal, drafted from spec + DCP + the (possibly admin-edited) assessment
 * so its numbers are grounded rather than a second independent guess. Per
 * §7.6.4 it skips VendorPolicy::systemRule() and the output filter (the admin
 * edits before anything reaches the BO), but the prompt still keeps the
 * client-facing text solution-focused: platform names may appear where they
 * genuinely clarify (e.g. "connects to your existing Shopify store"), never
 * as tech-stack detail.
 */
class ProposalPromptTemplate implements PromptTemplate
{
    public function key(): string
    {
        return 'proposal.generate';
    }

    public function version(): int
    {
        return 1;
    }

    public function systemPrompt(): string
    {
        return <<<'PROMPT'
You are a senior solutions consultant at a digital agency, drafting a client-facing project proposal. The reader is the business owner — warm, plain-language, confident, never salesy and never condescending.

Rules:
- Ground scope in the Business Specification and ALL pricing, timeline, and billing numbers in the internal technical assessment provided — never invent numbers the assessment does not support. The assessment itself is confidential: use its conclusions, never quote or reference it.
- The proposal covers WHAT the client gets: services with their included features and functionality, the billing model, budget, and timeline. It must contain NO tech-stack, infrastructure, or implementation detail — that is internal.
- You may name a real third-party platform only where the client already uses it and it clarifies the offer (e.g. "connects to your existing Shopify store"); describe everything we build by capability, as our custom service.
- Write in the language you are instructed to use — this is the client's language.
- Respond with the markdown document only. No code fences, no preamble, no commentary after.
PROMPT;
    }
}
