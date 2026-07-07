<?php

namespace App\Http\Controllers\Discovery;

use App\Http\Controllers\Controller;
use App\Models\BusinessOwner;
use App\Models\DiscoverySession;
use App\Models\SelectedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SelectedServiceController extends Controller
{
    /**
     * Add a catalog service (service_id set) or a custom entry
     * (service_id omitted, name/description/features supplied by the BO).
     */
    public function store(Request $request): JsonResponse
    {
        $session = $this->currentSession($request);

        $data = $request->validate([
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'name' => ['required_without:service_id', 'nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:255'],
            'reference_links' => ['nullable', 'array'],
            'reference_links.*' => ['string', 'max:500'],
        ]);

        if (! empty($data['service_id'])) {
            $selected = $session->selectedServices()->firstOrCreate(
                ['service_id' => $data['service_id']],
                [
                    'custom' => false,
                    'origin' => 'catalog',
                    'features' => $data['features'] ?? [],
                    'priority' => false,
                ],
            );
        } else {
            $selected = $session->selectedServices()->create([
                'service_id' => null,
                'custom' => true,
                'origin' => 'bo_custom',
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
    }
}
