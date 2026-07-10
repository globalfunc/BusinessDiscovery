<?php

namespace App\Services\Ai\Tools\Suggest;

/**
 * Outcome of one `suggest.*` generation. `successful: false` means the call
 * failed, returned non-JSON, or failed §7.4 validation — the endpoint then
 * falls back to suggestion_presets. `cards` are §7.4-shaped card arrays with
 * hallucinated related_catalog_key values already nulled. `pendingBriefId`
 * points at the advisory_briefs row that passed the S5.6 deterministic gate
 * (content/social + growth tools only) — S5.7 holds the brief itself back
 * from this response; the frontend fires the async brief.grade reveal
 * request against that row. Null means no brief exists for this generation,
 * never that the cards failed.
 */
final class SuggestionResult
{
    /**
     * @param  array<int, array<string, mixed>>  $cards
     */
    public function __construct(
        public readonly bool $successful,
        public readonly array $cards,
        public readonly ?int $pendingBriefId = null,
    ) {}
}
