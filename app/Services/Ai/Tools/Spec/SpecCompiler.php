<?php

namespace App\Services\Ai\Tools\Spec;

use App\Models\BusinessOwner;
use App\Models\DiscoverySession;
use App\Models\SpecDocument;
use App\Services\Ai\AiCallRequest;
use App\Services\Ai\AiClient;
use App\Support\DiscoverySpecRenderer;

/**
 * The spec.compile tool (§7.2/§7.5): Review-screen open (when no version
 * exists yet) or an explicit regenerate produces one new spec_documents
 * version. Never throws and never leaves the BO spec-less — when the AI call
 * fails or returns unusable text, the same request stores the deterministic
 * DiscoverySpecRenderer output instead (generated_by=fallback), per §7.7.
 * compileFallback() is that path directly, with zero AI involvement — S3.6's
 * pre-flight budget check should call it instead of compile() on exhaustion.
 *
 * Vendor neutrality needs no handling here: the §7.6 scan/regenerate/redact
 * runs inside AiClient::call() for every tool.
 */
class SpecCompiler
{
    public function __construct(
        private readonly AiClient $aiClient,
        private readonly SpecCompileAssembler $assembler,
    ) {}

    public function compile(BusinessOwner $businessOwner, DiscoverySession $discoverySession): SpecDocument
    {
        $request = AiCallRequest::fromContextBlocks(
            tool: 'spec.compile',
            blocks: $this->assembler->assemble($businessOwner, $discoverySession),
            businessOwner: $businessOwner,
        );

        $result = $this->aiClient->call($request);

        $markdown = $result->successful ? SpecMarkdown::usable($result->text) : null;

        if ($markdown === null) {
            return $this->storeFallback($businessOwner, $discoverySession, [
                'status' => 'ai_failed',
                'ai_call_id' => $result->aiCall->id,
            ]);
        }

        return $this->store($discoverySession, $markdown, 'ai', [
            'model' => $result->aiCall->model,
            'prompt_version' => 1,
            'ai_call_id' => $result->aiCall->id,
            'language' => $discoverySession->language?->value,
            'status' => 'ok',
        ]);
    }

    /**
     * The §7.7 deterministic renderer path — no AI call is made at all.
     */
    public function compileFallback(BusinessOwner $businessOwner, DiscoverySession $discoverySession): SpecDocument
    {
        return $this->storeFallback($businessOwner, $discoverySession, ['status' => 'fallback']);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function storeFallback(BusinessOwner $businessOwner, DiscoverySession $discoverySession, array $meta): SpecDocument
    {
        $meta['language'] = $discoverySession->language?->value;

        return $this->store(
            $discoverySession,
            DiscoverySpecRenderer::render($discoverySession, $businessOwner),
            'fallback',
            $meta,
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function store(DiscoverySession $discoverySession, string $markdown, string $generatedBy, array $meta): SpecDocument
    {
        return SpecDocument::create([
            'discovery_session_id' => $discoverySession->id,
            'version' => ($discoverySession->specDocuments()->max('version') ?? 0) + 1,
            'markdown' => $markdown,
            'generated_by' => $generatedBy,
            'model_meta' => $meta,
        ]);
    }
}
