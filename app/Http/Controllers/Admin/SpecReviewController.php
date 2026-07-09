<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessOwner;
use App\Models\DiscoverySession;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * §6.4 Spec review & Proposal builder: rendered/raw spec preview + version
 * diff list (reusing the BO-side §3.8 renderer) plus the "decision surface"
 * — a scannable summary of selected services, billing/budget/timeline, and
 * branding directions, pulled from the same structured data the spec itself
 * is compiled from (selected_services + phase_3/phase_6 discovery_answers).
 */
class SpecReviewController extends Controller
{
    public function show(BusinessOwner $businessOwner): Response
    {
        $businessOwner->load([
            'discoverySession.specDocuments' => fn ($q) => $q->orderByDesc('version'),
            'discoverySession.selectedServices.service',
            'discoverySession.answers' => fn ($q) => $q->whereIn('phase', ['phase_3', 'phase_6']),
        ]);

        $session = $businessOwner->discoverySession;
        $locale = $businessOwner->language?->value ?? 'en';

        return Inertia::render('Admin/BusinessOwners/Spec', [
            'businessOwner' => [
                'id' => $businessOwner->id,
                'name' => $businessOwner->name,
                'company' => $businessOwner->company,
                'language' => $locale,
            ],
            'versions' => $session ? $session->specDocuments->map(fn ($s) => [
                'id' => $s->id,
                'version' => $s->version,
                'markdown' => $s->markdown,
                'generated_by' => $s->generated_by,
                'change_summary' => $s->change_summary,
                'created_at' => $s->created_at?->toIso8601String(),
            ])->values() : [],
            'decisionSurface' => $session ? $this->decisionSurface($session, $locale) : null,
        ]);
    }

    private function decisionSurface(DiscoverySession $session, string $locale): array
    {
        $answersByKey = fn (string $phase) => $session->answers
            ->where('phase', $phase)
            ->keyBy('field_key')
            ->map(fn ($a) => $a->value);

        $phase6 = $answersByKey('phase_6');
        $phase3 = $answersByKey('phase_3');

        return [
            'services' => $session->selectedServices->map(fn ($record) => [
                'id' => $record->id,
                'name' => $record->custom ? $record->name : ($record->service?->name[$locale] ?? $record->service?->name['en'] ?? $record->name),
                'description' => $record->custom ? $record->description : ($record->service?->one_liner[$locale] ?? null),
                'features' => $record->features ?? [],
                'priority' => $record->priority,
                'custom' => $record->custom,
                'origin' => $record->origin,
                'price_min' => $record->price_min,
                'price_max' => $record->price_max,
            ])->values(),
            'billing' => [
                'billing_model' => $phase6->get('billing_model'),
                'budget_min' => $phase6->get('budget_min'),
                'budget_max' => $phase6->get('budget_max'),
                'timeline_choice' => $phase6->get('timeline_choice'),
                'timeline_note' => $phase6->get('timeline_note'),
            ],
            'branding' => [
                'style_chips' => $phase3->get('style_chips', []),
                'color_preset' => $phase3->get('color_preset'),
                'color_custom_hex' => $phase3->get('color_custom_hex'),
                'accepted_directions' => Collection::make($phase3->get('accepted_suggestions', []))
                    ->map(fn ($card) => [
                        'title' => $card['title'] ?? null,
                        'summary' => $card['summary'] ?? null,
                        'features' => $card['features'] ?? [],
                        'note' => $card['note'] ?? null,
                    ])
                    ->values(),
            ],
        ];
    }
}
