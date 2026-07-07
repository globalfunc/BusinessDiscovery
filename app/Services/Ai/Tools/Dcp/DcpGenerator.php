<?php

namespace App\Services\Ai\Tools\Dcp;

use App\Models\BusinessOwner;
use App\Models\DcpProfile;
use App\Models\DiscoverySession;
use App\Models\TaxonomyNiche;
use App\Services\Ai\AiCallRequest;
use App\Services\Ai\AiClient;
use Illuminate\Support\Facades\Log;

/**
 * The dcp.generate tool (§7.2): one call on Phase 0 continue producing the
 * §3.1 DCP. Never throws — any failure (API error, unparseable output,
 * schema rejection) stores an empty-payload DcpProfile so the flow always
 * advances (§3.9 "empty/failed AI states never block progress") and the UI
 * can offer a retry. Every attempt writes a new version.
 */
class DcpGenerator
{
    public function __construct(
        private readonly AiClient $aiClient,
        private readonly DcpInputAssembler $assembler,
        private readonly DcpSchemaValidator $validator,
    ) {}

    public function generate(BusinessOwner $businessOwner, DiscoverySession $discoverySession): DcpProfile
    {
        $request = AiCallRequest::fromContextBlocks(
            tool: 'dcp.generate',
            blocks: $this->assembler->assemble($businessOwner, $discoverySession),
            businessOwner: $businessOwner,
        );

        $result = $this->aiClient->call($request);

        $payload = $result->successful ? $this->parseAndValidate($result->text) : null;

        return DcpProfile::create([
            'discovery_session_id' => $discoverySession->id,
            'payload' => $payload !== null ? $this->sanitizeNicheId($payload) : [],
            'version' => ($discoverySession->dcpProfiles()->max('version') ?? 0) + 1,
            'model_meta' => [
                'model' => $result->aiCall->model,
                'prompt_version' => 1,
                'ai_call_id' => $result->aiCall->id,
                'status' => $payload !== null ? 'ok' : 'failed',
            ],
        ]);
    }

    /**
     * @return array<string, mixed>|null null when the output is unusable
     */
    private function parseAndValidate(?string $text): ?array
    {
        if ($text === null) {
            return null;
        }

        // Defensive: strip markdown fences despite the JSON-only instruction.
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $text) ?? $text;

        $data = json_decode($text, true);

        if (! is_array($data)) {
            Log::warning('dcp.generate returned non-JSON output.');

            return null;
        }

        $errors = $this->validator->validate($data);

        if ($errors !== []) {
            Log::warning('dcp.generate output failed schema validation.', ['errors' => $errors]);

            return null;
        }

        return $data;
    }

    /**
     * The Phase 1 pre-highlight badge trusts detected_niche.niche_id, so a
     * hallucinated ID is nulled here rather than checked at every read site.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizeNicheId(array $payload): array
    {
        $nicheId = $payload['detected_niche']['niche_id'] ?? null;

        if ($nicheId !== null && ! TaxonomyNiche::where('id', $nicheId)->where('hidden', false)->exists()) {
            $payload['detected_niche']['niche_id'] = null;
        }

        return $payload;
    }
}
