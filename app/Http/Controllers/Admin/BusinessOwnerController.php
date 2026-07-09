<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BusinessOwnerStatus;
use App\Enums\DiscoveryPhase;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBusinessOwnerRequest;
use App\Http\Requests\Admin\UpdateBusinessOwnerRequest;
use App\Models\ActivityEvent;
use App\Models\AiCall;
use App\Models\BusinessOwner;
use App\Models\DiscoverySession;
use App\Models\TaxonomyNiche;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BusinessOwnerController extends Controller
{
    private function nicheOptions()
    {
        return TaxonomyNiche::query()
            ->where('hidden', false)
            ->with('category')
            ->orderBy('sort')
            ->get()
            ->map(fn (TaxonomyNiche $niche) => [
                'id' => $niche->id,
                'name' => $niche->name,
                'category_name' => $niche->category?->name,
            ]);
    }

    public function index(): Response
    {
        $businessOwners = BusinessOwner::query()
            ->withCount('referralTokens')
            ->latest()
            ->get()
            ->map(fn (BusinessOwner $bo) => [
                'id' => $bo->id,
                'name' => $bo->name,
                'company' => $bo->company,
                'status' => $bo->status->value,
                'current_stage' => $bo->current_stage->value,
                'language' => $bo->language?->value,
                'referral_tokens_count' => $bo->referral_tokens_count,
                'created_at' => $bo->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/BusinessOwners/Index', [
            'businessOwners' => $businessOwners,
            'niches' => $this->nicheOptions(),
        ]);
    }

    public function store(StoreBusinessOwnerRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('logo');

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('bo-logos', 'public');
        }

        $businessOwner = BusinessOwner::create($data);

        ActivityEvent::create([
            'business_owner_id' => $businessOwner->id,
            'type' => 'bo_created',
            'payload' => ['name' => $businessOwner->name, 'company' => $businessOwner->company],
        ]);

        return redirect()->route('admin.business-owners.show', $businessOwner)
            ->with('success', 'Business owner created.');
    }

    public function show(BusinessOwner $businessOwner): Response
    {
        $businessOwner->load([
            'referralTokens' => fn ($q) => $q->latest(),
            'activityEvents' => fn ($q) => $q->latest()->limit(20),
            'discoverySession.answers',
            'discoverySession.uploads',
            'discoverySession.dcpProfiles' => fn ($q) => $q->orderByDesc('version'),
            'discoverySession.specDocuments' => fn ($q) => $q->orderByDesc('version'),
        ]);

        $session = $businessOwner->discoverySession;
        $latestDcpProfile = $session?->dcpProfiles->first();

        return Inertia::render('Admin/BusinessOwners/Show', [
            'businessOwner' => [
                'id' => $businessOwner->id,
                'name' => $businessOwner->name,
                'company' => $businessOwner->company,
                'logo_path' => $businessOwner->logo_path ? Storage::disk('public')->url($businessOwner->logo_path) : null,
                'greeting_override' => $businessOwner->greeting_override,
                'admin_context' => $businessOwner->admin_context,
                'language' => $businessOwner->language?->value,
                'pre_selected_niche_id' => $businessOwner->pre_selected_niche_id,
                'status' => $businessOwner->status->value,
                'current_stage' => $businessOwner->current_stage->value,
                'ai_token_cap' => $businessOwner->ai_token_cap,
                'created_at' => $businessOwner->created_at?->toIso8601String(),
            ],
            'niches' => $this->nicheOptions(),
            'referralTokens' => $businessOwner->referralTokens->map(fn ($t) => [
                'id' => $t->id,
                'state' => $t->state->value,
                'expires_at' => $t->expires_at?->toIso8601String(),
                'sent_at' => $t->sent_at?->toIso8601String(),
                'first_visited_at' => $t->first_visited_at?->toIso8601String(),
                'revoked_at' => $t->revoked_at?->toIso8601String(),
                'created_at' => $t->created_at?->toIso8601String(),
            ]),
            'activity' => $businessOwner->activityEvents->map(fn ($e) => [
                'id' => $e->id,
                'type' => $e->type,
                'payload' => $e->payload,
                'created_at' => $e->created_at?->toIso8601String(),
            ]),
            'discovery' => $session ? $this->discoveryProgress($session) : null,
            'answers' => $session ? $this->answersByPhase($session) : [],
            'uploads' => $session ? $session->uploads->map(fn ($u) => array_merge($u->toDiscoveryArray(), ['phase' => $u->phase]))->values() : [],
            'dcpProfile' => $latestDcpProfile ? [
                'version' => $latestDcpProfile->version,
                'is_empty' => $latestDcpProfile->isEmpty(),
                'payload' => $latestDcpProfile->payload,
                'model_meta' => $latestDcpProfile->model_meta,
                'created_at' => $latestDcpProfile->created_at?->toIso8601String(),
            ] : null,
            'specVersions' => $session ? $session->specDocuments->map(fn ($s) => [
                'id' => $s->id,
                'version' => $s->version,
                'generated_by' => $s->generated_by,
                'change_summary' => $s->change_summary,
                'created_at' => $s->created_at?->toIso8601String(),
            ])->values() : [],
            'aiUsage' => $this->boAiUsage($businessOwner),
        ]);
    }

    /**
     * Per-phase discovery progress: completed phases are everything before
     * the session's current_phase (or all of them once submitted), the
     * current_phase is "current", the rest are "upcoming".
     */
    private function discoveryProgress(DiscoverySession $session): array
    {
        $ordered = DiscoveryPhase::ordered();
        $currentIndex = array_search($session->current_phase, $ordered, true);
        $submitted = $session->status === 'submitted';

        $phases = collect($ordered)->map(fn (DiscoveryPhase $phase, int $index) => [
            'value' => $phase->value,
            'label' => $phase->label(),
            'status' => match (true) {
                $submitted => 'completed',
                $index < $currentIndex => 'completed',
                $index === $currentIndex => 'current',
                default => 'upcoming',
            },
        ])->values()->all();

        return [
            'current_phase' => $session->current_phase->value,
            'status' => $session->status,
            'started_at' => $session->started_at?->toIso8601String(),
            'submitted_at' => $session->submitted_at?->toIso8601String(),
            'phases' => $phases,
        ];
    }

    /** Structured answers grouped by phase, in phase order, with humanized field labels. */
    private function answersByPhase(DiscoverySession $session): array
    {
        $order = array_flip(array_map(fn (DiscoveryPhase $p) => $p->value, DiscoveryPhase::ordered()));

        return $session->answers
            ->groupBy('phase')
            ->map(fn ($answers, $phase) => [
                'phase' => $phase,
                'label' => DiscoveryPhase::tryFrom($phase)?->label() ?? $phase,
                'answers' => $answers->map(fn ($a) => [
                    'field_key' => $a->field_key,
                    'label' => Str::headline($a->field_key),
                    'value' => $a->value,
                ])->values(),
            ])
            ->sortBy(fn ($group) => $order[$group['phase']] ?? PHP_INT_MAX)
            ->values()
            ->all();
    }

    /** AI usage & cost scoped to a single BO (§7.7 cap accounting), overall + per-tool. */
    private function boAiUsage(BusinessOwner $businessOwner): array
    {
        $totals = AiCall::query()
            ->where('business_owner_id', $businessOwner->id)
            ->selectRaw('coalesce(sum(input_tokens + output_tokens), 0) as tokens, coalesce(sum(cost_estimate), 0) as cost, count(*) as calls')
            ->first();

        $byTool = AiCall::query()
            ->where('business_owner_id', $businessOwner->id)
            ->select('tool')
            ->selectRaw('sum(input_tokens + output_tokens) as tokens, sum(cost_estimate) as cost, count(*) as calls')
            ->groupBy('tool')
            ->orderByDesc('tokens')
            ->get()
            ->map(fn ($row) => [
                'tool' => $row->tool,
                'tokens' => (int) $row->tokens,
                'cost' => round((float) $row->cost, 6),
                'calls' => (int) $row->calls,
            ]);

        return [
            'total' => ['tokens' => (int) $totals->tokens, 'cost' => round((float) $totals->cost, 6), 'calls' => (int) $totals->calls],
            'by_tool' => $byTool,
        ];
    }

    public function update(UpdateBusinessOwnerRequest $request, BusinessOwner $businessOwner): RedirectResponse
    {
        $data = $request->safe()->except('logo');

        if ($request->hasFile('logo')) {
            if ($businessOwner->logo_path) {
                Storage::disk('public')->delete($businessOwner->logo_path);
            }

            $data['logo_path'] = $request->file('logo')->store('bo-logos', 'public');
        }

        $businessOwner->update($data);

        return back()->with('success', 'Business owner updated.');
    }

    public function destroy(BusinessOwner $businessOwner): RedirectResponse
    {
        $businessOwner->update(['status' => BusinessOwnerStatus::Archived]);
        $businessOwner->delete();

        return redirect()->route('admin.business-owners.index')->with('success', 'Business owner archived.');
    }
}
