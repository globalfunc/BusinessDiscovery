<?php

namespace App\Services\Ai\Tools\Spec;

/**
 * Shared output hygiene for the two spec tools: strips stray code fences and
 * rejects text that doesn't look like the §7.5 10-section document, so a
 * degenerate response (empty, refusal prose, truncated fragment) falls
 * through to the deterministic renderer / failure path instead of being
 * stored as a spec version.
 */
final class SpecMarkdown
{
    /**
     * Minimum "## " headings for output to count as a spec. The template
     * mandates exactly 10; a lower bar tolerates merged sections without
     * accepting arbitrary prose.
     */
    private const MIN_SECTIONS = 6;

    public static function usable(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        // Defensive: strip markdown fences despite the no-fences instruction.
        $text = trim($text);
        $text = preg_replace('/^```(?:markdown|md)?\s*|\s*```$/', '', $text) ?? $text;
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        $sections = preg_match_all('/^## /m', $text);

        return $sections >= self::MIN_SECTIONS ? $text : null;
    }
}
