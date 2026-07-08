<?php

namespace App\Services\Ai;

use App\Models\VendorBlocklistTerm;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Runtime half of §7.6: scans AI output against the admin-managed blocklist,
 * builds the single corrective regeneration turn, and redacts on a repeat hit.
 *
 * Scan strategy for structured (JSON) tools: the whole raw model string is
 * scanned *before* the tool parses it, and redaction rewrites that same raw
 * string. One filter therefore covers JSON cards, the DCP, and future
 * markdown/prose tools identically; it catches a leak in any field (even ones
 * a per-field scan wouldn't know to check); and because a literal/word-bounded
 * replacement only ever swaps a brand token for a generic phrase inside a
 * string value, the redacted output stays valid JSON — preserving the
 * never-block suggestion contract (a redacted card still parses & validates).
 */
class VendorFilter
{
    /** @var VendorMatch[]|null lazily compiled, memoized per instance */
    private ?array $compiled = null;

    /**
     * Scan text against every active, compilable blocklist term.
     *
     * @return VendorMatch[] one entry per term that fired; empty when clean
     */
    public function scan(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }

        $matches = [];

        foreach ($this->compiled() as $term) {
            if (preg_match_all($term->pattern, $text, $found) && $found[0] !== []) {
                $matches[] = new VendorMatch(
                    pattern: $term->pattern,
                    replacement: $term->replacement,
                    hits: array_values(array_unique($found[0])),
                );
            }
        }

        return $matches;
    }

    /**
     * Replace every matched term with its generic label.
     *
     * @param  VendorMatch[]  $matches
     */
    public function redact(string $text, array $matches): string
    {
        foreach ($matches as $match) {
            $text = preg_replace($match->pattern, $match->replacement, $text) ?? $text;
        }

        return $text;
    }

    /**
     * The offending substrings across all matches, for the corrective turn.
     *
     * @param  VendorMatch[]  $matches
     * @return string[]
     */
    public function leakedTerms(array $matches): array
    {
        return array_values(array_unique(array_merge(...array_map(
            fn (VendorMatch $m) => $m->hits,
            $matches,
        ) ?: [[]])));
    }

    /**
     * Clone the original request with the leaked answer + a corrective user turn
     * appended, so the single regeneration (§7.6.2) rewrites vendor-safe while
     * keeping the same tool/model/format.
     *
     * @param  VendorMatch[]  $matches
     */
    public function correctiveRequest(AiCallRequest $original, string $leakedText, array $matches): AiCallRequest
    {
        $messages = array_merge($original->messages, [
            ['role' => 'assistant', 'content' => $leakedText],
            ['role' => 'user', 'content' => VendorPolicy::correctiveInstruction($this->leakedTerms($matches))],
        ]);

        return new AiCallRequest(
            tool: $original->tool,
            messages: $messages,
            system: $original->system,
            businessOwner: $original->businessOwner,
            model: $original->model,
            effort: $original->effort,
            maxTokens: $original->maxTokens,
        );
    }

    /**
     * Active terms compiled to PCRE patterns, memoized. Literal terms get
     * unicode-aware word boundaries so "Wix" doesn't match "Wixom"; regex terms
     * are used verbatim (with `/` delimiters — admins escape any literal slash).
     * A term whose pattern fails to compile is skipped and logged rather than
     * breaking the scan for every other term.
     *
     * @return VendorMatch[] reused as compiled-pattern carriers (hits empty)
     */
    private function compiled(): array
    {
        if ($this->compiled !== null) {
            return $this->compiled;
        }

        $default = (string) config('ai.vendor_redaction_label', 'a custom solution');

        $rows = Cache::rememberForever(VendorBlocklistTerm::CACHE_KEY, fn () => VendorBlocklistTerm::query()
            ->where('active', true)
            ->get(['term', 'is_regex', 'replacement'])
            ->map(fn (VendorBlocklistTerm $t) => [
                'term' => $t->term,
                'is_regex' => $t->is_regex,
                'replacement' => $t->replacement,
            ])
            ->all());

        $compiled = [];

        foreach ($rows as $row) {
            $term = trim((string) $row['term']);

            if ($term === '') {
                continue;
            }

            $pattern = $row['is_regex']
                ? '/'.$term.'/iu'
                : '/(?<![\p{L}\p{N}])'.preg_quote($term, '/').'(?![\p{L}\p{N}])/iu';

            if (@preg_match($pattern, '') === false) {
                Log::warning('Vendor blocklist term has an invalid pattern; skipping.', ['term' => $term]);

                continue;
            }

            $compiled[] = new VendorMatch(
                pattern: $pattern,
                replacement: $row['replacement'] !== null && $row['replacement'] !== ''
                    ? (string) $row['replacement']
                    : $default,
                hits: [],
            );
        }

        return $this->compiled = $compiled;
    }
}
