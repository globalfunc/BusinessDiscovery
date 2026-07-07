<?php

namespace App\Services\Ai\Contracts;

/**
 * Validates a tool's parsed JSON output against its §7.2 output shape
 * (e.g. the DCP shape in §3.1, or the Suggestion Card contract in §7.4).
 * One implementation per tool, added alongside that tool in S3.1-S3.3/S3.5.
 */
interface OutputSchemaValidator
{
    /**
     * @param  array<string, mixed>  $data
     * @return string[] validation error messages; empty array means valid
     */
    public function validate(array $data): array;
}
