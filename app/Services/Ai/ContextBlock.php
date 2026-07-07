<?php

namespace App\Services\Ai;

final class ContextBlock
{
    public function __construct(
        public readonly ContextBlockType $type,
        public readonly string $content,
    ) {}
}
