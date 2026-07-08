<?php

namespace App\Services\Ai\Tools\Suggest;

use App\Services\Ai\Contracts\OutputSchemaValidator;

/**
 * Validates every `suggest.*` tool's output against the §7.4 Suggestion Card
 * contract: a top-level `suggestions` array of 3–5 cards, each with a title,
 * summary, ≥3 features, a rationale, tags, and a saas_eligible flag. A payload
 * that fails here is treated exactly like a failed AI call — the endpoint
 * falls back to suggestion_presets and never blocks (§7.7).
 *
 * `related_catalog_key` is required-present only for suggest.services (it lets
 * an accepted card link to a real catalog service); branding/content cards
 * carry no catalog link, so the flag is off for them.
 */
class SuggestionSchemaValidator implements OutputSchemaValidator
{
    private const MIN_CARDS = 3;

    private const MAX_CARDS = 5;

    private const MIN_FEATURES = 3;

    public function __construct(private readonly bool $requireCatalogKey = false) {}

    public function validate(array $data): array
    {
        if (! array_key_exists('suggestions', $data) || ! is_array($data['suggestions'])) {
            return ['Missing required array key [suggestions].'];
        }

        $cards = $data['suggestions'];
        $count = count($cards);

        if ($count < self::MIN_CARDS || $count > self::MAX_CARDS) {
            return ["suggestions must contain between 3 and 5 cards, got {$count}."];
        }

        $errors = [];

        foreach (array_values($cards) as $index => $card) {
            $errors = array_merge($errors, $this->validateCard($card, $index));
        }

        return $errors;
    }

    /**
     * @return string[]
     */
    private function validateCard(mixed $card, int $index): array
    {
        if (! is_array($card)) {
            return ["suggestions[{$index}] must be an object."];
        }

        $errors = [];

        foreach (['title', 'summary', 'rationale'] as $key) {
            if (! is_string($card[$key] ?? null) || trim($card[$key]) === '') {
                $errors[] = "suggestions[{$index}].{$key} must be a non-empty string.";
            }
        }

        $features = $card['features'] ?? null;
        if (! is_array($features) || array_filter($features, fn ($f) => ! is_string($f) || trim($f) === '') !== []) {
            $errors[] = "suggestions[{$index}].features must be an array of non-empty strings.";
        } elseif (count($features) < self::MIN_FEATURES) {
            $errors[] = "suggestions[{$index}].features must include at least 3 items.";
        }

        $tags = $card['tags'] ?? null;
        if (! is_array($tags) || array_filter($tags, fn ($t) => ! is_string($t)) !== []) {
            $errors[] = "suggestions[{$index}].tags must be an array of strings.";
        }

        if (! is_bool($card['saas_eligible'] ?? null)) {
            $errors[] = "suggestions[{$index}].saas_eligible must be a boolean.";
        }

        if ($this->requireCatalogKey) {
            if (! array_key_exists('related_catalog_key', $card)) {
                $errors[] = "suggestions[{$index}].related_catalog_key must be present (string or null).";
            } elseif ($card['related_catalog_key'] !== null && ! is_string($card['related_catalog_key'])) {
                $errors[] = "suggestions[{$index}].related_catalog_key must be a string or null.";
            }
        }

        return $errors;
    }
}
