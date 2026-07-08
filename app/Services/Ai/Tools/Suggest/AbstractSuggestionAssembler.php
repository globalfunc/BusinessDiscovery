<?php

namespace App\Services\Ai\Tools\Suggest;

use App\Enums\DiscoveryPhase;
use App\Models\DiscoverySession;
use App\Models\SuggestionPreset;
use App\Services\Ai\Contracts\InputAssembler;
use App\Services\Ai\PromptTemplateRegistry;

/**
 * Shared §7.3 context-block builders for the suggest.* assemblers: the DCP
 * digest, the niche resolution, the suggestion-preset "inspiration" block,
 * and the interview language. Each concrete assembler (services, branding,
 * and S3.3's content/social + growth) fills in only its phase-specific
 * catalog excerpt, structured answers, phase notes, and task instruction.
 */
abstract class AbstractSuggestionAssembler implements InputAssembler
{
    public function __construct(protected readonly PromptTemplateRegistry $templates) {}

    /** The §7.2 tool key this assembler builds for, e.g. "suggest.services". */
    abstract protected function tool(): string;

    /** The phase whose suggestion_presets are used as inspiration + fallback. */
    abstract protected function phase(): DiscoveryPhase;

    protected function systemPrompt(): string
    {
        return $this->templates->get($this->tool())->systemPrompt();
    }

    protected function language(DiscoverySession $session): string
    {
        return $session->language?->value ?? 'bg';
    }

    /**
     * The confirmed Phase 1 niche id (autosaved as a structured answer), or
     * null when the BO picked "Other / not listed" or hasn't chosen yet.
     */
    protected function nicheId(DiscoverySession $session): ?int
    {
        $value = $session->answers()
            ->where('phase', DiscoveryPhase::Phase1->value)
            ->where('field_key', 'niche_id')
            ->value('value');

        return is_int($value) ? $value : null;
    }

    /**
     * Compact operator-facing digest of the latest usable DCP (§3.1) for the
     * §7.3 DCP block. Returns '' when no DCP exists or it's empty, so the
     * suggestion tool falls back to grounding on raw answers alone.
     */
    protected function dcpDigest(DiscoverySession $session): string
    {
        $profile = $session->latestDcpProfile;

        if ($profile === null || $profile->isEmpty()) {
            return '';
        }

        $payload = $profile->payload;
        $lines = [];

        if (is_string($payload['summary'] ?? null)) {
            $lines[] = $payload['summary'];
        }

        $lines[] = 'Digital maturity: '.($payload['digital_maturity'] ?? 'unknown');

        $pains = $this->labels($payload['pain_points'] ?? []);
        if ($pains !== []) {
            $lines[] = 'Pain points: '.implode('; ', $pains);
        }

        $goals = $this->labels($payload['goals'] ?? []);
        if ($goals !== []) {
            $lines[] = 'Goals: '.implode('; ', $goals);
        }

        $strengths = array_filter((array) ($payload['strengths'] ?? []), 'is_string');
        if ($strengths !== []) {
            $lines[] = 'Strengths: '.implode('; ', $strengths);
        }

        $signals = array_filter((array) ($payload['priority_signals'] ?? []), 'is_string');
        if ($signals !== []) {
            $lines[] = 'Priority signals: '.implode(', ', $signals);
        }

        return implode("\n", $lines);
    }

    /**
     * The niche's static preset cards for this phase, rendered as inspiration
     * (§7.3 block 7). The same rows back the AI-unavailable fallback (§7.7),
     * so keeping them in the prompt nudges the model toward the house style.
     */
    protected function presetsInspiration(DiscoverySession $session): string
    {
        $cards = $this->presetCards($session);

        if ($cards === []) {
            return '';
        }

        $blocks = [];
        foreach ($cards as $card) {
            $title = is_string($card['title'] ?? null) ? $card['title'] : '';
            $summary = is_string($card['summary'] ?? null) ? $card['summary'] : '';
            $blocks[] = trim("- {$title}: {$summary}");
        }

        return "These house presets show the style and depth we expect (blend/improve on them, don't copy verbatim):\n".implode("\n", $blocks);
    }

    /**
     * Raw preset cards for this niche + phase — reused by the controller as
     * the §7.7 fallback payload when a call fails validation.
     *
     * @return array<int, array<string, mixed>>
     */
    public function presetCards(DiscoverySession $session): array
    {
        $nicheId = $this->nicheId($session);

        if ($nicheId === null) {
            return [];
        }

        $preset = SuggestionPreset::query()
            ->where('taxonomy_niche_id', $nicheId)
            ->where('phase', $this->phase()->value)
            ->first();

        return is_array($preset?->cards) ? $preset->cards : [];
    }

    /**
     * @param  mixed  $items  DCP list of {id,label} objects
     * @return string[]
     */
    private function labels(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($item) => is_array($item) && is_string($item['label'] ?? null) ? $item['label'] : null,
            $items,
        )));
    }
}
