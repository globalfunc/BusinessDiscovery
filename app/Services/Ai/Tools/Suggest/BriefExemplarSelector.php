<?php

namespace App\Services\Ai\Tools\Suggest;

use App\Models\BriefExemplar;
use App\Models\DiscoverySession;
use App\Models\TaxonomyNiche;
use App\Services\Ai\Support\DcpDigest;
use Illuminate\Support\Collection;

/**
 * Picks the most relevant brief_exemplars rows for one S5.6 generation call.
 * Relevance is deterministic: an exemplar scores one point per context tag
 * that appears in the session's niche names (bg+en) or DCP digest text, and
 * the top-scoring active rows win (ties broken by newest). With no tag hits
 * the newest active rows still go in — a generic gold pair beats none for
 * anchoring style and depth.
 */
class BriefExemplarSelector
{
    private const MAX_EXEMPLARS = 2;

    /**
     * @return Collection<int, BriefExemplar>
     */
    public function selectFor(DiscoverySession $session): Collection
    {
        $haystack = mb_strtolower($this->contextText($session));

        return BriefExemplar::query()
            ->where('active', true)
            ->get()
            ->sortBy([
                fn (BriefExemplar $a, BriefExemplar $b) => $this->score($b, $haystack) <=> $this->score($a, $haystack),
                fn (BriefExemplar $a, BriefExemplar $b) => $b->id <=> $a->id,
            ])
            ->take(self::MAX_EXEMPLARS)
            ->values();
    }

    private function score(BriefExemplar $exemplar, string $haystack): int
    {
        $score = 0;

        foreach ((array) $exemplar->context_tags as $tag) {
            if (is_string($tag) && $tag !== '' && str_contains($haystack, mb_strtolower($tag))) {
                $score++;
            }
        }

        return $score;
    }

    private function contextText(DiscoverySession $session): string
    {
        $nicheId = $session->answers()
            ->where('field_key', 'niche_id')
            ->value('value');

        $niche = is_int($nicheId) ? TaxonomyNiche::find($nicheId) : null;
        $names = $niche !== null ? implode(' ', array_filter((array) $niche->name, 'is_string')) : '';

        return $names.' '.DcpDigest::for($session);
    }
}
