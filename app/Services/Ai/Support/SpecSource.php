<?php

namespace App\Services\Ai\Support;

use App\Models\BusinessOwner;
use App\Support\DiscoverySpecRenderer;

/**
 * The §7.3 SpecDocument block content for the S4.5 admin-side generators
 * (assessment/proposal/email), which ground on the compiled spec rather than
 * raw answers. Prefers the latest stored spec_documents version (it may carry
 * amendments); before any spec exists it falls back to the deterministic
 * renderer so an admin can still draft from a partial discovery. Returns ''
 * when there is no discovery session at all — callers send the block anyway
 * and AiCallRequest::fromContextBlocks() drops blank blocks.
 */
final class SpecSource
{
    public static function markdownFor(BusinessOwner $businessOwner): string
    {
        $session = $businessOwner->discoverySession;

        if ($session === null) {
            return '';
        }

        return $session->latestSpecDocument?->markdown
            ?? DiscoverySpecRenderer::render($session, $businessOwner);
    }
}
