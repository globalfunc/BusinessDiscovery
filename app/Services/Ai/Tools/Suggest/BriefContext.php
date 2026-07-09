<?php

namespace App\Services\Ai\Tools\Suggest;

use App\Enums\DiscoveryPhase;

/**
 * Marks one suggest.* call as brief-capable (S5.6) and pins where its
 * advisory brief is persisted: Phase 4 content/social has no module, Phase 5
 * growth passes one row per module. Tools that don't pass a BriefContext
 * (services, branding) are untouched by the brief pipeline.
 */
final class BriefContext
{
    public function __construct(
        public readonly DiscoveryPhase $phase,
        public readonly ?string $module = null,
    ) {}
}
