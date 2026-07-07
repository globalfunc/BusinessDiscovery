<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TaxonomyCategory;
use App\Models\TaxonomyNiche;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaxonomyNicheController extends Controller
{
    public function store(Request $request, TaxonomyCategory $taxonomyCategory): RedirectResponse
    {
        $data = $request->validate([
            'name_en' => ['required', 'string', 'max:255'],
            'name_bg' => ['required', 'string', 'max:255'],
            'sort' => ['nullable', 'integer'],
        ]);

        $taxonomyCategory->niches()->create([
            'name' => ['en' => $data['name_en'], 'bg' => $data['name_bg']],
            'sort' => $data['sort'] ?? 0,
        ]);

        return back()->with('success', 'Niche created.');
    }

    public function update(Request $request, TaxonomyNiche $taxonomyNiche): RedirectResponse
    {
        $data = $request->validate([
            'name_en' => ['required', 'string', 'max:255'],
            'name_bg' => ['required', 'string', 'max:255'],
            'sort' => ['nullable', 'integer'],
            'hidden' => ['boolean'],
        ]);

        $taxonomyNiche->update([
            'name' => ['en' => $data['name_en'], 'bg' => $data['name_bg']],
            'sort' => $data['sort'] ?? $taxonomyNiche->sort,
            'hidden' => $data['hidden'] ?? $taxonomyNiche->hidden,
        ]);

        return back()->with('success', 'Niche updated.');
    }
}
