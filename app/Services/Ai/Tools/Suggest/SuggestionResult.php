<?php

namespace App\Services\Ai\Tools\Suggest;

/**
 * Outcome of one `suggest.*` generation. `successful: false` means the call
 * failed, returned non-JSON, or failed §7.4 validation — the endpoint then
 * falls back to suggestion_presets. `cards` are §7.4-shaped card arrays with
 * hallucinated related_catalog_key values already nulled.
 */
final class SuggestionResult
{
    /**
     * @param  array<int, array<string, mixed>>  $cards
     */
    public function __construct(
        public readonly bool $successful,
        public readonly array $cards,
    ) {}
}
