<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\PromptTemplate;
use InvalidArgumentException;

/**
 * Maps tool keys (§7.2) to their versioned PromptTemplate. Bound as a
 * singleton in AppServiceProvider; S3.1-S3.3/S3.5 register their templates
 * against it (e.g. in a service provider's boot() method) instead of
 * hardcoding prompt strings inside the tool classes.
 */
class PromptTemplateRegistry
{
    /** @var array<string, PromptTemplate> */
    private array $templates = [];

    public function register(PromptTemplate $template): void
    {
        $this->templates[$template->key()] = $template;
    }

    public function get(string $key): PromptTemplate
    {
        return $this->templates[$key]
            ?? throw new InvalidArgumentException("No prompt template registered for tool [{$key}].");
    }

    public function has(string $key): bool
    {
        return isset($this->templates[$key]);
    }
}
