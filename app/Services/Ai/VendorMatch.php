<?php

namespace App\Services\Ai;

/**
 * One blocklist term that fired against a piece of AI output, carrying its
 * compiled pattern (for redaction), its generic replacement label, and the
 * actual substrings that appeared in the text (for the corrective turn).
 */
final class VendorMatch
{
    /**
     * @param  string[]  $hits  the actual matched substrings from the text
     */
    public function __construct(
        public readonly string $pattern,
        public readonly string $replacement,
        public readonly array $hits,
    ) {}
}
