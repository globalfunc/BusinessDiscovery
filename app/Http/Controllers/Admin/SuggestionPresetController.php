<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DiscoveryPhase;
use App\Http\Controllers\Controller;
use App\Models\SuggestionPreset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin CRUD for §6.6 suggestion presets: the static Suggestion Cards an AI
 * suggest.* tool blends with as inspiration, and the §7.7 fallback shown
 * when AI is unavailable. `cards` follows the §7.4 card shape validated by
 * SuggestionSchemaValidator; only suggest.services cards carry
 * `related_catalog_key`, so it's optional here regardless of phase.
 */
class SuggestionPresetController extends Controller
{
    /**
     * Phases that have a suggest.* tool (and therefore presets); Phase 0/1/6
     * and Review have none.
     *
     * @return list<string>
     */
    public static function presetPhases(): array
    {
        return [
            DiscoveryPhase::Phase2->value,
            DiscoveryPhase::Phase3->value,
            DiscoveryPhase::Phase4->value,
            DiscoveryPhase::Phase5->value,
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        SuggestionPreset::query()->updateOrCreate(
            ['taxonomy_niche_id' => $data['taxonomy_niche_id'], 'phase' => $data['phase']],
            ['cards' => $data['cards']],
        );

        return back()->with('success', 'Suggestion preset saved.');
    }

    public function update(Request $request, SuggestionPreset $suggestionPreset): RedirectResponse
    {
        $data = $request->validate([
            'cards' => ['required', 'array', 'min:1'],
            'cards.*.title' => ['required', 'string', 'max:255'],
            'cards.*.summary' => ['required', 'string', 'max:1000'],
            'cards.*.features' => ['required', 'array', 'min:3'],
            'cards.*.features.*' => ['required', 'string', 'max:255'],
            'cards.*.rationale' => ['required', 'string', 'max:1000'],
            'cards.*.tags' => ['array'],
            'cards.*.tags.*' => ['string', 'max:100'],
            'cards.*.saas_eligible' => ['boolean'],
            'cards.*.related_catalog_key' => ['nullable', 'string', 'max:255'],
        ]);

        $suggestionPreset->update(['cards' => $this->normalizeCards($data['cards'])]);

        return back()->with('success', 'Suggestion preset updated.');
    }

    public function destroy(SuggestionPreset $suggestionPreset): RedirectResponse
    {
        $suggestionPreset->delete();

        return back()->with('success', 'Suggestion preset removed.');
    }

    /**
     * @return array{taxonomy_niche_id: int, phase: string, cards: array<int, array<string, mixed>>}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'taxonomy_niche_id' => ['required', 'integer', 'exists:taxonomy_niches,id'],
            'phase' => ['required', Rule::in(self::presetPhases())],
            'cards' => ['required', 'array', 'min:1'],
            'cards.*.title' => ['required', 'string', 'max:255'],
            'cards.*.summary' => ['required', 'string', 'max:1000'],
            'cards.*.features' => ['required', 'array', 'min:3'],
            'cards.*.features.*' => ['required', 'string', 'max:255'],
            'cards.*.rationale' => ['required', 'string', 'max:1000'],
            'cards.*.tags' => ['array'],
            'cards.*.tags.*' => ['string', 'max:100'],
            'cards.*.saas_eligible' => ['boolean'],
            'cards.*.related_catalog_key' => ['nullable', 'string', 'max:255'],
        ]);

        $data['cards'] = $this->normalizeCards($data['cards']);

        return $data;
    }

    /**
     * @param  array<int, array<string, mixed>>  $cards
     * @return array<int, array<string, mixed>>
     */
    private function normalizeCards(array $cards): array
    {
        return array_map(fn (array $card) => [
            'title' => $card['title'],
            'summary' => $card['summary'],
            'features' => array_values($card['features']),
            'rationale' => $card['rationale'],
            'tags' => array_values($card['tags'] ?? []),
            'saas_eligible' => (bool) ($card['saas_eligible'] ?? false),
            'related_catalog_key' => $card['related_catalog_key'] ?? null,
        ], $cards);
    }
}
