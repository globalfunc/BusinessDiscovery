<?php

namespace App\Services\Ai\Contracts;

/**
 * A versioned system prompt for one AI tool (§7.2's call-type table). Concrete
 * templates (policy text, admin context framing, task instructions) are
 * written in S3.1-S3.3/S3.5; this session only defines the registry shape.
 */
interface PromptTemplate
{
    /** Tool key, e.g. "dcp.generate" — matches the `tool` column on ai_calls. */
    public function key(): string;

    public function version(): int;

    public function systemPrompt(): string;
}
