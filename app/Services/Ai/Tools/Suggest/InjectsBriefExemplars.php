<?php

namespace App\Services\Ai\Tools\Suggest;

use App\Models\BriefExemplar;
use App\Models\DiscoverySession;
use Illuminate\Support\Collection;

/**
 * Shared S5.6 plumbing for the brief-capable assemblers (content/social,
 * growth): selects the most relevant brief_exemplars rows, renders them as
 * the §7.3-style BriefExemplars context block, memoizes the selection for
 * reproducibility metadata (ProvidesBriefExemplars), and carries the shared
 * task-instruction rules — including the prompt-level "advice only, never a
 * deliverable" gate — for the optional `brief` field.
 */
trait InjectsBriefExemplars
{
    /** @var Collection<int, BriefExemplar>|null memoized by briefExemplarsBlock() */
    private ?Collection $selectedExemplars = null;

    /** @return Collection<int, BriefExemplar> */
    public function selectedExemplars(): Collection
    {
        return $this->selectedExemplars ?? new Collection;
    }

    protected function briefExemplarsBlock(DiscoverySession $session): string
    {
        $this->selectedExemplars = $this->exemplarSelector->selectFor($session);

        if ($this->selectedExemplars->isEmpty()) {
            return '';
        }

        $rendered = $this->selectedExemplars->map(function (BriefExemplar $exemplar, int $index) {
            $brief = $exemplar->exemplar_brief;
            $bullets = implode("\n", array_map(
                fn (string $bullet) => "- {$bullet}",
                array_filter((array) ($brief['bullets'] ?? []), 'is_string'),
            ));

            $number = $index + 1;

            return "### Exemplar {$number}\nTheir context:\n{$exemplar->dcp_excerpt}\nThe brief we wrote for them:\n".($brief['paragraph'] ?? '')."\n{$bullets}";
        })->implode("\n\n");

        return "Gold-standard advisory briefs written for other businesses. Match their specificity, depth, and advisory tone — but ground YOUR brief entirely in this owner's own context; never reuse their content:\n\n{$rendered}";
    }

    /**
     * The shared task-instruction rules for the optional `brief` field. The
     * caller embeds the `"brief"` key in its JSON shape example; these rules
     * follow the card rules.
     */
    protected function briefInstruction(): string
    {
        return <<<'BRIEF'
The optional top-level "brief" is a short "note from the studio" shown above the cards:
- It is general advice and insight about this owner's situation — NOT ready-to-publish copy, NOT captions or scripts, NOT a step-by-step action plan.
- It MUST reference this owner's own niche, pain points, or goals concretely; a brief that could apply to any business is worthless and will be discarded.
- No generic platitudes ("consistency is key", "content is king", and the like).
- Keep it tight: the paragraph plus bullets together under 800 characters, at most 4 bullets.
- Write it in the interview language, same as the cards. Omit the field entirely if you cannot make it specific.
BRIEF;
    }
}
