<?php

namespace App\Services\Ai;

use App\Models\PromptTemplateOverride;
use App\Services\Ai\Contracts\PromptTemplate;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Maps tool keys (§7.2) to their versioned PromptTemplate. Bound as a
 * singleton in AppServiceProvider; S3.1-S3.3/S3.5 register their templates
 * against it (e.g. in a service provider's boot() method) instead of
 * hardcoding prompt strings inside the tool classes.
 *
 * S4.7 layers the §6.7 "prompt template viewer/editor with version history
 * and reset-to-default" on top: get() transparently prefers an active
 * `prompt_template_overrides` row over the hardcoded template below, so no
 * tool/assembler code changed. base() always returns the hardcoded default
 * (what "reset to default" reverts to); history() and the two mutators are
 * used only by the admin AiSettingsController.
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
        $base = $this->base($key);

        $override = PromptTemplateOverride::query()
            ->where('tool', $key)
            ->where('active', true)
            ->first();

        if ($override === null) {
            return $base;
        }

        return new class($base, $override) implements PromptTemplate
        {
            public function __construct(private readonly PromptTemplate $base, private readonly PromptTemplateOverride $override) {}

            public function key(): string
            {
                return $this->base->key();
            }

            public function version(): int
            {
                return $this->override->version;
            }

            public function systemPrompt(): string
            {
                return $this->override->system_prompt;
            }
        };
    }

    public function base(string $key): PromptTemplate
    {
        return $this->templates[$key]
            ?? throw new InvalidArgumentException("No prompt template registered for tool [{$key}].");
    }

    public function has(string $key): bool
    {
        return isset($this->templates[$key]);
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->templates);
    }

    /**
     * Save a new active override version for $key, deactivating any prior
     * override rows. Versions continue on from the hardcoded template's own
     * version() so the first override is never version 1 again.
     */
    public function saveOverride(string $key, string $systemPrompt): PromptTemplateOverride
    {
        $this->base($key); // throws if $key isn't a real tool

        $nextVersion = max(
            $this->base($key)->version(),
            PromptTemplateOverride::query()->where('tool', $key)->max('version') ?? 0,
        ) + 1;

        PromptTemplateOverride::query()->where('tool', $key)->where('active', true)->update(['active' => false]);

        return PromptTemplateOverride::create([
            'tool' => $key,
            'version' => $nextVersion,
            'system_prompt' => $systemPrompt,
            'active' => true,
        ]);
    }

    /** Deactivates every override row for $key so get() falls back to base(). */
    public function resetToDefault(string $key): void
    {
        PromptTemplateOverride::query()->where('tool', $key)->where('active', true)->update(['active' => false]);
    }

    /** @return Collection<int, PromptTemplateOverride> newest first */
    public function history(string $key): Collection
    {
        return PromptTemplateOverride::query()->where('tool', $key)->orderByDesc('version')->get();
    }
}
