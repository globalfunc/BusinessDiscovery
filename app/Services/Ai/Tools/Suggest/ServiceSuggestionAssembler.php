<?php

namespace App\Services\Ai\Tools\Suggest;

use App\Enums\DiscoveryPhase;
use App\Models\BusinessOwner;
use App\Models\DiscoverySession;
use App\Models\SelectedService;
use App\Models\Service;
use App\Models\TaxonomyNiche;
use App\Services\Ai\ContextBlock;
use App\Services\Ai\ContextBlockType;

/**
 * §7.3 block assembly for suggest.services (Phase 2). Sends the gated catalog
 * excerpt (with keys, so a card's related_catalog_key can link to a real
 * service), what the BO has already selected (so we don't re-suggest it), and
 * the §7.4 card schema tuned for online-service capabilities.
 */
class ServiceSuggestionAssembler extends AbstractSuggestionAssembler
{
    protected function tool(): string
    {
        return 'suggest.services';
    }

    protected function phase(): DiscoveryPhase
    {
        return DiscoveryPhase::Phase2;
    }

    public function assemble(BusinessOwner $businessOwner, DiscoverySession $discoverySession): array
    {
        return [
            new ContextBlock(ContextBlockType::SystemPolicy, $this->systemPrompt()),
            new ContextBlock(ContextBlockType::AdminContext, trim((string) $businessOwner->admin_context)),
            new ContextBlock(ContextBlockType::Dcp, $this->dcpDigest($discoverySession)),
            new ContextBlock(ContextBlockType::TaxonomyCatalog, $this->catalogExcerpt($discoverySession)),
            new ContextBlock(ContextBlockType::StructuredAnswers, $this->alreadySelected($discoverySession)),
            new ContextBlock(ContextBlockType::PhaseNotes, $this->phaseNotes($discoverySession)),
            new ContextBlock(ContextBlockType::SuggestionPresets, $this->presetsInspiration($discoverySession)),
            new ContextBlock(ContextBlockType::TaskInstruction, $this->taskInstruction($discoverySession)),
        ];
    }

    private function catalogExcerpt(DiscoverySession $session): string
    {
        $nicheId = $this->nicheId($session);

        $niche = $nicheId !== null ? TaxonomyNiche::find($nicheId) : null;
        $header = $niche !== null
            ? "Catalog services available for this niche ({$niche->name['en']}). Use the key in related_catalog_key when a card closely matches one:"
            : 'Catalog services available (use the key in related_catalog_key when a card closely matches one):';

        $services = Service::query()
            ->where('hidden', false)
            ->when($nicheId !== null, fn ($query) => $query->whereHas('niches', fn ($q) => $q->where('taxonomy_niches.id', $nicheId)))
            ->orderBy('key')
            ->get();

        if ($services->isEmpty()) {
            // No niche filter matched — fall back to the full catalog so cards
            // can still link, rather than sending nothing.
            $services = Service::query()->where('hidden', false)->orderBy('key')->get();
        }

        $lines = $services->map(fn (Service $service) => sprintf(
            '- key=%s · %s — %s',
            $service->key,
            $service->name['en'] ?? '',
            $service->one_liner['en'] ?? '',
        ));

        return $lines->isEmpty() ? '' : $header."\n".$lines->implode("\n");
    }

    private function alreadySelected(DiscoverySession $session): string
    {
        $selected = $session->selectedServices()->with('service')->get();

        if ($selected->isEmpty()) {
            return '';
        }

        $lines = $selected->map(function (SelectedService $s) {
            $name = $s->custom ? ($s->name ?? 'Custom service') : ($s->service?->name['en'] ?? 'Catalog service');

            return "- {$name}";
        });

        return "Already selected by the owner (do NOT suggest these again):\n".$lines->implode("\n");
    }

    private function phaseNotes(DiscoverySession $session): string
    {
        $note = $session->answers()
            ->where('phase', DiscoveryPhase::Phase2->value)
            ->where('field_key', 'notes')
            ->value('value');

        return is_string($note) ? trim($note) : '';
    }

    private function taskInstruction(DiscoverySession $session): string
    {
        $language = $this->language($session);

        return <<<TASK
Suggest 3 to 5 online services that would genuinely help this business. Write in the interview language "{$language}".

Return exactly this JSON shape:

{
  "suggestions": [
    {
      "title": "<short service name in the interview language>",
      "summary": "<one-line value in the interview language>",
      "features": ["<feature 1>", "<feature 2>", "<feature 3>"],
      "rationale": "<1-2 sentences referencing the owner's own stated context>",
      "tags": ["<snake_case signal, e.g. retention, time_saving, visibility, new_customers>"],
      "saas_eligible": true | false,
      "related_catalog_key": "<matching catalog key from the list above, or null>"
    }
  ]
}

Rules:
- 3 to 5 cards; each card MUST have at least 3 concrete features tied to the owner's stated needs.
- rationale must reference the owner's own words or context whenever possible.
- related_catalog_key: use a catalog key from the list only when the card genuinely matches that service; otherwise null (it becomes a bespoke custom service).
- Do not repeat any already-selected service.
TASK;
    }
}
