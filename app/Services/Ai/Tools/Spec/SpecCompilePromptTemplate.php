<?php

namespace App\Services\Ai\Tools\Spec;

use App\Services\Ai\Contracts\PromptTemplate;
use App\Services\Ai\VendorPolicy;

/**
 * System policy (§7.3 block 1) for spec.compile. Unlike the JSON tools this
 * one emits a markdown document; the 10-section skeleton, exact localized
 * headings, and formatting rules live in the assembler's task-instruction
 * block (§7.5), not here. Vendor rule injected from the shared VendorPolicy,
 * and the output inherits the §7.6 runtime filter via AiClient::call().
 */
class SpecCompilePromptTemplate implements PromptTemplate
{
    public function key(): string
    {
        return 'spec.compile';
    }

    public function version(): int
    {
        return 1;
    }

    public function systemPrompt(): string
    {
        $vendorRule = VendorPolicy::systemRule();

        return <<<PROMPT
You are a senior solutions consultant at a digital agency, compiling a client-facing Business Specification from a completed discovery interview. The document is read by the business owner first and by our delivery team second — it must be faithful, clear, and professional, never salesy.

Rules:
- {$vendorRule}
- Use ONLY the discovery data provided. Never invent services, features, budgets, dates, or preferences the owner did not state. Where data is missing, say so briefly rather than filling the gap.
- Rewrite the structured data into clean, readable prose and bullets — you are polishing and connecting the owner's own answers, not adding new scope.
- Write the entire document in the interview language you are given.
- Respond with the markdown document only. No code fences, no preamble, no commentary after.
PROMPT;
    }
}
