<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PipelineStage;
use App\Http\Controllers\Controller;
use App\Models\ActivityEvent;
use App\Models\BusinessOwner;
use App\Models\LeadStage;
use App\Models\TaxonomyCategory;
use App\Models\TaxonomyNiche;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PipelineController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'taxonomy_category_id' => ['nullable', 'integer', 'exists:taxonomy_categories,id'],
            'taxonomy_niche_id' => ['nullable', 'integer', 'exists:taxonomy_niches,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $businessOwners = BusinessOwner::query()
            ->with(['preSelectedNiche.category', 'leadStages' => fn ($q) => $q->latest('changed_at')->limit(1)])
            ->when(
                $filters['taxonomy_niche_id'] ?? null,
                fn ($q, $nicheId) => $q->where('pre_selected_niche_id', $nicheId),
            )
            ->when(
                $filters['taxonomy_category_id'] ?? null,
                fn ($q, $categoryId) => $q->whereHas(
                    'preSelectedNiche',
                    fn ($niche) => $niche->where('taxonomy_category_id', $categoryId),
                ),
            )
            ->when(
                $filters['date_from'] ?? null,
                fn ($q, $date) => $q->whereDate('created_at', '>=', $date),
            )
            ->when(
                $filters['date_to'] ?? null,
                fn ($q, $date) => $q->whereDate('created_at', '<=', $date),
            )
            ->latest()
            ->get()
            ->map(fn (BusinessOwner $bo) => [
                'id' => $bo->id,
                'name' => $bo->name,
                'company' => $bo->company,
                'current_stage' => $bo->current_stage->value,
                'niche' => $bo->preSelectedNiche ? [
                    'id' => $bo->preSelectedNiche->id,
                    'name' => $bo->preSelectedNiche->name,
                    'category_name' => $bo->preSelectedNiche->category?->name,
                ] : null,
                'note' => $bo->leadStages->first()?->note,
                'created_at' => $bo->created_at?->toIso8601String(),
            ]);

        $categories = TaxonomyCategory::query()
            ->where('hidden', false)
            ->orderBy('sort')
            ->get()
            ->map(fn (TaxonomyCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
            ]);

        $niches = TaxonomyNiche::query()
            ->where('hidden', false)
            ->orderBy('sort')
            ->get()
            ->map(fn (TaxonomyNiche $niche) => [
                'id' => $niche->id,
                'name' => $niche->name,
                'taxonomy_category_id' => $niche->taxonomy_category_id,
            ]);

        $stages = collect(PipelineStage::cases())->map(fn (PipelineStage $stage) => [
            'value' => $stage->value,
            'label' => $stage->label(),
        ]);

        return Inertia::render('Admin/Pipeline/Index', [
            'businessOwners' => $businessOwners,
            'categories' => $categories,
            'niches' => $niches,
            'stages' => $stages,
            'filters' => $filters,
        ]);
    }

    public function updateStage(Request $request, BusinessOwner $businessOwner): RedirectResponse
    {
        $data = $request->validate([
            'stage' => ['required', 'string', 'in:'.implode(',', array_column(PipelineStage::cases(), 'value'))],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $stage = PipelineStage::from($data['stage']);
        $now = now();

        LeadStage::create([
            'business_owner_id' => $businessOwner->id,
            'stage' => $stage,
            'note' => $data['note'] ?? null,
            'changed_by' => Auth::id(),
            'changed_at' => $now,
        ]);

        $businessOwner->update(['current_stage' => $stage]);

        ActivityEvent::create([
            'business_owner_id' => $businessOwner->id,
            'type' => 'stage_changed',
            'payload' => ['stage' => $stage->value, 'note' => $data['note'] ?? null],
        ]);

        return back()->with('success', 'Stage updated.');
    }
}
