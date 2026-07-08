<?php

namespace App\Services\Ai\Tools\Suggest;

use App\Enums\DiscoveryPhase;
use App\Models\BusinessOwner;
use App\Models\DiscoverySession;
use App\Models\TaxonomyNiche;
use App\Services\Ai\ContextBlock;
use App\Services\Ai\ContextBlockType;

/**
 * §7.3 block assembly for suggest.branding (Phase 3). No catalog link (brand
 * directions aren't catalog services), so related_catalog_key is always null.
 * Sends the niche flavor plus the BO's own style-chip / color / reference-note
 * answers so directions build on what they already leaned toward.
 */
class BrandingSuggestionAssembler extends AbstractSuggestionAssembler
{
    protected function tool(): string
    {
        return 'suggest.branding';
    }

    protected function phase(): DiscoveryPhase
    {
        return DiscoveryPhase::Phase3;
    }

    public function assemble(BusinessOwner $businessOwner, DiscoverySession $discoverySession): array
    {
        return [
            new ContextBlock(ContextBlockType::SystemPolicy, $this->systemPrompt()),
            new ContextBlock(ContextBlockType::AdminContext, trim((string) $businessOwner->admin_context)),
            new ContextBlock(ContextBlockType::Dcp, $this->dcpDigest($discoverySession)),
            new ContextBlock(ContextBlockType::TaxonomyCatalog, $this->nicheFlavor($discoverySession)),
            new ContextBlock(ContextBlockType::StructuredAnswers, $this->brandingAnswers($discoverySession)),
            new ContextBlock(ContextBlockType::PhaseNotes, $this->referenceNotes($discoverySession)),
            new ContextBlock(ContextBlockType::SuggestionPresets, $this->presetsInspiration($discoverySession)),
            new ContextBlock(ContextBlockType::TaskInstruction, $this->taskInstruction($discoverySession)),
        ];
    }

    private function nicheFlavor(DiscoverySession $session): string
    {
        $nicheId = $this->nicheId($session);
        $niche = $nicheId !== null ? TaxonomyNiche::find($nicheId) : null;

        return $niche !== null ? 'Business niche: '.($niche->name['en'] ?? '') : '';
    }

    private function brandingAnswers(DiscoverySession $session): string
    {
        $answers = $session->answers()
            ->where('phase', DiscoveryPhase::Phase3->value)
            ->get()
            ->keyBy('field_key');

        $lines = [];

        $chips = $answers->get('style_chips')?->value;
        if (is_array($chips) && $chips !== []) {
            $lines[] = 'Preferred style directions: '.implode(', ', array_filter($chips, 'is_string'));
        }

        $preset = $answers->get('color_preset')?->value;
        if (is_string($preset) && $preset !== '') {
            $lines[] = 'Chosen color palette preset: '.$preset;
        }

        $customHex = $answers->get('color_custom_hex')?->value;
        if (is_string($customHex) && $customHex !== '') {
            $lines[] = 'Chosen custom color: '.$customHex;
        }

        return implode("\n", $lines);
    }

    private function referenceNotes(DiscoverySession $session): string
    {
        $links = $session->answers()
            ->where('phase', DiscoveryPhase::Phase3->value)
            ->where('field_key', 'reference_links')
            ->value('value');

        if (! is_array($links) || $links === []) {
            return '';
        }

        $notes = [];
        foreach ($links as $link) {
            $note = is_array($link) && is_string($link['note'] ?? null) ? trim($link['note']) : '';
            if ($note !== '') {
                $notes[] = "- {$note}";
            }
        }

        return $notes === []
            ? ''
            : "What the owner likes about their reference sites (aesthetic inspiration only — never name the sites back):\n".implode("\n", $notes);
    }

    private function taskInstruction(DiscoverySession $session): string
    {
        $language = $this->language($session);

        return <<<TASK
Propose 3 to 5 distinct brand look-and-feel directions for this business. Write in the interview language "{$language}".

Return exactly this JSON shape:

{
  "suggestions": [
    {
      "title": "<evocative direction name in the interview language>",
      "summary": "<one-line description of the vibe in the interview language>",
      "features": ["<concrete brand element 1>", "<element 2>", "<element 3>"],
      "rationale": "<1-2 sentences referencing the owner's niche/personality/preferences>",
      "tags": ["<snake_case descriptor, e.g. warm, minimal, premium, playful>"],
      "saas_eligible": false,
      "related_catalog_key": null
    }
  ]
}

Rules:
- 3 to 5 cards; each MUST list at least 3 concrete visual/brand elements (palette, typography, imagery, layout motifs, signature UI moments).
- Make the directions genuinely different from one another.
- rationale must reference the owner's own niche, strengths, or expressed preferences whenever possible.
- saas_eligible is always false and related_catalog_key is always null for brand directions.
TASK;
    }
}
