<?php

namespace App\Services\Ai\Tools\Email;

use App\Services\Ai\Contracts\PromptTemplate;

/**
 * System policy (§7.3 block 1) for email.generate — the §6.5 Warm tease /
 * Follow-up / Proposal cover generators. Per §7.6.4 no VendorPolicy rule and
 * no output filter: the admin edits and sends manually (no sending in v1),
 * so a useful mention ("saw you're on Facebook only") is not a leak.
 */
class EmailPromptTemplate implements PromptTemplate
{
    public function key(): string
    {
        return 'email.generate';
    }

    public function version(): int
    {
        return 1;
    }

    public function systemPrompt(): string
    {
        return <<<'PROMPT'
You are writing outreach email copy for the owner of a small digital agency to send personally to a small-business client. The voice is one human writing to another: warm, brief, specific to their business — never corporate, never template-smelling, no marketing buzzwords.

Rules:
- Ground every specific claim in the provided business context, discovery data, or specification. Never invent details, prices, or commitments.
- Keep it short: subject under 60 characters; body 60-160 words, in short paragraphs. No bullet lists unless the email type asks for one.
- Write in the language you are instructed to use. Sign off with a plain first-name placeholder line: "[Your name]".
- Respond with ONLY a JSON object: {"subject": "...", "body": "..."}. The body uses \n for line breaks. No code fences, no commentary.
PROMPT;
    }
}
