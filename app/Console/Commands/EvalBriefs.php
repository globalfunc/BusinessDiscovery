<?php

namespace App\Console\Commands;

use App\Services\Ai\AiCallRequest;
use App\Services\Ai\AiClient;
use App\Services\Ai\AiSettings;
use App\Services\Ai\PromptTemplateRegistry;
use App\Services\Ai\Tools\Suggest\BriefExemplarSelector;
use App\Services\Ai\Tools\Suggest\BriefGrader;
use App\Services\Ai\Tools\Suggest\BriefPrompt;
use App\Services\Ai\Tools\Suggest\BriefQualityGate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * S5.7 offline eval harness. Runs the gold set (database/eval/
 * brief-gold-set.json — representative DCP digests with human-graded target
 * briefs) against the CURRENT prompt + exemplar library + rubric, so any
 * change to the generation prompt, the exemplars, the rubric, or the judge
 * model is regression-tested before shipping instead of judged by vibes.
 *
 * Two layers per case:
 *  - Judge calibration (always): the judge grades the human-graded target
 *    brief; a pass-expected case must clear the rubric threshold, a
 *    fail-expected case must not. Misses mean the rubric/judge disagrees
 *    with the human standard.
 *  - Generation (--generate): a fresh brief is generated from the gold DCP
 *    through the live suggest.content_social system prompt + exemplar
 *    selection, then run through the deterministic gate and the judge —
 *    end-to-end pass means a live BO with that profile would see a brief.
 *
 * Results are written to storage/app/brief-eval/last-run.json and compared
 * against the previous run: any case that passed before and fails now is
 * reported as a regression (exit code 1).
 */
class EvalBriefs extends Command
{
    protected $signature = 'briefs:eval
        {--generate : Also generate a fresh brief per case through the live prompt + exemplars (extra AI calls)}
        {--set= : Path to an alternative gold-set JSON file}';

    protected $description = 'Run the advisory-brief gold set against the current prompt, exemplars and grading rubric';

    private const RESULTS_PATH = 'brief-eval/last-run.json';

    public function handle(
        AiClient $aiClient,
        AiSettings $aiSettings,
        PromptTemplateRegistry $templates,
        BriefGrader $grader,
        BriefQualityGate $gate,
        BriefExemplarSelector $selector,
    ): int {
        $path = $this->option('set') ?: database_path('eval/brief-gold-set.json');

        if (! is_file($path)) {
            $this->error("Gold set not found at {$path}.");

            return self::FAILURE;
        }

        $cases = json_decode((string) file_get_contents($path), true)['cases'] ?? [];

        if ($cases === []) {
            $this->error('Gold set contains no cases.');

            return self::FAILURE;
        }

        $rubric = $aiSettings->briefRubric();
        $threshold = (float) ($rubric['threshold'] ?? 3.5);
        $this->info(sprintf(
            'Evaluating %d cases against rubric v%d (threshold %.2f, mode %s)%s.',
            count($cases),
            $rubric['version'] ?? 1,
            $threshold,
            $rubric['mode'] ?? 'log_only',
            $this->option('generate') ? ' + live generation' : '',
        ));

        $results = [];
        $rows = [];

        foreach ($cases as $case) {
            $key = $case['key'] ?? 'unnamed';
            $expectPass = ($case['expect'] ?? 'pass') === 'pass';

            $grade = $grader->grade($case['target_brief'] ?? null, $case['dcp_digest'] ?? '');
            $judgePassed = $grade !== null && $grade->passes();
            $judgeOk = $grade !== null && $judgePassed === $expectPass;

            $generation = $this->option('generate')
                ? $this->generateAndScore($case, $aiClient, $templates, $grader, $gate, $selector)
                : null;

            $results[$key] = [
                'expect' => $expectPass ? 'pass' : 'fail',
                'judge_composite' => $grade?->composite,
                'judge_ok' => $judgeOk,
                'generation' => $generation,
            ];

            $rows[] = [
                $key,
                $expectPass ? 'pass' : 'fail',
                $grade !== null ? number_format($grade->composite, 2) : 'ERROR',
                $judgeOk ? 'OK' : 'MISS',
                $generation === null ? '—' : ($generation['ok'] ? "revealed ({$generation['composite']})" : ($generation['reason'] ?? 'hidden')),
            ];
        }

        $this->table(['case', 'expect', 'judge composite', 'judge vs human', 'generated brief'], $rows);

        $judgeOkCount = count(array_filter($results, fn ($r) => $r['judge_ok']));
        $this->info(sprintf('Judge calibration: %d/%d cases match the human grade.', $judgeOkCount, count($results)));

        if ($this->option('generate')) {
            $generated = array_filter($results, fn ($r) => $r['generation'] !== null);
            $revealed = array_filter($generated, fn ($r) => $r['generation']['ok']);
            $this->info(sprintf('Generation: %d/%d gold DCPs produced a brief that would be revealed.', count($revealed), count($generated)));
        }

        $regressions = $this->regressions($results);
        Storage::put(self::RESULTS_PATH, json_encode([
            'ran_at' => now()->toIso8601String(),
            'rubric_version' => $rubric['version'] ?? 1,
            'threshold' => $threshold,
            'results' => $results,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if ($regressions !== []) {
            $this->error('Regressions vs previous run: '.implode(', ', $regressions));

            return self::FAILURE;
        }

        $this->info('No regressions vs previous run.');

        return self::SUCCESS;
    }

    /**
     * The --generate leg: live system prompt (incl. any admin override) +
     * live exemplar selection for this case's context, asking for the brief
     * alone — then the same deterministic gate + judge a live call would face.
     *
     * @param  array<string, mixed>  $case
     * @return array{ok: bool, composite: ?float, reason: ?string}
     */
    private function generateAndScore(
        array $case,
        AiClient $aiClient,
        PromptTemplateRegistry $templates,
        BriefGrader $grader,
        BriefQualityGate $gate,
        BriefExemplarSelector $selector,
    ): array {
        $digest = $case['dcp_digest'] ?? '';
        $exemplarsBlock = BriefPrompt::exemplarsBlock($selector->selectForText($digest));

        $sections = array_filter([
            $exemplarsBlock !== '' ? "## Brief exemplars\n\n{$exemplarsBlock}" : null,
            "## Owner context\n\n{$digest}",
            "## Task\n\nWrite ONLY the advisory brief for this owner — no suggestion cards. Respond with a single JSON object shaped exactly as {\"brief\": {\"paragraph\": \"...\", \"bullets\": [\"...\"]}}.\n\n".BriefPrompt::instruction(),
        ]);

        $result = $aiClient->call(new AiCallRequest(
            tool: 'suggest.content_social',
            messages: [['role' => 'user', 'content' => implode("\n\n", $sections)]],
            system: $templates->get('suggest.content_social')->systemPrompt(),
        ));

        if (! $result->successful || $result->text === null) {
            return ['ok' => false, 'composite' => null, 'reason' => 'generation_failed'];
        }

        $text = trim($result->text);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $text) ?? $text;
        $brief = json_decode($text, true)['brief'] ?? null;

        $dropReason = $gate->evaluate($brief, array_map('mb_strtolower', (array) ($case['grounding_tokens'] ?? [])));

        if ($dropReason !== null) {
            return ['ok' => false, 'composite' => null, 'reason' => "gate: {$dropReason}"];
        }

        $grade = $grader->grade($brief, $digest);

        if ($grade === null) {
            return ['ok' => false, 'composite' => null, 'reason' => 'grade_failed'];
        }

        return ['ok' => $grade->passes(), 'composite' => $grade->composite, 'reason' => $grade->passes() ? null : 'below_threshold'];
    }

    /**
     * Cases whose judge calibration (or generation leg, when both runs had
     * one) passed last run but fails now.
     *
     * @param  array<string, array<string, mixed>>  $results
     * @return string[]
     */
    private function regressions(array $results): array
    {
        $previous = json_decode(Storage::get(self::RESULTS_PATH) ?? '', true)['results'] ?? null;

        if (! is_array($previous)) {
            return [];
        }

        $regressions = [];

        foreach ($results as $key => $result) {
            $before = $previous[$key] ?? null;

            if ($before === null) {
                continue;
            }

            if (($before['judge_ok'] ?? false) && ! $result['judge_ok']) {
                $regressions[] = "{$key} (judge)";
            }

            if (($before['generation']['ok'] ?? false) && $result['generation'] !== null && ! $result['generation']['ok']) {
                $regressions[] = "{$key} (generation)";
            }
        }

        return $regressions;
    }
}
