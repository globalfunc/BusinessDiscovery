<?php

namespace App\Services\Ai\Contracts;

use App\Models\BusinessOwner;
use App\Models\DiscoverySession;
use App\Services\Ai\ContextBlock;

/**
 * Assembles the §7.3 ordered context blocks for one AI call. Concrete tools
 * (S3.1+) implement one assembler per call type; each returns blocks in the
 * fixed order system_policy → admin_context → dcp → taxonomy_catalog →
 * structured_answers → phase_notes → suggestion_presets → task_instruction.
 * A tool may omit blocks that don't apply to it — AiCallRequest::fromContextBlocks()
 * drops any block with blank content.
 */
interface InputAssembler
{
    /**
     * @return ContextBlock[]
     */
    public function assemble(BusinessOwner $businessOwner, DiscoverySession $discoverySession): array;
}
