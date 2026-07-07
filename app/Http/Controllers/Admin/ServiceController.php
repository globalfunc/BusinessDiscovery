<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ServiceController extends Controller
{
    private function normalizePrices(Request $request): void
    {
        $request->merge([
            'price_min' => $request->input('price_min') === '' ? null : $request->input('price_min'),
            'price_max' => $request->input('price_max') === '' ? null : $request->input('price_max'),
        ]);
    }

    private function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:255'],
            'name_bg' => ['required', 'string', 'max:255'],
            'one_liner_en' => ['required', 'string', 'max:500'],
            'one_liner_bg' => ['required', 'string', 'max:500'],
            'base_features' => ['array'],
            'base_features.*' => ['string', 'max:255'],
            'tags' => ['array'],
            'tags.*' => ['string', 'max:100'],
            'saas_eligible' => ['boolean'],
            'price_min' => ['nullable', 'integer', 'min:0'],
            'price_max' => ['nullable', 'integer', 'min:0', 'gte:price_min'],
            'niche_ids' => ['array'],
            'niche_ids.*' => ['integer', 'exists:taxonomy_niches,id'],
            'recommended_niche_ids' => ['array'],
            'recommended_niche_ids.*' => ['integer', 'exists:taxonomy_niches,id'],
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $this->normalizePrices($request);
        $data = $request->validate($this->rules());

        $service = Service::create([
            'key' => Str::slug($data['name_en']),
            'name' => ['en' => $data['name_en'], 'bg' => $data['name_bg']],
            'one_liner' => ['en' => $data['one_liner_en'], 'bg' => $data['one_liner_bg']],
            'base_features' => $data['base_features'] ?? [],
            'tags' => $data['tags'] ?? [],
            'saas_eligible' => $data['saas_eligible'] ?? false,
            'price_min' => $data['price_min'] ?? null,
            'price_max' => $data['price_max'] ?? null,
        ]);

        $this->syncNiches($service, $data);

        return back()->with('success', 'Service created.');
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $this->normalizePrices($request);
        $data = $request->validate([...$this->rules(), 'hidden' => ['boolean']]);

        $service->update([
            'name' => ['en' => $data['name_en'], 'bg' => $data['name_bg']],
            'one_liner' => ['en' => $data['one_liner_en'], 'bg' => $data['one_liner_bg']],
            'base_features' => $data['base_features'] ?? [],
            'tags' => $data['tags'] ?? [],
            'saas_eligible' => $data['saas_eligible'] ?? false,
            'price_min' => $data['price_min'] ?? null,
            'price_max' => $data['price_max'] ?? null,
            'hidden' => $data['hidden'] ?? $service->hidden,
        ]);

        $this->syncNiches($service, $data);

        return back()->with('success', 'Service updated.');
    }

    private function syncNiches(Service $service, array $data): void
    {
        $nicheIds = $data['niche_ids'] ?? [];
        $recommendedIds = $data['recommended_niche_ids'] ?? [];

        $sync = [];
        foreach ($nicheIds as $nicheId) {
            $sync[$nicheId] = ['recommended' => in_array($nicheId, $recommendedIds)];
        }

        $service->niches()->sync($sync);
    }
}
