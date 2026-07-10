<?php

namespace App\Services\Ai\Tools\Suggest;

use App\Services\Ai\Contracts\PromptTemplate;
use App\Services\Ai\VendorPolicy;

/**
 * System policy for the S5.7 `brief.grade` LLM-as-judge call. Deliberately
 * rubric-agnostic: the dimensions, their descriptions, and the scoring
 * anchors ride in the user turn (they're admin-editable config via
 * AiSettings::briefRubric()), so editing the rubric never requires a prompt
 * override — and vice versa. Known caveat, by design: a same-family judge is
 * lenient toward the generator's own blind spots; it's a filter, not a
 * guarantee — calibration comes from the admin labels and the eval harness.
 */
class BriefGradePromptTemplate implements PromptTemplate
{
    public function key(): string
    {
        return 'brief.grade';
    }

    public function version(): int
    {
        return 1;
    }

    public function systemPrompt(): string
    {
        $vendorRule = VendorPolicy::systemRule();

        return <<<PROMPT
You are a strict quality judge for a digital agency. You grade short advisory briefs ("notes from the studio") written for a specific business owner, against a rubric supplied in the task. You never rewrite the brief — you only score it.

Rules:
- {$vendorRule}
- Score every rubric dimension from 1 (fails the dimension entirely) to 5 (exemplary), using the dimension descriptions as the standard. Be skeptical: generic advice dressed in specific-sounding language is still generic. A brief that merely repeats the owner's own words back without adding direction is not insight.
- Judge only against the owner context provided; treat claims about the business that the context does not support as a credibility failure.
- Reasons must be one short sentence per dimension, in English, concrete about what earned or cost the score.
- Respond with a single JSON object only — no markdown fences, no commentary before or after — shaped exactly as: {"scores": {"<dimension_key>": {"score": <1-5>, "reason": "<one sentence>"}, ...}} with one entry per rubric dimension key.
PROMPT;
    }
}
