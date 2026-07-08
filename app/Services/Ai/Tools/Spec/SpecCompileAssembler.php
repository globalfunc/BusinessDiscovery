<?php

namespace App\Services\Ai\Tools\Spec;

use App\Enums\Language;
use App\Models\BusinessOwner;
use App\Models\DiscoveryAnswer;
use App\Models\DiscoverySession;
use App\Services\Ai\ContextBlock;
use App\Services\Ai\ContextBlockType;
use App\Services\Ai\Contracts\InputAssembler;
use App\Services\Ai\PromptTemplateRegistry;
use App\Services\Ai\Support\DcpDigest;
use App\Support\DiscoverySpecRenderer;

/**
 * §7.3 block assembly for spec.compile. The structured_answers block is the
 * deterministic DiscoverySpecRenderer output — one localized digest that
 * already covers every answer, selection, upload, and price the BO gave — so
 * the model rewrites a complete, faithful skeleton into prose instead of
 * re-deriving facts from raw rows. The task instruction pins the output to
 * the same ten localized headings the fallback renderer uses.
 */
class SpecCompileAssembler implements InputAssembler
{
    public function __construct(protected readonly PromptTemplateRegistry $templates) {}

    public function assemble(BusinessOwner $businessOwner, DiscoverySession $discoverySession): array
    {
        return [
            new ContextBlock(ContextBlockType::SystemPolicy, $this->templates->get('spec.compile')->systemPrompt()),
            new ContextBlock(ContextBlockType::AdminContext, trim((string) $businessOwner->admin_context)),
            new ContextBlock(ContextBlockType::Dcp, DcpDigest::for($discoverySession)),
            new ContextBlock(ContextBlockType::StructuredAnswers, DiscoverySpecRenderer::render($discoverySession, $businessOwner)),
            new ContextBlock(ContextBlockType::PhaseNotes, $this->allPhaseNotes($discoverySession)),
            new ContextBlock(ContextBlockType::TaskInstruction, $this->taskInstruction($discoverySession)),
        ];
    }

    /**
     * Every free-text "notes" answer across phases (§7.3 block 6) — the
     * deterministic digest above only carries the intake notes, and the
     * BO's own words are exactly what the prose sections should echo.
     */
    private function allPhaseNotes(DiscoverySession $session): string
    {
        return $session->answers()
            ->where('field_key', 'notes')
            ->orderBy('phase')
            ->get()
            ->filter(fn (DiscoveryAnswer $answer) => is_string($answer->value) && trim($answer->value) !== '')
            ->map(fn (DiscoveryAnswer $answer) => "Notes from {$answer->phase}: ".trim($answer->value))
            ->implode("\n");
    }

    private function taskInstruction(DiscoverySession $session): string
    {
        $language = $session->language ?? Language::Bulgarian;

        $headings = collect(DiscoverySpecRenderer::sectionTitles($language))
            ->map(fn (string $title) => "## {$title}")
            ->implode("\n");

        return <<<TASK
Compile the Business Specification for this owner. Write the entire document in the interview language "{$language->value}".

Structure — exactly these 10 sections, in this order, each starting with this exact heading line:

{$headings}

Formatting rules:
- Under each heading: a short grounding paragraph (1-3 sentences) where prose adds value, then `- ` bullets for concrete items. Nest detail bullets with a two-space indent (`  - `). Bold key terms with `**`.
- Section 3 lists each selected catalog service with its features, priority marker, and the owner's notes; section 4 does the same for custom and AI-suggested services including reference links.
- Where the discovery data has nothing for a section, keep the heading and note in one line that it will be clarified on the discovery call — never invent content to fill it.
- The final section lists 3-6 concrete open questions drawn from real gaps or ambiguities in the data above.
- Output the markdown document only — start directly with the first heading.
TASK;
    }
}
