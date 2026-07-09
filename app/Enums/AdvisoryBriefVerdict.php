<?php

namespace App\Enums;

/**
 * Outcome of the S5.6 deterministic brief gate. S5.7 adds a third state
 * (hidden_low_value) once the LLM-judge grading layer exists.
 */
enum AdvisoryBriefVerdict: string
{
    case Shown = 'shown';
    case Dropped = 'dropped';
}
