<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VendorBlocklistTerm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin CRUD for the §7.6.2 vendor blocklist (§6.6 "Vendor blocklist editor").
 * Function over polish: names/regex, per-term generic replacement, active flag.
 * Regex rows are validated to compile before save so a bad pattern can never
 * reach — and silently break — the runtime output filter.
 */
class VendorBlocklistController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Vendor/Blocklist', [
            'terms' => VendorBlocklistTerm::query()
                ->orderBy('category')
                ->orderBy('term')
                ->get(['id', 'term', 'is_regex', 'replacement', 'active', 'category']),
            'defaultReplacement' => (string) config('ai.vendor_redaction_label'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        VendorBlocklistTerm::create($this->validated($request));

        return back()->with('success', 'Blocklist term added.');
    }

    public function update(Request $request, VendorBlocklistTerm $vendorBlocklistTerm): RedirectResponse
    {
        $vendorBlocklistTerm->update($this->validated($request));

        return back()->with('success', 'Blocklist term updated.');
    }

    public function destroy(VendorBlocklistTerm $vendorBlocklistTerm): RedirectResponse
    {
        $vendorBlocklistTerm->delete();

        return back()->with('success', 'Blocklist term removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'term' => ['required', 'string', 'max:255'],
            'is_regex' => ['boolean'],
            'replacement' => ['nullable', 'string', 'max:255'],
            'active' => ['boolean'],
            'category' => ['nullable', 'string', 'max:255'],
        ]);

        if (($data['is_regex'] ?? false) && @preg_match('/'.$data['term'].'/iu', '') === false) {
            throw ValidationException::withMessages([
                'term' => 'That regular expression is invalid (remember to escape any literal "/").',
            ]);
        }

        return [
            'term' => $data['term'],
            'is_regex' => $data['is_regex'] ?? false,
            'replacement' => $data['replacement'] ?? null,
            'active' => $data['active'] ?? true,
            'category' => $data['category'] ?? null,
        ];
    }
}
