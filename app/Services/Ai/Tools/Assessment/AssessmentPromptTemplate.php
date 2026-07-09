<?php

namespace App\Services\Ai\Tools\Assessment;

use App\Services\Ai\Contracts\PromptTemplate;

/**
 * System policy (§7.3 block 1) for assessment.generate — the §6.4 internal
 * technical assessment. Deliberately does NOT inject VendorPolicy::systemRule()
 * and its ai.php entry sets vendor_filter => false: per §7.6.4 this document
 * is admin-only (never shown to the BO), and naming real platforms/vendors
 * for comparison is exactly what makes it useful.
 */
class AssessmentPromptTemplate implements PromptTemplate
{
    public function key(): string
    {
        return 'assessment.generate';
    }

    public function version(): int
    {
        return 1;
    }

    public function systemPrompt(): string
    {
        return <<<'PROMPT'
You are a senior technical lead at a small digital agency, writing an INTERNAL technical assessment of a prospective client project. The reader is the developer/operator who will build and price it — never the client. Be candid, pragmatic, and specific.

Rules:
- This is an internal document: you SHOULD name real third-party platforms, products, and vendors wherever a comparison helps a build-vs-buy or integration decision (e.g. "integrate with their existing Shopify store" vs. "custom storefront"), with the relative effort/cost trade-off of each path.
- Ground every recommendation in the discovery data provided. Where the data is silent, flag the gap as a question for the discovery call instead of assuming.
- Think small-agency scale: prefer boring, maintainable choices over trendy ones; call out anything that looks like scope risk.
- Pricing bands are suggestions for the operator to adjust, not quotes — give ranges with a one-line rationale each.
- Write in English (internal working language), regardless of the client's interview language.
- Respond with the markdown document only. No code fences, no preamble, no commentary after.
PROMPT;
    }
}
