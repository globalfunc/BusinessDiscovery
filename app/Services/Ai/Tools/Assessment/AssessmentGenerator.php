<?php

namespace App\Services\Ai\Tools\Assessment;

use App\Models\AssessmentDocument;
use App\Models\BusinessOwner;
use App\Services\Ai\AiCallRequest;
use App\Services\Ai\AiClient;

/**
 * The assessment.generate tool (§6.4/§7.2): admin click → one new
 * assessment_documents version (generated_by=ai). Unlike spec.compile there
 * is no deterministic fallback — this is an admin convenience with a human
 * at the keyboard, so a failed/gated call returns null and the UI says
 * "try again" (or the admin writes it by hand and saves a manual version).
 */
class AssessmentGenerator
{
    public function __construct(
        private readonly AiClient $aiClient,
        private readonly AssessmentAssembler $assembler,
    ) {}

    public function generate(BusinessOwner $businessOwner, ?string $adminNotes = null): ?AssessmentDocument
    {
        $result = $this->aiClient->call(AiCallRequest::fromContextBlocks(
            tool: 'assessment.generate',
            blocks: $this->assembler->assemble($businessOwner, $adminNotes),
            businessOwner: $businessOwner,
        ));

        if (! $result->successful || trim((string) $result->text) === '') {
            return null;
        }

        return AssessmentDocument::create([
            'business_owner_id' => $businessOwner->id,
            'version' => ($businessOwner->assessmentDocuments()->max('version') ?? 0) + 1,
            'markdown' => trim($result->text),
            'generated_by' => 'ai',
            'model_meta' => [
                'model' => $result->aiCall->model,
                'prompt_version' => 1,
                'ai_call_id' => $result->aiCall->id,
            ],
        ]);
    }
}
