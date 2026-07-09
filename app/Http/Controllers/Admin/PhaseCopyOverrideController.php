<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PhaseCopyOverride;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Admin editor for §6.6 phase-copy overrides. One row per (phase, language);
 * saving an all-blank form deletes the row so BO-facing pages fall back to
 * the static bg/en lang JSON default (see resources/js/lib/i18n.ts).
 */
class PhaseCopyOverrideController extends Controller
{
    public function update(Request $request, string $phase, string $language): RedirectResponse
    {
        if (! in_array($phase, PhaseCopyOverride::phases(), true) || ! in_array($language, ['bg', 'en'], true)) {
            abort(404);
        }

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:2000'],
            'helper' => ['nullable', 'string', 'max:2000'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        $title = $data['title'] ?? null;
        $helper = $data['helper'] ?? null;
        $body = $data['body'] ?? null;

        if ($title === null && $helper === null && $body === null) {
            PhaseCopyOverride::query()->where('phase', $phase)->where('language', $language)->delete();

            return back()->with('success', 'Override cleared.');
        }

        PhaseCopyOverride::query()->updateOrCreate(
            ['phase' => $phase, 'language' => $language],
            ['title' => $title, 'helper' => $helper, 'body' => $body],
        );

        return back()->with('success', 'Phase copy updated.');
    }
}
