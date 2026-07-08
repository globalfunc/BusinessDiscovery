<?php

namespace App\Services\Ai\Tools\Suggest;

use App\Models\BusinessOwner;
use App\Models\DiscoverySession;
use App\Models\Service;
use App\Services\Ai\AiCallRequest;
use App\Services\Ai\AiClient;
use App\Services\Ai\Contracts\InputAssembler;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates one `suggest.*` call (§7.2). Parameterized by the tool key,
 * its assembler, and a §7.4 validator so suggest.services and suggest.branding
 * (and S3.3's content/social + growth tools) share one code path. Never throws:
 * any failure returns an unsuccessful SuggestionResult so the controller can
 * fall back to suggestion_presets and the flow never blocks (§3.9, §7.7).
 */
class SuggestionGenerator
{
    public function __construct(private readonly AiClient $aiClient) {}

    public function generate(
        string $tool,
        InputAssembler $assembler,
        SuggestionSchemaValidator $validator,
        BusinessOwner $businessOwner,
        DiscoverySession $discoverySession,
    ): SuggestionResult {
        $request = AiCallRequest::fromContextBlocks(
            tool: $tool,
            blocks: $assembler->assemble($businessOwner, $discoverySession),
            businessOwner: $businessOwner,
        );

        $result = $this->aiClient->call($request);

        if (! $result->successful) {
            return new SuggestionResult(false, []);
        }

        $cards = $this->parseAndValidate($tool, $result->text, $validator);

        if ($cards === null) {
            return new SuggestionResult(false, []);
        }

        return new SuggestionResult(true, $this->sanitizeCatalogKeys($cards));
    }

    /**
     * @return array<int, array<string, mixed>>|null null when unusable
     */
    private function parseAndValidate(string $tool, ?string $text, SuggestionSchemaValidator $validator): ?array
    {
        if ($text === null) {
            return null;
        }

        // Defensive: strip markdown fences despite the JSON-only instruction.
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $text) ?? $text;

        $data = json_decode($text, true);

        if (! is_array($data)) {
            Log::warning("{$tool} returned non-JSON output.");

            return null;
        }

        $errors = $validator->validate($data);

        if ($errors !== []) {
            Log::warning("{$tool} output failed schema validation.", ['errors' => $errors]);

            return null;
        }

        return $data['suggestions'];
    }

    /**
     * A card's related_catalog_key is trusted at accept time to link to a real
     * catalog service, so a hallucinated key is nulled here — the card then
     * becomes a bespoke custom service on accept instead of a broken link.
     * (Mirrors DcpGenerator::sanitizeNicheId.)
     *
     * @param  array<int, array<string, mixed>>  $cards
     * @return array<int, array<string, mixed>>
     */
    private function sanitizeCatalogKeys(array $cards): array
    {
        $keys = array_values(array_filter(array_map(
            fn ($card) => is_string($card['related_catalog_key'] ?? null) ? $card['related_catalog_key'] : null,
            $cards,
        )));

        $valid = $keys === []
            ? []
            : Service::query()->whereIn('key', $keys)->where('hidden', false)->pluck('key')->all();

        return array_map(function (array $card) use ($valid) {
            $key = $card['related_catalog_key'] ?? null;
            $card['related_catalog_key'] = is_string($key) && in_array($key, $valid, true) ? $key : null;

            return $card;
        }, $cards);
    }
}
