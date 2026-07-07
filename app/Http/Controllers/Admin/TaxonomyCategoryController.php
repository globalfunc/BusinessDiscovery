<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TaxonomyCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaxonomyCategoryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name_en' => ['required', 'string', 'max:255'],
            'name_bg' => ['required', 'string', 'max:255'],
            'sort' => ['nullable', 'integer'],
        ]);

        TaxonomyCategory::create([
            'name' => ['en' => $data['name_en'], 'bg' => $data['name_bg']],
            'sort' => $data['sort'] ?? 0,
        ]);

        return back()->with('success', 'Category created.');
    }

    public function update(Request $request, TaxonomyCategory $taxonomyCategory): RedirectResponse
    {
        $data = $request->validate([
            'name_en' => ['required', 'string', 'max:255'],
            'name_bg' => ['required', 'string', 'max:255'],
            'sort' => ['nullable', 'integer'],
            'hidden' => ['boolean'],
        ]);

        $taxonomyCategory->update([
            'name' => ['en' => $data['name_en'], 'bg' => $data['name_bg']],
            'sort' => $data['sort'] ?? $taxonomyCategory->sort,
            'hidden' => $data['hidden'] ?? $taxonomyCategory->hidden,
        ]);

        return back()->with('success', 'Category updated.');
    }
}
