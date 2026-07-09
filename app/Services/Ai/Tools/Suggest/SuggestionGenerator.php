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
    public function __construct(
        private readonly AiClient $aiClient,
        private readonly AdvisoryBriefService $advisoryBriefs,
    ) {}

    /**
     * @param  BriefContext|null  $briefContext  present only for the S5.6
     *                                           brief-capable tools (content/social, growth) — the payload's
     *                                           optional `brief` is then gated, persisted, and returned on the
     *                                           result; every other tool ignores a stray brief field entirely.
     */
    public function generate(
        string $tool,
        InputAssembler $assembler,
        SuggestionSchemaValidator $validator,
        BusinessOwner $businessOwner,
        DiscoverySession $discoverySession,
        ?BriefContext $briefContext = null,
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

        $data = $this->parse($tool, $result->text);

        if ($data === null || ! $this->validCards($tool, $data, $validator)) {
            return new SuggestionResult(false, []);
        }

        // The brief is processed only once the cards themselves are usable —
        // a preset-fallback response never carries a brief, so a note that
        // contradicts static cards can't appear (S5.6).
        $brief = null;

        if ($briefContext !== null) {
            $brief = $this->advisoryBriefs->process(
                rawBrief: $data['brief'] ?? null,
                businessOwner: $businessOwner,
                session: $discoverySession,
                context: $briefContext,
                tool: $tool,
                model: $result->aiCall->model,
                exemplars: $assembler instanceof ProvidesBriefExemplars
                    ? AdvisoryBriefService::exemplarSet($assembler->selectedExemplars())
                    : [],
            );
        }

        return new SuggestionResult(true, $this->sanitizeCatalogKeys($data['suggestions']), $brief);
    }

    /**
     * @return array<string, mixed>|null the decoded payload, null when unusable
     */
    private function parse(string $tool, ?string $text): ?array
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

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validCards(string $tool, array $data, SuggestionSchemaValidator $validator): bool
    {
        $errors = $validator->validate($data);

        if ($errors !== []) {
            Log::warning("{$tool} output failed schema validation.", ['errors' => $errors]);

            return false;
        }

        return true;
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
