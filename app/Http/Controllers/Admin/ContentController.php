<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Setting;
use App\Models\TaxonomyCategory;
use Inertia\Inertia;
use Inertia\Response;

class ContentController extends Controller
{
    public function index(): Response
    {
        $categories = TaxonomyCategory::query()
            ->with(['niches' => fn ($q) => $q->orderBy('sort')])
            ->orderBy('sort')
            ->get()
            ->map(fn (TaxonomyCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'sort' => $category->sort,
                'hidden' => $category->hidden,
                'niches' => $category->niches->map(fn ($niche) => [
                    'id' => $niche->id,
                    'name' => $niche->name,
                    'sort' => $niche->sort,
                    'hidden' => $niche->hidden,
                ]),
            ]);

        $services = Service::query()
            ->with('niches')
            ->orderBy('key')
            ->get()
            ->map(fn (Service $service) => [
                'id' => $service->id,
                'key' => $service->key,
                'name' => $service->name,
                'one_liner' => $service->one_liner,
                'base_features' => $service->base_features,
                'saas_eligible' => $service->saas_eligible,
                'tags' => $service->tags,
                'price_min' => $service->price_min,
                'price_max' => $service->price_max,
                'hidden' => $service->hidden,
                'niche_ids' => $service->niches->pluck('id'),
                'recommended_niche_ids' => $service->niches->filter(fn ($n) => $n->pivot->recommended)->pluck('id'),
            ]);

        return Inertia::render('Admin/Content/Index', [
            'categories' => $categories,
            'services' => $services,
            'showPricesToBo' => (bool) (Setting::query()->where('key', 'show_prices_to_bo')->first()?->value['enabled'] ?? false),
        ]);
    }
}
