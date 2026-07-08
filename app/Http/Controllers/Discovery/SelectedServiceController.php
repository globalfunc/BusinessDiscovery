<?php

namespace App\Http\Controllers\Discovery;

use App\Http\Controllers\Controller;
use App\Models\BusinessOwner;
use App\Models\DiscoverySession;
use App\Models\SelectedService;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SelectedServiceController extends Controller
{
    /**
     * Add a catalog service (service_id set), a BO-authored custom entry
     * (service_id omitted), or an accepted AI suggestion (origin=ai_suggestion,
     * §3.3/S3.2). An accepted suggestion whose related_catalog_key resolves to
     * a real service links to that catalog row (keeping its curated features);
     * otherwise it lands as a bespoke custom service — both tagged
     * origin=ai_suggestion so provenance survives (§5.3).
     */
    public function store(Request $request): JsonResponse
    {
        $session = $this->currentSession($request);

        abort_if($session->status === 'submitted', 403, 'This discovery has already been submitted.');

        $data = $request->validate([
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'origin' => ['nullable', 'in:catalog,bo_custom,ai_suggestion'],
            'related_catalog_key' => ['nullable', 'string', 'max:255'],
            'name' => ['required_without_all:service_id,related_catalog_key', 'nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:255'],
            'reference_links' => ['nullable', 'array'],
            'reference_links.*' => ['string', 'max:500'],
        ]);

        $origin = $data['origin'] ?? null;

        // Resolve an accepted suggestion's catalog link to a service_id.
        if (empty($data['service_id']) && ! empty($data['related_catalog_key'])) {
            $data['service_id'] = Service::where('key', $data['related_catalog_key'])
                ->where('hidden', false)
                ->value('id');
        }

        if (! empty($data['service_id'])) {
            $selected = $session->selectedServices()->firstOrCreate(
                ['service_id' => $data['service_id']],
                [
                    'custom' => false,
                    'origin' => $origin ?? 'catalog',
                    'features' => $data['features'] ?? [],
                    'priority' => false,
                ],
            );
        } else {
            $selected = $session->selectedServices()->create([
                'service_id' => null,
                'custom' => true,
                'origin' => $origin ?? 'bo_custom',
                'name' => $data['name'] ?? null,
                'description' => $data['description'] ?? null,
                'features' => $data['features'] ?? [],
                'reference_links' => $data['reference_links'] ?? [],
                'priority' => false,
            ]);
        }

        return response()->json(['selectedService' => $selected->toDiscoveryArray()]);
    }

    public function update(Request $request, SelectedService $selectedService): JsonResponse
    {
        $this->authorizeOwnership($request, $selectedService);

        $data = $request->validate([
            'features' => ['sometimes', 'array'],
            'features.*' => ['string', 'max:255'],
            'priority' => ['sometimes', 'boolean'],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'reference_links' => ['sometimes', 'array'],
            'reference_links.*' => ['string', 'max:500'],
        ]);

        $selectedService->update($data);

        return response()->json(['selectedService' => $selectedService->fresh()->toDiscoveryArray()]);
    }

    public function destroy(Request $request, SelectedService $selectedService): JsonResponse
    {
        $this->authorizeOwnership($request, $selectedService);

        $selectedService->delete();

        return response()->json(['deleted' => true]);
    }

    private function currentSession(Request $request): DiscoverySession
    {
        /** @var BusinessOwner $businessOwner */
        $businessOwner = $request->attributes->get('businessOwner');

        return DiscoverySession::where('business_owner_id', $businessOwner->id)->firstOrFail();
    }

    private function authorizeOwnership(Request $request, SelectedService $selectedService): void
    {
        $session = $this->currentSession($request);

        abort_unless($selectedService->discovery_session_id === $session->id, 403);
        abort_if($session->status === 'submitted', 403, 'This discovery has already been submitted.');
    }
}
