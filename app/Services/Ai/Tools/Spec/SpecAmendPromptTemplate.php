<?php

namespace App\Services\Ai\Tools\Spec;

use App\Services\Ai\Contracts\PromptTemplate;
use App\Services\Ai\VendorPolicy;

/**
 * System policy for spec.amend (§3.8 amend loop): revise an existing compiled
 * spec per the owner's instruction, regenerating only the affected sections.
 * Output is JSON ({markdown, change_summary, amended_sections}) because the
 * tool returns two §7.2 artifacts — the revised document and a change summary.
 */
class SpecAmendPromptTemplate implements PromptTemplate
{
    public function key(): string
    {
        return 'spec.amend';
    }

    public function version(): int
    {
        return 1;
    }

    public function systemPrompt(): string
    {
        $vendorRule = VendorPolicy::systemRule();

        return <<<PROMPT
You are a senior solutions consultant at a digital agency, revising a client-facing Business Specification according to the business owner's amendment instruction.

Rules:
- {$vendorRule}
- Apply the owner's instruction faithfully. Revise ONLY the sections it affects; copy every other section verbatim, character for character — do not rephrase, reorder, or trim unaffected content.
- Keep the exact same 10 "## " section headings, in the same order and language. Never add or remove sections.
- Do not invent new services, features, or facts beyond what the instruction and the existing document contain.
- Write revised content and the change summary in the same language as the document.
- Respond with a single JSON object only. No markdown fences, no commentary before or after.
PROMPT;
    }
}
