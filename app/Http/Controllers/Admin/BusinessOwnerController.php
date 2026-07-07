<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BusinessOwnerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBusinessOwnerRequest;
use App\Http\Requests\Admin\UpdateBusinessOwnerRequest;
use App\Models\ActivityEvent;
use App\Models\BusinessOwner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BusinessOwnerController extends Controller
{
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
        $businessOwner->load(['referralTokens' => fn ($q) => $q->latest(), 'activityEvents' => fn ($q) => $q->latest()->limit(20)]);

        return Inertia::render('Admin/BusinessOwners/Show', [
            'businessOwner' => [
                'id' => $businessOwner->id,
                'name' => $businessOwner->name,
                'company' => $businessOwner->company,
                'logo_path' => $businessOwner->logo_path ? Storage::disk('public')->url($businessOwner->logo_path) : null,
                'greeting_override' => $businessOwner->greeting_override,
                'admin_context' => $businessOwner->admin_context,
                'language' => $businessOwner->language?->value,
                'status' => $businessOwner->status->value,
                'current_stage' => $businessOwner->current_stage->value,
                'created_at' => $businessOwner->created_at?->toIso8601String(),
            ],
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
        ]);
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
