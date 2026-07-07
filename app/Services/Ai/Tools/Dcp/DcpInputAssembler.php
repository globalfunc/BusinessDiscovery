<?php

namespace App\Services\Ai\Tools\Dcp;

use App\Enums\DiscoveryPhase;
use App\Models\BusinessOwner;
use App\Models\DiscoverySession;
use App\Models\TaxonomyCategory;
use App\Models\TaxonomyNiche;
use App\Services\Ai\ContextBlock;
use App\Services\Ai\ContextBlockType;
use App\Services\Ai\Contracts\InputAssembler;
use App\Services\Ai\PromptTemplateRegistry;

/**
 * Assembles the §7.3 blocks for dcp.generate: system policy, admin business
 * context, the taxonomy (with IDs, so detected_niche can be tied back to a
 * real niche row for the Phase 1 pre-highlight), the BO's Phase 0 intake
 * text, and the task instruction with the §3.1 DCP JSON shape.
 */
class DcpInputAssembler implements InputAssembler
{
    /**
     * Phase 0 free-text answer keys, in the §3.1 guided-interview order.
     * Free prompt and guided answers coexist — the BO may have typed in both
     * modes; everything present is sent.
     */
    private const INTAKE_FIELDS = [
        'free_prompt' => 'In their own words',
        'guided_offer' => 'What the business offers, and to whom',
        'guided_channels' => 'How customers currently find and book/buy',
        'guided_frustrations' => 'Day-to-day frustrations (pain points)',
        'guided_success' => 'What success online looks like / edge over competitors',
        'website_url' => 'Existing website',
        'social_links' => 'Social profiles',
    ];

    public function __construct(private readonly PromptTemplateRegistry $templates) {}

    public function assemble(BusinessOwner $businessOwner, DiscoverySession $discoverySession): array
    {
        return [
            new ContextBlock(ContextBlockType::SystemPolicy, $this->templates->get('dcp.generate')->systemPrompt()),
            new ContextBlock(ContextBlockType::AdminContext, trim((string) $businessOwner->admin_context)),
            new ContextBlock(ContextBlockType::TaxonomyCatalog, $this->taxonomy()),
            new ContextBlock(ContextBlockType::PhaseNotes, $this->intakeText($discoverySession)),
            new ContextBlock(ContextBlockType::TaskInstruction, $this->taskInstruction($discoverySession)),
        ];
    }

    private function taxonomy(): string
    {
        $lines = TaxonomyCategory::query()
            ->where('hidden', false)
            ->orderBy('sort')
            ->with(['niches' => fn ($query) => $query->where('hidden', false)->orderBy('sort')])
            ->get()
            ->flatMap(fn (TaxonomyCategory $category) => $category->niches->map(
                fn (TaxonomyNiche $niche) => sprintf(
                    '- niche_id=%d · %s > %s',
                    $niche->id,
                    $category->name['en'] ?? '',
                    $niche->name['en'] ?? '',
                ),
            ));

        if ($lines->isEmpty()) {
            return '';
        }

        return "Available business niches (pick the closest match for detected_niche):\n".$lines->implode("\n");
    }

    private function intakeText(DiscoverySession $discoverySession): string
    {
        $answers = $discoverySession->answers()
            ->where('phase', DiscoveryPhase::Phase0->value)
            ->get()
            ->keyBy('field_key');

        $sections = [];

        foreach (self::INTAKE_FIELDS as $key => $heading) {
            $value = $answers->get($key)?->value;

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $sections[] = "### {$heading}\n".trim($value);
        }

        return implode("\n\n", $sections);
    }

    private function taskInstruction(DiscoverySession $discoverySession): string
    {
        $language = $discoverySession->language?->value ?? 'bg';

        return <<<TASK
Produce the Discovery Context Profile for this business owner. The interview language is "{$language}".

Return exactly this JSON shape:

{
  "detected_niche": {"category": "<short English slug>", "niche": "<short English slug>", "niche_id": <matching niche_id from the taxonomy list, or null if nothing fits>, "confidence": <0.0-1.0>},
  "pain_points": [{"id": "<snake_case slug>", "label": "<short label in the interview language>", "evidence": "<quote or close paraphrase of the owner's words>"}],
  "goals": [{"id": "<snake_case slug>", "label": "<short label in the interview language>"}],
  "strengths": ["<strength in the interview language>"],
  "digital_maturity": "low" | "medium" | "high",
  "priority_signals": ["<snake_case signal, e.g. retention, time_saving, visibility, new_customers>"],
  "summary": "<2-3 sentence operator-facing synopsis in English>",
  "tone_hints": {"language": "{$language}", "formality": "casual" | "neutral" | "formal"}
}

Notes:
- pain_points, goals, strengths, priority_signals: include only what the owner's text supports; empty arrays are fine.
- confidence reflects how sure you are about the niche given their description; use null niche_id and low confidence rather than forcing a bad match.
- digital_maturity: judge from what they already have (website, social presence, booking/ordering tools they mention).
TASK;
    }
}
