<?php

namespace App\Services\Ai\Tools\Suggest;

/**
 * The S5.6 deterministic quality gate for advisory briefs. Purely mechanical
 * — no AI, no DB: shape + hard length cap, a generic-platitude blocklist
 * (en/bg), and a DCP-grounding check (the brief must contain at least one
 * concrete niche/pain-point/goal token from the session context, supplied by
 * the caller). Returns null when the brief may be shown, or a drop_reason
 * string; a dropped brief is nulled in the response while the Suggestion
 * Cards return untouched. The S5.7 LLM judge layers on top of this — this
 * gate stays synchronous and cheap.
 */
class BriefQualityGate
{
    /** Hard cap on paragraph + bullets combined (spec: ~600–800 chars). */
    private const MAX_TOTAL_CHARS = 800;

    private const MAX_BULLETS = 4;

    /**
     * Case-insensitive substrings that mark a brief as generic filler. Seeded
     * with the classics in both interview languages; extend as real drops
     * are reviewed (S5.7 adds the admin surface for that).
     */
    private const PLATITUDES = [
        'consistency is key',
        'content is king',
        'engage with your audience',
        'engage your audience',
        'post regularly',
        'post consistently',
        'quality over quantity',
        'in today\'s digital world',
        'in today\'s digital age',
        'social media is essential',
        'постоянството е ключ',
        'съдържанието е цар',
        'ангажирайте аудиторията',
        'публикувайте редовно',
        'качество пред количество',
        'в днешния дигитален свят',
    ];

    /**
     * @param  mixed  $brief  the raw `brief` value from the model payload
     * @param  string[]  $groundingTokens  lowercased concrete tokens from the
     *                                     session's niche/DCP context
     * @return string|null null = show; otherwise the drop_reason
     */
    public function evaluate(mixed $brief, array $groundingTokens): ?string
    {
        if (! is_array($brief)) {
            return 'malformed';
        }

        $paragraph = $brief['paragraph'] ?? null;
        $bullets = $brief['bullets'] ?? [];

        if (! is_string($paragraph) || trim($paragraph) === '' || ! is_array($bullets)) {
            return 'malformed';
        }

        foreach ($bullets as $bullet) {
            if (! is_string($bullet) || trim($bullet) === '') {
                return 'malformed';
            }
        }

        if (count($bullets) > self::MAX_BULLETS) {
            return 'too_many_bullets';
        }

        $text = $paragraph.' '.implode(' ', $bullets);

        if (mb_strlen($text) > self::MAX_TOTAL_CHARS) {
            return 'too_long';
        }

        $lower = mb_strtolower($text);

        foreach (self::PLATITUDES as $platitude) {
            if (str_contains($lower, $platitude)) {
                return 'platitude';
            }
        }

        if (! $this->grounded($lower, $groundingTokens)) {
            return 'ungrounded';
        }

        return null;
    }

    /**
     * A brief is grounded when it contains at least one context token (or its
     * 6-char prefix — a cheap stemmer so Bulgarian inflection still matches).
     * An empty token set means grounding can't be demonstrated at all, so the
     * brief is dropped rather than waved through.
     *
     * @param  string[]  $groundingTokens
     */
    private function grounded(string $briefText, array $groundingTokens): bool
    {
        foreach ($groundingTokens as $token) {
            $needle = mb_strlen($token) > 6 ? mb_substr($token, 0, 6) : $token;

            if ($needle !== '' && str_contains($briefText, $needle)) {
                return true;
            }
        }

        return false;
    }
}
