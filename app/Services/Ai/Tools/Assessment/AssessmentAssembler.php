<?php

namespace App\Services\Ai\Tools\Assessment;

use App\Models\BusinessOwner;
use App\Services\Ai\ContextBlock;
use App\Services\Ai\ContextBlockType;
use App\Services\Ai\PromptTemplateRegistry;
use App\Services\Ai\Support\DcpDigest;
use App\Services\Ai\Support\SpecSource;

/**
 * §7.3 block assembly for assessment.generate (§6.4). Grounds on the
 * compiled spec (SpecSource) + DCP + the admin's standing business context,
 * plus the optional ad-hoc notes the admin typed on the generate click —
 * the "admin notes" input the spec names. No taxonomy/preset blocks: the
 * spec already carries the selections this document assesses.
 */
class AssessmentAssembler
{
    public function __construct(protected readonly PromptTemplateRegistry $templates) {}

    /**
     * @return ContextBlock[]
     */
    public function assemble(BusinessOwner $businessOwner, ?string $adminNotes = null): array
    {
        return [
            new ContextBlock(ContextBlockType::SystemPolicy, $this->templates->get('assessment.generate')->systemPrompt()),
            new ContextBlock(ContextBlockType::AdminContext, $this->adminContext($businessOwner, $adminNotes)),
            new ContextBlock(ContextBlockType::Dcp, $businessOwner->discoverySession ? DcpDigest::for($businessOwner->discoverySession) : ''),
            new ContextBlock(ContextBlockType::SpecDocument, SpecSource::markdownFor($businessOwner)),
            new ContextBlock(ContextBlockType::TaskInstruction, $this->taskInstruction()),
        ];
    }

    private function adminContext(BusinessOwner $businessOwner, ?string $adminNotes): string
    {
        $parts = array_filter([
            trim((string) $businessOwner->admin_context),
            trim((string) $adminNotes) !== '' ? 'Admin notes for this assessment: '.trim((string) $adminNotes) : '',
        ]);

        return implode("\n\n", $parts);
    }

    private function taskInstruction(): string
    {
        return <<<'TASK'
Write the internal technical assessment for this project. Structure — exactly these sections, in this order, each starting with a `## ` heading:

## Suggested tech stack
## Integrations & build-vs-buy
## Infrastructure needs
## Implementation plan
## Effort & complexity notes
## Suggested pricing bands

Formatting rules:
- Under each heading: a short grounding paragraph where prose adds value, then `- ` bullets for concrete items. Bold key terms with `**`.
- "Integrations & build-vs-buy" must compare concrete paths per capability — name the real off-the-shelf platform(s) worth integrating with versus a custom build, with the relative effort/cost of each path.
- "Suggested pricing bands" gives a range per billing model that fits this project (one-time build, build + support retainer, subscription), each with a one-line rationale tied to the effort notes.
- Where the discovery data has nothing for a section, keep the heading and note in one line what must be clarified on the discovery call — never invent client requirements.
- Output the markdown document only — start directly with the first heading.
TASK;
    }
}
