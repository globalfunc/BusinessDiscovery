<?php

namespace App\Enums;

/**
 * Outcome of the brief-quality pipeline. Shown/Dropped are set by the S5.6
 * deterministic gate at generation time; HiddenLowValue is the S5.7 LLM
 * judge demoting a gate-passing brief whose composite missed the threshold
 * (only in enforce mode — log_only grades but never demotes).
 */
enum AdvisoryBriefVerdict: string
{
    case Shown = 'shown';
    case Dropped = 'dropped';
    case HiddenLowValue = 'hidden_low_value';
}
