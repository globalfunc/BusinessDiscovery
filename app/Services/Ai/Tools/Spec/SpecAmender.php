<?php

namespace App\Services\Ai\Tools\Spec;

use App\Models\BusinessOwner;
use App\Models\DiscoverySession;
use App\Models\SpecAmendment;
use App\Models\SpecDocument;
use App\Services\Ai\AiCallRequest;
use App\Services\Ai\AiClient;
use App\Services\Ai\PromptTemplateRegistry;
use Illuminate\Support\Facades\Log;

/**
 * The spec.amend tool (§3.8 amend loop): the owner's instruction against the
 * latest spec version yields a revised document (section-level regeneration —
 * unaffected sections are copied verbatim) plus a change summary, stored as
 * version+1 with a spec_amendments audit row. Never throws: any failure
 * (API error, unparseable JSON, degenerate markdown) returns null and stores
 * nothing — the current version stays live and the BO can retry or submit
 * as-is, so the flow never blocks (§3.9/§7.7).
 *
 * Builds its AiCallRequest directly rather than via §7.3 context blocks —
 * the input here is a document + an instruction, not interview state. Vendor
 * neutrality still applies automatically inside AiClient::call() (§7.6).
 */
class SpecAmender
{
    public function __construct(
        private readonly AiClient $aiClient,
        private readonly PromptTemplateRegistry $templates,
    ) {}

    public function amend(BusinessOwner $businessOwner, DiscoverySession $discoverySession, string $instruction): ?SpecDocument
    {
        $current = $discoverySession->latestSpecDocument;

        if ($current === null) {
            return null;
        }

        $request = new AiCallRequest(
            tool: 'spec.amend',
            messages: [['role' => 'user', 'content' => $this->userTurn($current, $instruction)]],
            system: $this->templates->get('spec.amend')->systemPrompt(),
            businessOwner: $businessOwner,
        );

        $result = $this->aiClient->call($request);

        if (! $result->successful) {
            return null;
        }

        $revision = $this->parse($result->text);

        if ($revision === null) {
            return null;
        }

        $document = SpecDocument::create([
            'discovery_session_id' => $discoverySession->id,
            'version' => ($discoverySession->specDocuments()->max('version') ?? 0) + 1,
            'markdown' => $revision['markdown'],
            'generated_by' => 'ai',
            'change_summary' => $revision['change_summary'],
            'model_meta' => [
                'model' => $result->aiCall->model,
                'prompt_version' => 1,
                'ai_call_id' => $result->aiCall->id,
                'language' => $discoverySession->language?->value,
                'amended_sections' => $revision['amended_sections'],
                'status' => 'ok',
            ],
        ]);

        SpecAmendment::create([
            'spec_document_id' => $current->id,
            'instruction' => $instruction,
            'resulting_version' => $document->version,
        ]);

        return $document;
    }

    private function userTurn(SpecDocument $current, string $instruction): string
    {
        return <<<TURN
## Current Business Specification (version {$current->version})

{$current->markdown}

## Owner's amendment instruction

{$instruction}

## Task

Apply the instruction to the specification. Revise only the sections it affects; copy every other section verbatim. Return exactly this JSON shape:

{
  "markdown": "<the full revised specification markdown, all 10 sections>",
  "change_summary": "<1-3 sentences, in the document's language, telling the owner what changed>",
  "amended_sections": [<the numbers (1-10) of the sections you changed>]
}
TURN;
    }

    /**
     * @return array{markdown: string, change_summary: string, amended_sections: array<int, int>}|null
     */
    private function parse(?string $text): ?array
    {
        if ($text === null) {
            return null;
        }

        // Defensive: strip markdown fences despite the JSON-only instruction.
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $text) ?? $text;

        $data = json_decode($text, true);

        if (! is_array($data)) {
            Log::warning('spec.amend returned non-JSON output.');

            return null;
        }

        $markdown = SpecMarkdown::usable(is_string($data['markdown'] ?? null) ? $data['markdown'] : null);
        $summary = is_string($data['change_summary'] ?? null) ? trim($data['change_summary']) : '';

        if ($markdown === null || $summary === '') {
            Log::warning('spec.amend output failed validation.', [
                'has_markdown' => $markdown !== null,
                'has_summary' => $summary !== '',
            ]);

            return null;
        }

        return [
            'markdown' => $markdown,
            'change_summary' => $summary,
            'amended_sections' => array_values(array_filter((array) ($data['amended_sections'] ?? []), 'is_int')),
        ];
    }
}
