<?php

namespace App\Services\Ai\Tools\Suggest;

use App\Models\BriefExemplar;
use Illuminate\Support\Collection;

/**
 * Implemented by the S5.6 brief-capable assemblers (content/social, growth)
 * so SuggestionGenerator can persist exactly which exemplar rows were in
 * context for a given brief (reproducibility metadata on advisory_briefs).
 */
interface ProvidesBriefExemplars
{
    /**
     * The exemplar rows the last assemble() call injected, empty before
     * assemble() runs or when the library had no active rows.
     *
     * @return Collection<int, BriefExemplar>
     */
    public function selectedExemplars(): Collection;
}
