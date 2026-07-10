<?php

namespace App\Services\Ai\Support;

use App\Models\DcpProfile;
use App\Models\DiscoverySession;

/**
 * Compact operator-facing digest of the latest usable DCP (§3.1) for the
 * §7.3 DCP block, shared by every assembler that sends one (the suggest.*
 * family and spec.compile). Returns '' when no DCP exists or it's empty, so
 * the tool grounds on raw answers alone instead.
 */
final class DcpDigest
{
    public static function for(DiscoverySession $session): string
    {
        return self::fromProfile($session->latestDcpProfile);
    }

    /**
     * Digest a specific persisted profile — S5.7's brief grader and the admin
     * review surface reconstruct the DCP snapshot an advisory_briefs row
     * pointed at, which may not be the session's latest anymore.
     */
    public static function fromProfile(?DcpProfile $profile): string
    {
        if ($profile === null || $profile->isEmpty()) {
            return '';
        }

        $payload = $profile->payload;
        $lines = [];

        if (is_string($payload['summary'] ?? null)) {
            $lines[] = $payload['summary'];
        }

        $lines[] = 'Digital maturity: '.($payload['digital_maturity'] ?? 'unknown');

        $pains = self::labels($payload['pain_points'] ?? []);
        if ($pains !== []) {
            $lines[] = 'Pain points: '.implode('; ', $pains);
        }

        $goals = self::labels($payload['goals'] ?? []);
        if ($goals !== []) {
            $lines[] = 'Goals: '.implode('; ', $goals);
        }

        $strengths = array_filter((array) ($payload['strengths'] ?? []), 'is_string');
        if ($strengths !== []) {
            $lines[] = 'Strengths: '.implode('; ', $strengths);
        }

        $signals = array_filter((array) ($payload['priority_signals'] ?? []), 'is_string');
        if ($signals !== []) {
            $lines[] = 'Priority signals: '.implode(', ', $signals);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  mixed  $items  DCP list of {id,label} objects
     * @return string[]
     */
    private static function labels(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($item) => is_array($item) && is_string($item['label'] ?? null) ? $item['label'] : null,
            $items,
        )));
    }
}
