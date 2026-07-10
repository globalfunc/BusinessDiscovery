<?php

namespace App\Services\Ai\Tools\Suggest;

use App\Enums\AdvisoryBriefVerdict;
use App\Models\AdvisoryBrief;
use App\Models\BusinessOwner;
use App\Services\Ai\AiCallRequest;
use App\Services\Ai\AiClient;
use App\Services\Ai\AiSettings;
use App\Services\Ai\PromptTemplateRegistry;
use App\Services\Ai\Support\DcpDigest;
use Illuminate\Support\Facades\Log;

/**
 * The S5.7 `brief.grade` LLM-as-judge. Grades one advisory brief against the
 * admin-configurable rubric (AiSettings::briefRubric()) on a cheap model and
 * decides whether the async-reveal endpoint may surface it. The call goes
 * through AiClient::call() like every other tool, so it logs its own
 * ai_calls row, counts against the §7.7 budgets/rate limit, and inherits the
 * §7.6 vendor filter (the output is scores + short reasons, so a redaction
 * can never corrupt anything structural).
 *
 * Reveal semantics per rubric mode:
 *  - log_only: every outcome (pass, fail, judge error) is persisted for
 *    calibration, but the brief is revealed iff it passed the S5.6
 *    deterministic gate — i.e. always, since only gate-passing rows reach
 *    this class. The verdict is never rewritten.
 *  - enforce: a composite below threshold — or a failed/unparseable grade —
 *    demotes the row to hidden_low_value and the brief stays hidden.
 */
class BriefGrader
{
    public function __construct(
        private readonly AiClient $aiClient,
        private readonly AiSettings $aiSettings,
        private readonly PromptTemplateRegistry $templates,
    ) {}

    /**
     * Grade a persisted gate-passing row, stamp the grading metadata on it,
     * and return the brief payload to reveal — or null to keep it hidden.
     *
     * @return array{paragraph: string, bullets: array<int, string>}|null
     */
    public function gradeRecord(AdvisoryBrief $record): ?array
    {
        $rubric = $this->aiSettings->briefRubric();
        $context = DcpDigest::fromProfile($record->dcpProfile);

        $grade = $this->grade($record->brief, $context, $record->businessOwner);
        $enforce = ($rubric['mode'] ?? 'log_only') === 'enforce';

        if ($grade === null) {
            if ($enforce) {
                $record->update([
                    'verdict' => AdvisoryBriefVerdict::HiddenLowValue,
                    'drop_reason' => 'grade_failed',
                ]);

                return null;
            }

            return $record->brief;
        }

        $hidden = $enforce && ! $grade->passes();

        $record->update([
            'scores' => $grade->scores,
            'composite' => $grade->composite,
            'judge_model' => $grade->judgeModel,
            'rubric_version' => $grade->rubricVersion,
            ...($hidden ? ['verdict' => AdvisoryBriefVerdict::HiddenLowValue] : []),
        ]);

        if ($hidden) {
            Log::info('Advisory brief hidden by rubric judge.', [
                'advisory_brief_id' => $record->id,
                'composite' => $grade->composite,
                'threshold' => $grade->threshold,
            ]);

            return null;
        }

        return $record->brief;
    }

    /**
     * Grade an arbitrary brief payload against owner context — the shared
     * core used by gradeRecord() and by the offline eval harness, which has
     * no advisory_briefs row. Null means the judge call failed, was budget-
     * gated, or returned an unusable payload; the caller decides what a
     * missing grade means for reveal.
     */
    public function grade(mixed $brief, string $ownerContext, ?BusinessOwner $businessOwner = null): ?BriefGrade
    {
        if (! is_array($brief) || ! is_string($brief['paragraph'] ?? null)) {
            return null;
        }

        $rubric = $this->aiSettings->briefRubric();
        $dimensions = $rubric['dimensions'] ?? [];

        if ($dimensions === []) {
            return null;
        }

        $result = $this->aiClient->call(new AiCallRequest(
            tool: 'brief.grade',
            messages: [['role' => 'user', 'content' => $this->userTurn($brief, $ownerContext, $dimensions)]],
            system: $this->templates->get('brief.grade')->systemPrompt(),
            businessOwner: $businessOwner,
        ));

        if (! $result->successful || $result->text === null) {
            return null;
        }

        $scores = $this->parseScores($result->text, $dimensions);

        if ($scores === null) {
            Log::warning('brief.grade returned an unusable payload.', ['ai_call_id' => $result->aiCall->id]);

            return null;
        }

        return new BriefGrade(
            scores: $scores,
            composite: $this->composite($scores, $dimensions),
            judgeModel: $result->aiCall->model,
            rubricVersion: (int) ($rubric['version'] ?? 1),
            threshold: (float) ($rubric['threshold'] ?? 3.5),
        );
    }

    /**
     * @param  array<string, mixed>  $brief
     * @param  array<int, array<string, mixed>>  $dimensions
     */
    private function userTurn(array $brief, string $ownerContext, array $dimensions): string
    {
        $rubricLines = collect($dimensions)->map(
            fn (array $dimension, int $index) => ($index + 1).". {$dimension['label']} (key: {$dimension['key']}) — {$dimension['description']}",
        )->implode("\n");

        $bullets = implode("\n", array_map(
            fn (string $bullet) => "- {$bullet}",
            array_filter((array) ($brief['bullets'] ?? []), 'is_string'),
        ));

        $context = trim($ownerContext) !== '' ? $ownerContext : '(no owner profile was captured)';

        return <<<TASK
## Rubric

Score each dimension 1–5:
{$rubricLines}

## Owner context

{$context}

## Advisory brief to grade

{$brief['paragraph']}
{$bullets}
TASK;
    }

    /**
     * @param  array<int, array<string, mixed>>  $dimensions
     * @return array<string, array{score: int, reason: string}>|null null when any dimension is missing or malformed
     */
    private function parseScores(string $text, array $dimensions): ?array
    {
        // Defensive: strip markdown fences despite the JSON-only instruction.
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $text) ?? $text;

        $data = json_decode($text, true);
        $raw = is_array($data) ? ($data['scores'] ?? null) : null;

        if (! is_array($raw)) {
            return null;
        }

        $scores = [];

        foreach ($dimensions as $dimension) {
            $entry = $raw[$dimension['key']] ?? null;

            if (! is_array($entry) || ! is_numeric($entry['score'] ?? null)) {
                return null;
            }

            $scores[$dimension['key']] = [
                'score' => max(1, min(5, (int) round((float) $entry['score']))),
                'reason' => is_string($entry['reason'] ?? null) ? $entry['reason'] : '',
            ];
        }

        return $scores;
    }

    /**
     * Weighted mean of the per-dimension scores, weights normalized so the
     * composite always lands on the same 1–5 scale as the dimensions.
     *
     * @param  array<string, array{score: int, reason: string}>  $scores
     * @param  array<int, array<string, mixed>>  $dimensions
     */
    private function composite(array $scores, array $dimensions): float
    {
        $weighted = 0.0;
        $totalWeight = 0.0;

        foreach ($dimensions as $dimension) {
            $weight = max(0.0, (float) ($dimension['weight'] ?? 0));
            $weighted += $scores[$dimension['key']]['score'] * $weight;
            $totalWeight += $weight;
        }

        return $totalWeight > 0.0 ? round($weighted / $totalWeight, 2) : 0.0;
    }
}
