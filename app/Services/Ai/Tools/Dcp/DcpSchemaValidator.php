<?php

namespace App\Services\Ai\Tools\Dcp;

use App\Services\Ai\Contracts\OutputSchemaValidator;

/**
 * Validates dcp.generate output against the §3.1 DCP shape. Every top-level
 * key must be present and well-typed — a payload that fails here is treated
 * exactly like a failed AI call (empty DCP stored, retry offered).
 */
class DcpSchemaValidator implements OutputSchemaValidator
{
    private const DIGITAL_MATURITY = ['low', 'medium', 'high'];

    public function validate(array $data): array
    {
        $errors = [];

        foreach (['detected_niche', 'pain_points', 'goals', 'strengths', 'digital_maturity', 'priority_signals', 'tone_hints', 'summary'] as $key) {
            if (! array_key_exists($key, $data)) {
                $errors[] = "Missing required key [{$key}].";
            }
        }

        if ($errors !== []) {
            return $errors;
        }

        $niche = $data['detected_niche'];
        if (! is_array($niche)) {
            $errors[] = 'detected_niche must be an object.';
        } else {
            if (! is_string($niche['category'] ?? null) || ! is_string($niche['niche'] ?? null)) {
                $errors[] = 'detected_niche.category and detected_niche.niche must be strings.';
            }
            $confidence = $niche['confidence'] ?? null;
            if (! is_int($confidence) && ! is_float($confidence) || $confidence < 0 || $confidence > 1) {
                $errors[] = 'detected_niche.confidence must be a number between 0 and 1.';
            }
            if (array_key_exists('niche_id', $niche) && $niche['niche_id'] !== null && ! is_int($niche['niche_id'])) {
                $errors[] = 'detected_niche.niche_id must be an integer or null.';
            }
        }

        $errors = array_merge(
            $errors,
            $this->validateLabelledList($data['pain_points'], 'pain_points', requireEvidence: false),
            $this->validateLabelledList($data['goals'], 'goals', requireEvidence: false),
        );

        if (! is_array($data['strengths']) || array_filter($data['strengths'], fn ($s) => ! is_string($s)) !== []) {
            $errors[] = 'strengths must be an array of strings.';
        }

        if (! in_array($data['digital_maturity'], self::DIGITAL_MATURITY, true)) {
            $errors[] = 'digital_maturity must be one of low|medium|high.';
        }

        if (! is_array($data['priority_signals']) || array_filter($data['priority_signals'], fn ($s) => ! is_string($s)) !== []) {
            $errors[] = 'priority_signals must be an array of strings.';
        }

        $tone = $data['tone_hints'];
        if (! is_array($tone) || ! is_string($tone['language'] ?? null) || ! is_string($tone['formality'] ?? null)) {
            $errors[] = 'tone_hints must be an object with string language and formality.';
        }

        if (! is_string($data['summary']) || trim($data['summary']) === '') {
            $errors[] = 'summary must be a non-empty string.';
        }

        return $errors;
    }

    /**
     * @return string[]
     */
    private function validateLabelledList(mixed $items, string $key, bool $requireEvidence): array
    {
        if (! is_array($items)) {
            return ["{$key} must be an array."];
        }

        foreach ($items as $index => $item) {
            if (! is_array($item) || ! is_string($item['id'] ?? null) || ! is_string($item['label'] ?? null)) {
                return ["{$key}[{$index}] must be an object with string id and label."];
            }

            if ($requireEvidence && ! is_string($item['evidence'] ?? null)) {
                return ["{$key}[{$index}] must include string evidence."];
            }
        }

        return [];
    }
}
