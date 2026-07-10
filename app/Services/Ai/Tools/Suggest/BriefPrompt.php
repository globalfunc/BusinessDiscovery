<?php

namespace App\Services\Ai\Tools\Suggest;

use App\Models\BriefExemplar;
use Illuminate\Support\Collection;

/**
 * The prompt fragments for the advisory-brief field, shared by the live
 * assemblers (via InjectsBriefExemplars) and the S5.7 offline eval harness
 * (briefs:eval), which assembles a brief-only prompt without a discovery
 * session. One source of truth so the harness always evaluates exactly the
 * text production sends.
 */
final class BriefPrompt
{
    /**
     * Render selected exemplars as the §7.3-style BriefExemplars block body.
     *
     * @param  Collection<int, BriefExemplar>  $exemplars
     */
    public static function exemplarsBlock(Collection $exemplars): string
    {
        if ($exemplars->isEmpty()) {
            return '';
        }

        $rendered = $exemplars->values()->map(function (BriefExemplar $exemplar, int $index) {
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
    public static function instruction(): string
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
