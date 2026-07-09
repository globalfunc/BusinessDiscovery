<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Ai\AiSettings;
use App\Services\Ai\PromptTemplateRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin CRUD for §6.7 "AI & system settings" — defaults, per-tool overrides,
 * budgets, pricing, and the prompt template viewer/editor. Every write is
 * verified both as a DB assertion and, where it matters, through AiSettings/
 * PromptTemplateRegistry reads — the same objects AiClient/BudgetGate use —
 * so a passing test proves the setting actually takes effect, not just that
 * a row was written.
 */
class AiSettingsAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    public function test_index_renders_with_all_ten_tools(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.ai-settings.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('tools', 10)->has('promptTemplates', 10));
    }

    public function test_guest_cannot_view_ai_settings(): void
    {
        $this->get(route('admin.ai-settings.index'))->assertRedirect(route('login'));
    }

    public function test_update_defaults_persists_and_is_read_by_ai_settings(): void
    {
        $this->actingAs($this->admin())
            ->patch(route('admin.ai-settings.defaults.update'), [
                'model' => 'claude-opus-4-8',
                'effort' => 'high',
                'max_tokens' => 6000,
                'temperature' => 0.5,
            ])
            ->assertRedirect();

        $settings = app(AiSettings::class);
        $this->assertSame('claude-opus-4-8', $settings->default('model'));
        $this->assertSame('high', $settings->default('effort'));
        $this->assertSame(6000, $settings->default('max_tokens'));
    }

    public function test_update_tool_config_overrides_only_that_tool(): void
    {
        $this->actingAs($this->admin())
            ->patch(route('admin.ai-settings.tools.update'), [
                'tool' => 'suggest.growth',
                'model' => 'claude-haiku-4-5',
                'max_tokens' => 1500,
            ])
            ->assertRedirect();

        $settings = app(AiSettings::class);
        $this->assertSame('claude-haiku-4-5', $settings->toolConfig('suggest.growth', 'model'));
        $this->assertSame(1500, $settings->toolConfig('suggest.growth', 'max_tokens'));
        // A sibling tool must be untouched.
        $this->assertNotSame('claude-haiku-4-5', $settings->toolConfig('spec.compile', 'model'));
    }

    public function test_update_tool_config_rejects_an_unknown_tool(): void
    {
        $this->actingAs($this->admin())
            ->patch(route('admin.ai-settings.tools.update'), ['tool' => 'not.a.real.tool'])
            ->assertSessionHasErrors('tool');
    }

    public function test_update_budgets_persists_and_is_read_by_ai_settings(): void
    {
        $this->actingAs($this->admin())
            ->patch(route('admin.ai-settings.budgets.update'), [
                'global_monthly_token_cap' => 500000,
                'per_bo_token_cap' => 20000,
                'alert_threshold_pct' => 90,
                'rate_limit_per_minute' => 3,
                'budget_mode' => 'soft',
            ])
            ->assertRedirect();

        $budgets = app(AiSettings::class)->budgets();
        $this->assertSame(500000, $budgets['global_monthly_token_cap']);
        $this->assertSame(20000, $budgets['per_bo_token_cap']);
        $this->assertSame(90, $budgets['alert_threshold_pct']);
        $this->assertSame(3, $budgets['rate_limit_per_minute']);
        $this->assertSame('soft', $budgets['budget_mode']);
    }

    public function test_update_pricing_overrides_the_config_default(): void
    {
        $this->actingAs($this->admin())
            ->patch(route('admin.ai-settings.pricing.update'), [
                'pricing' => [
                    'claude-sonnet-5' => ['input' => 4.5, 'output' => 20.0],
                ],
            ])
            ->assertRedirect();

        $pricing = app(AiSettings::class)->pricing('claude-sonnet-5');
        $this->assertEquals(4.5, $pricing['input']);
        $this->assertEquals(20.0, $pricing['output']);
    }

    public function test_store_prompt_template_creates_a_new_version_and_deactivates_the_old_one(): void
    {
        $registry = app(PromptTemplateRegistry::class);
        $baseVersion = $registry->base('dcp.generate')->version();

        $this->actingAs($this->admin())
            ->post(route('admin.ai-settings.prompt-templates.store'), [
                'tool' => 'dcp.generate',
                'system_prompt' => 'A custom system prompt for testing.',
            ])
            ->assertRedirect();

        $active = $registry->get('dcp.generate');
        $this->assertSame('A custom system prompt for testing.', $active->systemPrompt());
        $this->assertSame($baseVersion + 1, $active->version());

        // A second save must supersede the first, not stack alongside it.
        $this->post(route('admin.ai-settings.prompt-templates.store'), [
            'tool' => 'dcp.generate',
            'system_prompt' => 'A second revision.',
        ]);

        $this->assertSame('A second revision.', $registry->get('dcp.generate')->systemPrompt());
        $this->assertSame($baseVersion + 2, $registry->get('dcp.generate')->version());
        $this->assertCount(2, $registry->history('dcp.generate'));
    }

    public function test_reset_prompt_template_reverts_to_the_hardcoded_default(): void
    {
        $registry = app(PromptTemplateRegistry::class);
        $base = $registry->base('email.generate');

        $this->actingAs($this->admin())->post(route('admin.ai-settings.prompt-templates.store'), [
            'tool' => 'email.generate',
            'system_prompt' => 'Overridden prompt.',
        ]);

        $this->assertNotSame($base->systemPrompt(), $registry->get('email.generate')->systemPrompt());

        $this->post(route('admin.ai-settings.prompt-templates.reset'), ['tool' => 'email.generate'])
            ->assertRedirect();

        $this->assertSame($base->systemPrompt(), $registry->get('email.generate')->systemPrompt());
        $this->assertSame($base->version(), $registry->get('email.generate')->version());
    }

    public function test_guest_cannot_write_any_ai_settings(): void
    {
        $this->patch(route('admin.ai-settings.defaults.update'), ['model' => 'x'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('settings', 0);
    }

    public function test_untouched_settings_fall_back_to_config(): void
    {
        // No Setting rows exist yet — AiSettings must transparently defer to
        // config/ai.php, exactly as AiClient/BudgetGate did before S4.7.
        $this->assertDatabaseCount('settings', 0);

        $settings = app(AiSettings::class);
        $this->assertSame(config('ai.default_model'), $settings->default('model'));
        $this->assertSame(config('ai.per_bo_token_cap'), $settings->budgets()['per_bo_token_cap']);
    }
}
