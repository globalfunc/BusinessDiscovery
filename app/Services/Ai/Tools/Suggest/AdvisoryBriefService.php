<?php

namespace App\Services\Ai\Tools\Suggest;

use App\Enums\AdvisoryBriefVerdict;
use App\Models\AdvisoryBrief;
use App\Models\BriefExemplar;
use App\Models\BusinessOwner;
use App\Models\DiscoverySession;
use App\Models\TaxonomyNiche;
use App\Services\Ai\PromptTemplateRegistry;
use App\Services\Ai\Support\DcpDigest;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates the S5.6 advisory-brief tail of one suggest.* call: runs the
 * deterministic BriefQualityGate over the `brief` field the model returned in
 * the same payload as the cards, persists an append-only advisory_briefs row
 * either way (verdict shown|dropped + reproducibility metadata), and hands
 * back the brief to surface — or null, in which case the cards return alone.
 *
 * Vendor neutrality (§7.6) needs no handling here: the brief rides in the
 * same raw model string the AiClient-level VendorFilter already scanned and,
 * if needed, redacted before the payload was parsed.
 */
class AdvisoryBriefService
{
    /**
     * Generic scaffolding words (DcpDigest headers + filler) excluded from
     * the grounding token set so "digital maturity" alone can't ground a brief.
     */
    private const STOPWORDS = [
        'digital', 'maturity', 'unknown', 'points', 'goals', 'strengths',
        'priority', 'signals', 'business', 'their', 'about', 'other',
        'бизнес', 'техните', 'повече', 'много', 'други',
    ];

    private const MIN_TOKEN_LENGTH = 5;

    public function __construct(
        private readonly BriefQualityGate $gate,
        private readonly PromptTemplateRegistry $templates,
    ) {}

    /**
     * @param  mixed  $rawBrief  the payload's `brief` value (missing = null)
     * @param  array<int, array{id: int, version: int}>  $exemplars  the id+version set that was in context
     * @return AdvisoryBrief|null the gate-passing row (S5.7 holds its brief
     *                            back until the async brief.grade reveal), or null when dropped
     */
    public function process(
        mixed $rawBrief,
        BusinessOwner $businessOwner,
        DiscoverySession $session,
        BriefContext $context,
        string $tool,
        string $model,
        array $exemplars,
    ): ?AdvisoryBrief {
        $dropReason = $rawBrief === null
            ? 'missing'
            : $this->gate->evaluate($rawBrief, $this->groundingTokens($session));

        $shown = $dropReason === null;

        $record = AdvisoryBrief::create([
            'business_owner_id' => $businessOwner->id,
            'phase' => $context->phase->value,
            'module' => $context->module,
            'brief' => is_array($rawBrief) ? $rawBrief : null,
            'verdict' => $shown ? AdvisoryBriefVerdict::Shown : AdvisoryBriefVerdict::Dropped,
            'drop_reason' => $dropReason,
            'model' => $model,
            'prompt_version' => $this->templates->has($tool) ? $this->templates->get($tool)->version() : null,
            'exemplars' => $exemplars,
            'dcp_profile_id' => $session->latestDcpProfile?->id,
        ]);

        if (! $shown) {
            Log::info('Advisory brief dropped by deterministic gate.', [
                'advisory_brief_id' => $record->id,
                'tool' => $tool,
                'drop_reason' => $dropReason,
            ]);

            return null;
        }

        return $record;
    }

    /**
     * Concrete niche/pain-point/goal tokens from the session context, for the
     * gate's grounding check: unicode words ≥5 chars from the niche names
     * (bg+en) and the DCP digest, lowercased, minus generic scaffolding words.
     *
     * @return string[]
     */
    private function groundingTokens(DiscoverySession $session): array
    {
        $nicheId = $session->answers()
            ->where('field_key', 'niche_id')
            ->value('value');

        $niche = is_int($nicheId) ? TaxonomyNiche::find($nicheId) : null;
        $names = $niche !== null ? implode(' ', array_filter((array) $niche->name, 'is_string')) : '';

        $source = mb_strtolower($names.' '.DcpDigest::for($session));

        preg_match_all('/\p{L}{'.self::MIN_TOKEN_LENGTH.',}/u', $source, $words);

        return array_values(array_diff(array_unique($words[0]), self::STOPWORDS));
    }

    /**
     * The id+version pairs persisted for reproducibility, from the exemplar
     * rows a selector put in context.
     *
     * @param  iterable<int, BriefExemplar>  $exemplars
     * @return array<int, array{id: int, version: int}>
     */
    public static function exemplarSet(iterable $exemplars): array
    {
        $set = [];

        foreach ($exemplars as $exemplar) {
            $set[] = ['id' => $exemplar->id, 'version' => $exemplar->version];
        }

        return $set;
    }
}
