<?php

namespace App\Services\Ai\Tools\Suggest;

/**
 * Outcome of one `suggest.*` generation. `successful: false` means the call
 * failed, returned non-JSON, or failed §7.4 validation — the endpoint then
 * falls back to suggestion_presets. `cards` are §7.4-shaped card arrays with
 * hallucinated related_catalog_key values already nulled. `brief` is the S5.6
 * advisory brief that passed the deterministic gate (content/social + growth
 * tools only) — null means no brief is shown, never that the cards failed.
 */
final class SuggestionResult
{
    /**
     * @param  array<int, array<string, mixed>>  $cards
     * @param  array{paragraph: string, bullets: array<int, string>}|null  $brief
     */
    public function __construct(
        public readonly bool $successful,
        public readonly array $cards,
        public readonly ?array $brief = null,
    ) {}
}
