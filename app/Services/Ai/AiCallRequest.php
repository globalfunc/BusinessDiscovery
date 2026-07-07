<?php

namespace App\Services\Ai;

use App\Models\BusinessOwner;

final class AiCallRequest
{
    /**
     * @param  string  $tool  Tool key from §7.2 (e.g. "dcp.generate"), stored on ai_calls.tool.
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function __construct(
        public readonly string $tool,
        public readonly array $messages,
        public readonly ?string $system = null,
        public readonly ?BusinessOwner $businessOwner = null,
        public readonly ?string $model = null,
        public readonly ?string $effort = null,
        public readonly ?int $maxTokens = null,
    ) {}

    /**
     * Build a request from ordered §7.3 context blocks: the system_policy
     * block becomes the system prompt, the rest are concatenated (in that
     * order) into a single user turn under light markdown headers. Blank
     * blocks are dropped rather than sent as empty sections.
     *
     * @param  ContextBlock[]  $blocks
     */
    public static function fromContextBlocks(
        string $tool,
        array $blocks,
        ?BusinessOwner $businessOwner = null,
        ?string $model = null,
        ?string $effort = null,
        ?int $maxTokens = null,
    ): self {
        $system = null;
        $sections = [];

        foreach ($blocks as $block) {
            if ($block->type === ContextBlockType::SystemPolicy) {
                $system = $block->content;

                continue;
            }

            if (trim($block->content) === '') {
                continue;
            }

            $sections[] = "## {$block->type->label()}\n\n{$block->content}";
        }

        return new self(
            tool: $tool,
            messages: [['role' => 'user', 'content' => implode("\n\n", $sections)]],
            system: $system,
            businessOwner: $businessOwner,
            model: $model,
            effort: $effort,
            maxTokens: $maxTokens,
        );
    }
}
