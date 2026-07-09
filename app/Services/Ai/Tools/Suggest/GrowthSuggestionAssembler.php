<?php

namespace App\Services\Ai\Tools\Suggest;

use App\Enums\DiscoveryPhase;
use App\Models\BusinessOwner;
use App\Models\DiscoverySession;
use App\Models\TaxonomyNiche;
use App\Services\Ai\ContextBlock;
use App\Services\Ai\ContextBlockType;
use App\Services\Ai\PromptTemplateRegistry;
use InvalidArgumentException;

/**
 * §7.3 block assembly for suggest.growth (Phase 5). Phase 5 fires one ✨ call
 * per enabled module (notifications, marketing, lead-gen, admin/ops), so this
 * assembler is module-scoped: {@see withModule()} pins which module's answers,
 * focus line, and task instruction are sent, keeping each call tight and on-topic.
 * No catalog link — related_catalog_key is always null. S5.6: each module call
 * also injects the brief-exemplar block and asks for the optional advisory
 * `brief`, scoped to that module's area.
 */
class GrowthSuggestionAssembler extends AbstractSuggestionAssembler implements ProvidesBriefExemplars
{
    use InjectsBriefExemplars;

    public function __construct(
        PromptTemplateRegistry $templates,
        private readonly BriefExemplarSelector $exemplarSelector,
    ) {
        parent::__construct($templates);
    }

    /**
     * The four Phase 5 modules (§3.6): the phase_5 answer field holding the
     * toggled sub-options, and the focus sentence that scopes the call.
     */
    private const MODULES = [
        'notifications' => [
            'field' => 'notifications_options',
            'focus' => 'reminders, confirmations and follow-up notifications (in-app, email, SMS, messaging-app channels)',
        ],
        'marketing' => [
            'field' => 'marketing_options',
            'focus' => 'marketing & retention (campaigns, newsletters, loyalty & discounts, gift vouchers, review collection, referrals, win-back)',
        ],
        'leadgen' => [
            'field' => 'leadgen_options',
            'focus' => 'lead generation (where new customers come from: local search visibility, communities, partnerships, directories) — preferences only, no live integrations',
        ],
        'admin_ops' => [
            'field' => 'admin_ops_options',
            'focus' => 'admin & operations tooling (scheduling, a customer database, digital invoices & quotes, document/knowledge assistant, customer chatbot, internal dashboards)',
        ],
    ];

    private string $module = 'notifications';

    /**
     * Pin the module this call is scoped to. Returns a fresh instance so the
     * container-shared assembler isn't mutated across concurrent requests.
     */
    public function withModule(string $module): self
    {
        if (! array_key_exists($module, self::MODULES)) {
            throw new InvalidArgumentException("Unknown growth module [{$module}].");
        }

        $clone = clone $this;
        $clone->module = $module;

        return $clone;
    }

    /** @return list<string> */
    public static function modules(): array
    {
        return array_keys(self::MODULES);
    }

    protected function tool(): string
    {
        return 'suggest.growth';
    }

    protected function phase(): DiscoveryPhase
    {
        return DiscoveryPhase::Phase5;
    }

    public function assemble(BusinessOwner $businessOwner, DiscoverySession $discoverySession): array
    {
        return [
            new ContextBlock(ContextBlockType::SystemPolicy, $this->systemPrompt()),
            new ContextBlock(ContextBlockType::AdminContext, trim((string) $businessOwner->admin_context)),
            new ContextBlock(ContextBlockType::Dcp, $this->dcpDigest($discoverySession)),
            new ContextBlock(ContextBlockType::TaxonomyCatalog, $this->nicheFlavor($discoverySession)),
            new ContextBlock(ContextBlockType::StructuredAnswers, $this->moduleAnswers($discoverySession)),
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

    /**
     * Only the pinned module's toggled sub-options (plus lead-gen's managed
     * interest) — keeps each per-module call scoped to its own area.
     */
    private function moduleAnswers(DiscoverySession $session): string
    {
        $field = self::MODULES[$this->module]['field'];

        $answers = $session->answers()
            ->where('phase', DiscoveryPhase::Phase5->value)
            ->get()
            ->keyBy('field_key');

        $lines = [];

        $selected = $answers->get($field)?->value;
        if (is_array($selected)) {
            $items = array_map(
                fn ($item) => str_replace('_', ' ', $item),
                array_filter($selected, fn ($item) => is_string($item) && $item !== ''),
            );
            if ($items !== []) {
                $lines[] = 'Sub-options they already toggled in this area: '.implode(', ', $items);
            }
        }

        if ($this->module === 'leadgen') {
            $interest = $answers->get('leadgen_managed_interest')?->value;
            if (is_string($interest) && $interest !== '') {
                $lines[] = 'Interest in a managed lead-gen service: '.str_replace('_', ' ', $interest);
            }
        }

        return implode("\n", $lines);
    }

    private function phaseNotes(DiscoverySession $session): string
    {
        $note = $session->answers()
            ->where('phase', DiscoveryPhase::Phase5->value)
            ->where('field_key', 'notes')
            ->value('value');

        return is_string($note) ? trim($note) : '';
    }

    private function taskInstruction(DiscoverySession $session): string
    {
        $language = $this->language($session);
        $focus = self::MODULES[$this->module]['focus'];
        $briefRules = $this->briefInstruction();

        return <<<TASK
Propose 3 to 5 distinct ideas for this business, strictly within this area: {$focus}. Write in the interview language "{$language}".

Return exactly this JSON shape:

{
  "brief": {
    "paragraph": "<3-5 sentences of plain-language direction about this area of their business>",
    "bullets": ["<up to 4 short, specific insight bullets>"]
  },
  "suggestions": [
    {
      "title": "<short idea name in the interview language>",
      "summary": "<one-line value in the interview language>",
      "features": ["<concrete capability or step 1>", "<step 2>", "<step 3>"],
      "rationale": "<1-2 sentences referencing the owner's niche, toggled options, or stated context>",
      "tags": ["<snake_case signal, e.g. retention, efficiency, visibility, automation>"],
      "saas_eligible": true | false,
      "related_catalog_key": null
    }
  ]
}

Rules:
- 3 to 5 cards; each MUST list at least 3 concrete capabilities or steps tied to the owner's needs.
- Stay strictly within the area above; do not stray into other growth modules.
- rationale must reference the owner's own niche, toggled sub-options, or expressed context whenever possible.
- related_catalog_key is always null for growth ideas.

{$briefRules}
- The brief stays strictly within the area above, like the cards.
TASK;
    }
}
