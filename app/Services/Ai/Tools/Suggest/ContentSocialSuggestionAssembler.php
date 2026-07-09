<?php

namespace App\Services\Ai\Tools\Suggest;

use App\Enums\DiscoveryPhase;
use App\Models\BusinessOwner;
use App\Models\DiscoverySession;
use App\Models\TaxonomyNiche;
use App\Services\Ai\ContextBlock;
use App\Services\Ai\ContextBlockType;
use App\Services\Ai\PromptTemplateRegistry;

/**
 * §7.3 block assembly for suggest.content_social (Phase 4). No catalog link
 * (content plays aren't catalog services), so related_catalog_key is always
 * null. Sends the niche flavor plus the BO's own content-need / platform /
 * cadence / interest answers so cards build on what they already told us.
 * S5.6: also injects the brief-exemplar block and asks for the optional
 * advisory `brief` in the same call — the content brief covers posts and
 * presence only, never short-video scripts.
 */
class ContentSocialSuggestionAssembler extends AbstractSuggestionAssembler implements ProvidesBriefExemplars
{
    use InjectsBriefExemplars;

    public function __construct(
        PromptTemplateRegistry $templates,
        private readonly BriefExemplarSelector $exemplarSelector,
    ) {
        parent::__construct($templates);
    }

    protected function tool(): string
    {
        return 'suggest.content_social';
    }

    protected function phase(): DiscoveryPhase
    {
        return DiscoveryPhase::Phase4;
    }

    public function assemble(BusinessOwner $businessOwner, DiscoverySession $discoverySession): array
    {
        return [
            new ContextBlock(ContextBlockType::SystemPolicy, $this->systemPrompt()),
            new ContextBlock(ContextBlockType::AdminContext, trim((string) $businessOwner->admin_context)),
            new ContextBlock(ContextBlockType::Dcp, $this->dcpDigest($discoverySession)),
            new ContextBlock(ContextBlockType::TaxonomyCatalog, $this->nicheFlavor($discoverySession)),
            new ContextBlock(ContextBlockType::StructuredAnswers, $this->contentAnswers($discoverySession)),
            new ContextBlock(ContextBlockType::PhaseNotes, $this->phaseNotes($discoverySession)),
            new ContextBlock(ContextBlockType::SuggestionPresets, $this->presetsInspiration($discoverySession)),
            new ContextBlock(ContextBlockType::BriefExemplars, $this->briefExemplarsBlock($discoverySession)),
            new ContextBlock(ContextBlockType::TaskInstruction, $this->taskInstruction($discoverySession)),
        ];
    }

    private function nicheFlavor(DiscoverySession $session): string
    {
        $nicheId = $this->nicheId($session);
        $niche = $nicheId !== null ? TaxonomyNiche::find($nicheId) : null;

        return $niche !== null ? 'Business niche: '.($niche->name['en'] ?? '') : '';
    }

    private function contentAnswers(DiscoverySession $session): string
    {
        $answers = $session->answers()
            ->where('phase', DiscoveryPhase::Phase4->value)
            ->get()
            ->keyBy('field_key');

        $lines = [];

        $needs = $this->humanizeList($answers->get('content_needs')?->value);
        if ($needs !== '') {
            $lines[] = 'Content needs: '.$needs;
        }

        $platforms = array_filter([
            $this->humanizeList($answers->get('social_platforms')?->value),
            $this->humanizeList($answers->get('other_platforms')?->value),
        ]);
        if ($platforms !== []) {
            $lines[] = 'Platforms they use or want: '.implode(', ', $platforms);
        }

        $cadence = $answers->get('posting_cadence')?->value;
        if (is_string($cadence) && $cadence !== '') {
            $lines[] = 'Posting appetite: '.str_replace('_', ' ', $cadence);
        }

        $interest = $answers->get('content_assist_interest')?->value;
        if (is_string($interest) && $interest !== '') {
            $lines[] = 'Interest in content-generation help: '.str_replace('_', ' ', $interest);
        }

        return implode("\n", $lines);
    }

    private function phaseNotes(DiscoverySession $session): string
    {
        $note = $session->answers()
            ->where('phase', DiscoveryPhase::Phase4->value)
            ->where('field_key', 'notes')
            ->value('value');

        return is_string($note) ? trim($note) : '';
    }

    /**
     * @param  mixed  $value  a list of snake_case option keys
     */
    private function humanizeList(mixed $value): string
    {
        if (! is_array($value)) {
            return '';
        }

        $items = array_map(
            fn ($item) => str_replace('_', ' ', $item),
            array_filter($value, fn ($item) => is_string($item) && $item !== ''),
        );

        return implode(', ', $items);
    }

    private function taskInstruction(DiscoverySession $session): string
    {
        $language = $this->language($session);
        $briefRules = $this->briefInstruction();

        return <<<TASK
Propose 3 to 5 distinct content & social-presence plays for this business. Write in the interview language "{$language}".

Return exactly this JSON shape:

{
  "brief": {
    "paragraph": "<3-5 sentences of plain-language direction about their content & online presence>",
    "bullets": ["<up to 4 short, specific insight bullets>"]
  },
  "suggestions": [
    {
      "title": "<short plan name in the interview language>",
      "summary": "<one-line value in the interview language>",
      "features": ["<concrete deliverable 1>", "<deliverable 2>", "<deliverable 3>"],
      "rationale": "<1-2 sentences referencing the owner's niche, platforms, or stated needs>",
      "tags": ["<snake_case signal, e.g. awareness, engagement, retention, local_reach>"],
      "saas_eligible": false,
      "related_catalog_key": null
    }
  ]
}

Rules:
- 3 to 5 cards; each MUST list at least 3 concrete deliverables (post counts, video scripts, templates, cadences, calendars).
- Make the plays genuinely different from one another.
- rationale must reference the owner's own niche, platforms, or expressed content needs whenever possible.
- saas_eligible is always false and related_catalog_key is always null for content/social plays.

{$briefRules}
- This content brief covers posts and online presence only — do not discuss short-video scripts in the brief (cards may still include them).
TASK;
    }
}
