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

        return BriefPrompt::exemplarsBlock($this->selectedExemplars);
    }

    /**
     * The shared task-instruction rules for the optional `brief` field. The
     * caller embeds the `"brief"` key in its JSON shape example; these rules
     * follow the card rules.
     */
    protected function briefInstruction(): string
    {
        return BriefPrompt::instruction();
    }
}
