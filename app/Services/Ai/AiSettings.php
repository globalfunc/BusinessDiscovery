<?php

namespace App\Services\Ai;

use App\Models\Setting;

/**
 * §6.7 "AI & system settings" — the read/write layer over the admin-editable
 * knobs config/ai.php used to be the only source for: default model/effort/
 * max_tokens/temperature, per-tool overrides, token budgets & alert
 * thresholds, and the cost-estimate pricing table. Values are stored as
 * `settings` rows (reusing the same key/value table as S4.6's
 * `show_prices_to_bo` toggle) and fall back to the config/ai.php default when
 * no row exists yet — so every existing config()-driven test and .env
 * deployment keeps working untouched until an admin actually edits a value.
 *
 * AiClient and BudgetGate read through this class instead of `config('ai...')`
 * directly; neither the pre-flight gating order nor the vendor-filter scan
 * changed (§7.6/§7.7 logic is untouched — only the config source moved).
 */
class AiSettings
{
    private const DEFAULTS_KEY = 'ai_defaults';

    private const BUDGETS_KEY = 'ai_budgets';

    private const PRICING_KEY = 'ai_pricing';

    /** @var array<string, string> tool => model, effort, max_tokens, temperature */
    public function toolConfig(string $tool, string $key): mixed
    {
        return $this->settingValue($this->toolKey($tool))[$key]
            ?? config('ai.tools')[$tool][$key]
            ?? null;
    }

    public function setToolConfig(string $tool, array $values): void
    {
        Setting::query()->updateOrCreate(['key' => $this->toolKey($tool)], ['value' => array_filter(
            $values,
            fn ($value) => $value !== null && $value !== '',
        )]);
    }

    public function default(string $key): mixed
    {
        return $this->settingValue(self::DEFAULTS_KEY)[$key] ?? config("ai.default_{$key}");
    }

    /**
     * @return array{model: string, effort: ?string, max_tokens: int, temperature: ?float}
     */
    public function defaults(): array
    {
        return [
            'model' => $this->default('model'),
            'effort' => $this->default('effort'),
            'max_tokens' => $this->default('max_tokens'),
            'temperature' => $this->default('temperature'),
        ];
    }

    public function setDefaults(array $values): void
    {
        Setting::query()->updateOrCreate(['key' => self::DEFAULTS_KEY], ['value' => array_filter(
            $values,
            fn ($value) => $value !== null && $value !== '',
        )]);
    }

    /**
     * @return array{input: float, output: float}|null
     */
    public function pricing(string $model): ?array
    {
        return $this->settingValue(self::PRICING_KEY)[$model] ?? config("ai.pricing.{$model}");
    }

    /**
     * @return array<string, array{input: float, output: float}> every known model, DB overrides merged over config defaults
     */
    public function pricingTable(): array
    {
        $stored = $this->settingValue(self::PRICING_KEY);

        return array_replace(config('ai.pricing', []), $stored);
    }

    public function setPricingTable(array $pricing): void
    {
        Setting::query()->updateOrCreate(['key' => self::PRICING_KEY], ['value' => $pricing]);
    }

    /**
     * @return array{global_monthly_token_cap: ?int, per_bo_token_cap: ?int, alert_threshold_pct: int, rate_limit_per_minute: int, budget_mode: string}
     */
    public function budgets(): array
    {
        $stored = $this->settingValue(self::BUDGETS_KEY);

        return [
            'global_monthly_token_cap' => $stored['global_monthly_token_cap'] ?? config('ai.global_monthly_token_cap'),
            'per_bo_token_cap' => $stored['per_bo_token_cap'] ?? config('ai.per_bo_token_cap'),
            'alert_threshold_pct' => $stored['alert_threshold_pct'] ?? config('ai.alert_threshold_pct', 80),
            'rate_limit_per_minute' => $stored['rate_limit_per_minute'] ?? config('ai.rate_limit_per_minute'),
            'budget_mode' => $stored['budget_mode'] ?? config('ai.budget_mode', 'hard'),
        ];
    }

    public function setBudgets(array $values): void
    {
        Setting::query()->updateOrCreate(['key' => self::BUDGETS_KEY], ['value' => array_filter(
            $values,
            fn ($value) => $value !== null && $value !== '',
        )]);
    }

    private function toolKey(string $tool): string
    {
        return "ai_tool.{$tool}";
    }

    /**
     * @return array<string, mixed>
     */
    private function settingValue(string $key): array
    {
        $value = Setting::query()->where('key', $key)->first()?->value;

        return is_array($value) ? $value : [];
    }
}
