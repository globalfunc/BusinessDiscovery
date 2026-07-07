<?php

namespace App\Services\Ai;

use App\Models\AiCall;

final class AiCallResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly ?string $text,
        public readonly AiCall $aiCall,
    ) {}
}
