<?php

namespace App\Http\Middleware;

use App\Models\PhaseCopyOverride;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'auth' => [
                'user' => $request->user()?->only('id', 'name', 'email'),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'plainReferralUrl' => fn () => $request->session()->get('plainReferralUrl'),
            ],
            // §6.6 phase-copy overrides, merged over the static bg/en lang
            // JSON by resources/js/lib/i18n.ts. Small table, cheap on every
            // request; only non-null fields are included per row.
            'phaseCopyOverrides' => fn () => PhaseCopyOverride::query()
                ->get()
                ->groupBy('language')
                ->map(fn ($rows) => $rows->keyBy('phase')->map(fn (PhaseCopyOverride $row) => array_filter([
                    'title' => $row->title,
                    'helper' => $row->helper,
                    'body' => $row->body,
                ], fn ($value) => $value !== null)))
                ->toArray(),
        ];
    }
}
