<?php

namespace App\Services\Ai\Tools\Suggest;

/**
 * One successful `brief.grade` outcome (S5.7): the per-dimension scores with
 * their one-line reasons, the weighted composite, and the rubric/judge
 * identity that produced them — everything the advisory_briefs row persists.
 * A failed grade never constructs this object (BriefGrader returns null).
 */
final class BriefGrade
{
    /**
     * @param  array<string, array{score: int, reason: string}>  $scores  keyed by rubric dimension key
     */
    public function __construct(
        public readonly array $scores,
        public readonly float $composite,
        public readonly string $judgeModel,
        public readonly int $rubricVersion,
        public readonly float $threshold,
    ) {}

    public function passes(): bool
    {
        return $this->composite >= $this->threshold;
    }
}
