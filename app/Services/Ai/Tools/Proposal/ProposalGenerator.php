<?php

namespace App\Services\Ai\Tools\Proposal;

use App\Models\BusinessOwner;
use App\Models\ProposalDocument;
use App\Services\Ai\AiCallRequest;
use App\Services\Ai\AiClient;
use InvalidArgumentException;

/**
 * The proposal.generate tool (§6.4/§7.2): admin click → one new
 * proposal_documents version (generated_by=ai), drafted from spec + DCP +
 * the latest (possibly admin-edited) assessment. The assessment is a hard
 * prerequisite — §6.4's ordering rule — enforced here as well as in the UI,
 * so no route can produce an ungrounded proposal. Like AssessmentGenerator,
 * a failed/gated AI call returns null (admin retries or writes manually);
 * there is no deterministic fallback.
 */
class ProposalGenerator
{
    public function __construct(
        private readonly AiClient $aiClient,
        private readonly ProposalAssembler $assembler,
    ) {}

    public function generate(BusinessOwner $businessOwner): ?ProposalDocument
    {
        $assessment = $businessOwner->latestAssessmentDocument;

        if ($assessment === null) {
            throw new InvalidArgumentException('proposal.generate requires an assessment to ground pricing/timeline in (§6.4); generate one first.');
        }

        $result = $this->aiClient->call(AiCallRequest::fromContextBlocks(
            tool: 'proposal.generate',
            blocks: $this->assembler->assemble($businessOwner, $assessment),
            businessOwner: $businessOwner,
        ));

        if (! $result->successful || trim((string) $result->text) === '') {
            return null;
        }

        return ProposalDocument::create([
            'business_owner_id' => $businessOwner->id,
            'version' => ($businessOwner->proposalDocuments()->max('version') ?? 0) + 1,
            'markdown' => trim($result->text),
            'generated_by' => 'ai',
            'model_meta' => [
                'model' => $result->aiCall->model,
                'prompt_version' => 1,
                'ai_call_id' => $result->aiCall->id,
                'assessment_document_id' => $assessment->id,
                'assessment_version' => $assessment->version,
            ],
        ]);
    }
}
