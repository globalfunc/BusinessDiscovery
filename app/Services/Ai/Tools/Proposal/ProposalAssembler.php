<?php

namespace App\Services\Ai\Tools\Proposal;

use App\Enums\Language;
use App\Models\AssessmentDocument;
use App\Models\BusinessOwner;
use App\Services\Ai\ContextBlock;
use App\Services\Ai\ContextBlockType;
use App\Services\Ai\PromptTemplateRegistry;
use App\Services\Ai\Support\DcpDigest;
use App\Services\Ai\Support\SpecSource;

/**
 * §7.3 block assembly for proposal.generate (§6.4). Takes the assessment the
 * caller already resolved (the generator enforces "no proposal without an
 * assessment") so the draft's numbers come from the admin-reviewed document,
 * not a fresh guess.
 */
class ProposalAssembler
{
    public function __construct(protected readonly PromptTemplateRegistry $templates) {}

    /**
     * @return ContextBlock[]
     */
    public function assemble(BusinessOwner $businessOwner, AssessmentDocument $assessment): array
    {
        return [
            new ContextBlock(ContextBlockType::SystemPolicy, $this->templates->get('proposal.generate')->systemPrompt()),
            new ContextBlock(ContextBlockType::AdminContext, trim((string) $businessOwner->admin_context)),
            new ContextBlock(ContextBlockType::Dcp, $businessOwner->discoverySession ? DcpDigest::for($businessOwner->discoverySession) : ''),
            new ContextBlock(ContextBlockType::SpecDocument, SpecSource::markdownFor($businessOwner)),
            new ContextBlock(ContextBlockType::Assessment, $assessment->markdown),
            new ContextBlock(ContextBlockType::TaskInstruction, $this->taskInstruction($businessOwner)),
        ];
    }

    private function taskInstruction(BusinessOwner $businessOwner): string
    {
        $language = ($businessOwner->language ?? Language::Bulgarian)->value;

        return <<<TASK
Draft the client-facing proposal for this business owner. Write the entire document in the client's language: "{$language}".

Structure — exactly these sections, in this order, each starting with a `## ` heading (translate the heading text into the client's language):

## What we will build for you
## Services & included features
## How billing works
## Budget
## Timeline
## Next steps

Formatting rules:
- Under each heading: a short warm paragraph, then `- ` bullets for concrete items. Bold key terms with `**`.
- "Services & included features" lists each service from the specification with its included features — the client should recognize their own choices.
- Budget and timeline figures must come from the internal assessment's pricing bands and effort notes, presented as ranges. No tech-stack, infrastructure, or implementation detail anywhere.
- "Next steps" is 2-4 short bullets ending with the discovery/kick-off call.
- Output the markdown document only — start directly with the first heading.
TASK;
    }
}
