<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdvisoryBriefVerdict;
use App\Http\Controllers\Controller;
use App\Models\AdvisoryBrief;
use App\Models\BriefExemplar;
use App\Services\Ai\AiSettings;
use App\Services\Ai\Support\DcpDigest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * S5.7 admin review & labeling surface — the calibration half of the brief-
 * quality loop. Lists every advisory_briefs row (shown, judge-hidden, and
 * gate-dropped alike — the "persist everything" requirement exists exactly so
 * these can be reviewed), filterable by verdict/score/label; the detail view
 * reconstructs what the model saw (DCP digest + exemplar set by persisted
 * id+version) so a bad brief can be blamed on the brief, the exemplars, or
 * the rubric. The good/bad labels are the ground truth the rubric threshold
 * and exemplar library get calibrated against; the rubric editor itself also
 * lives here (updateRubric) rather than on the AI-settings page, keeping the
 * whole loop on one screen.
 */
class AdvisoryBriefController extends Controller
{
    public function index(Request $request, AiSettings $aiSettings): Response
    {
        $filters = $request->validate([
            'verdict' => ['nullable', Rule::enum(AdvisoryBriefVerdict::class)],
            'label' => ['nullable', Rule::in(['good', 'bad', 'unlabeled'])],
            'phase' => ['nullable', 'string', 'max:32'],
            'min_composite' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'max_composite' => ['nullable', 'numeric', 'min:1', 'max:5'],
        ]);

        $briefs = AdvisoryBrief::query()
            ->with('businessOwner:id,name,company')
            ->when($filters['verdict'] ?? null, fn ($q, $verdict) => $q->where('verdict', $verdict))
            ->when($filters['phase'] ?? null, fn ($q, $phase) => $q->where('phase', $phase))
            ->when(($filters['label'] ?? null) === 'unlabeled', fn ($q) => $q->whereNull('label'))
            ->when(in_array($filters['label'] ?? null, ['good', 'bad'], true), fn ($q) => $q->where('label', $filters['label']))
            ->when($filters['min_composite'] ?? null, fn ($q, $min) => $q->where('composite', '>=', $min))
            ->when($filters['max_composite'] ?? null, fn ($q, $max) => $q->where('composite', '<=', $max))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (AdvisoryBrief $brief) => [
                'id' => $brief->id,
                'business_owner' => $brief->businessOwner?->only(['id', 'name', 'company']),
                'phase' => $brief->phase,
                'module' => $brief->module,
                'verdict' => $brief->verdict->value,
                'drop_reason' => $brief->drop_reason,
                'composite' => $brief->composite,
                'label' => $brief->label,
                'paragraph_excerpt' => is_string($brief->brief['paragraph'] ?? null)
                    ? mb_substr($brief->brief['paragraph'], 0, 140)
                    : null,
                'created_at' => $brief->created_at->toDateTimeString(),
            ]);

        return Inertia::render('Admin/AdvisoryBriefs/Index', [
            'briefs' => $briefs,
            'filters' => $filters,
            'rubric' => $aiSettings->briefRubric(),
        ]);
    }

    public function show(AdvisoryBrief $advisoryBrief): Response
    {
        $advisoryBrief->load(['businessOwner:id,name,company', 'dcpProfile']);

        // The exemplar rows whose id+version were in context. A version drift
        // note lets the admin see when an exemplar has since been rewritten —
        // the persisted set is what the model actually saw, not the current text.
        $inContext = collect($advisoryBrief->exemplars ?? []);
        $exemplarRows = BriefExemplar::query()->whereIn('id', $inContext->pluck('id'))->get()->keyBy('id');

        return Inertia::render('Admin/AdvisoryBriefs/Show', [
            'brief' => [
                'id' => $advisoryBrief->id,
                'business_owner' => $advisoryBrief->businessOwner?->only(['id', 'name', 'company']),
                'phase' => $advisoryBrief->phase,
                'module' => $advisoryBrief->module,
                'brief' => $advisoryBrief->brief,
                'verdict' => $advisoryBrief->verdict->value,
                'drop_reason' => $advisoryBrief->drop_reason,
                'scores' => $advisoryBrief->scores,
                'composite' => $advisoryBrief->composite,
                'judge_model' => $advisoryBrief->judge_model,
                'rubric_version' => $advisoryBrief->rubric_version,
                'label' => $advisoryBrief->label,
                'model' => $advisoryBrief->model,
                'prompt_version' => $advisoryBrief->prompt_version,
                'created_at' => $advisoryBrief->created_at->toDateTimeString(),
                'dcp_digest' => DcpDigest::fromProfile($advisoryBrief->dcpProfile),
                'exemplars' => $inContext->map(function (array $ref) use ($exemplarRows) {
                    $row = $exemplarRows->get($ref['id'] ?? 0);

                    return [
                        'id' => $ref['id'] ?? null,
                        'version_in_context' => $ref['version'] ?? null,
                        'current_version' => $row?->version,
                        'dcp_excerpt' => $row?->dcp_excerpt,
                        'exemplar_brief' => $row?->exemplar_brief,
                        'deleted' => $row === null,
                    ];
                })->values(),
            ],
        ]);
    }

    /** The admin's ground-truth calibration mark: good | bad | null (clear). */
    public function label(Request $request, AdvisoryBrief $advisoryBrief): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['nullable', Rule::in(['good', 'bad'])],
        ]);

        $advisoryBrief->update(['label' => $data['label'] ?? null]);

        return back()->with('success', 'Brief label saved.');
    }

    /**
     * Rubric editor: dimensions (key/label/description/weight), threshold,
     * mode. The rubric version auto-bumps when the dimensions change —
     * threshold/mode only alter the reveal decision, not what a stored score
     * means, so they don't invalidate comparability across graded rows.
     */
    public function updateRubric(Request $request, AiSettings $aiSettings): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['log_only', 'enforce'])],
            'threshold' => ['required', 'numeric', 'min:1', 'max:5'],
            'dimensions' => ['required', 'array', 'min:1'],
            'dimensions.*.key' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/'],
            'dimensions.*.label' => ['required', 'string', 'max:120'],
            'dimensions.*.description' => ['required', 'string', 'max:600'],
            'dimensions.*.weight' => ['required', 'numeric', 'min:0.01', 'max:1'],
        ]);

        $current = $aiSettings->briefRubric();

        // Normalize types before the change comparison — form input arrives
        // stringly ("0.3") and would otherwise bump the version every save.
        $dimensions = array_map(fn (array $dimension) => [
            'key' => $dimension['key'],
            'label' => $dimension['label'],
            'description' => $dimension['description'],
            'weight' => (float) $dimension['weight'],
        ], array_values($data['dimensions']));

        $aiSettings->setBriefRubric([
            'version' => $dimensions == ($current['dimensions'] ?? [])
                ? (int) ($current['version'] ?? 1)
                : (int) ($current['version'] ?? 1) + 1,
            'mode' => $data['mode'],
            'threshold' => (float) $data['threshold'],
            'dimensions' => $dimensions,
        ]);

        return back()->with('success', 'Grading rubric saved.');
    }
}
