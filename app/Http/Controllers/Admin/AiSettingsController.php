<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Ai\AiSettings;
use App\Services\Ai\PromptTemplateRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin CRUD for §6.7 "AI & system settings": per-tool model/effort/
 * max_tokens/temperature, global+per-BO token budgets & alert threshold,
 * hard-stop mode, the cost-estimate pricing table, and the prompt template
 * viewer/editor. Every write goes through AiSettings/PromptTemplateRegistry —
 * this controller never touches AiClient's gating/filtering logic directly.
 */
class AiSettingsController extends Controller
{
    /** §7.2 call types, in the order they appear through a BO/admin lifecycle. */
    public const TOOLS = [
        'dcp.generate',
        'suggest.services',
        'suggest.branding',
        'suggest.content_social',
        'suggest.growth',
        'spec.compile',
        'spec.amend',
        'assessment.generate',
        'proposal.generate',
        'email.generate',
    ];

    public function index(AiSettings $aiSettings, PromptTemplateRegistry $registry): Response
    {
        $tools = collect(self::TOOLS)->map(fn (string $tool) => [
            'tool' => $tool,
            'model' => $aiSettings->toolConfig($tool, 'model'),
            'effort' => $aiSettings->toolConfig($tool, 'effort'),
            'max_tokens' => $aiSettings->toolConfig($tool, 'max_tokens'),
            'temperature' => $aiSettings->toolConfig($tool, 'temperature'),
        ]);

        $promptTemplates = collect(self::TOOLS)->map(function (string $tool) use ($registry) {
            $active = $registry->get($tool);
            $base = $registry->base($tool);

            return [
                'tool' => $tool,
                'current_version' => $active->version(),
                'current_prompt' => $active->systemPrompt(),
                'is_override' => $active->version() !== $base->version(),
                'default_prompt' => $base->systemPrompt(),
                'default_version' => $base->version(),
                'history' => $registry->history($tool)->map(fn ($row) => [
                    'id' => $row->id,
                    'version' => $row->version,
                    'active' => $row->active,
                    'created_at' => $row->created_at?->toIso8601String(),
                ]),
            ];
        });

        return Inertia::render('Admin/AiSettings/Index', [
            'defaults' => $aiSettings->defaults(),
            'tools' => $tools,
            'budgets' => $aiSettings->budgets(),
            'pricing' => $aiSettings->pricingTable(),
            'promptTemplates' => $promptTemplates,
            'availableModels' => array_keys(config('ai.pricing', [])),
            'effortLevels' => ['low', 'medium', 'high', 'xhigh', 'max'],
        ]);
    }

    public function updateDefaults(Request $request, AiSettings $aiSettings): RedirectResponse
    {
        $data = $request->validate([
            'model' => ['nullable', 'string', 'max:255'],
            'effort' => ['nullable', 'string', 'in:low,medium,high,xhigh,max'],
            'max_tokens' => ['nullable', 'integer', 'min:1', 'max:200000'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ]);

        $aiSettings->setDefaults($data);

        return back()->with('success', 'AI defaults updated.');
    }

    public function updateTool(Request $request, AiSettings $aiSettings): RedirectResponse
    {
        $data = $request->validate([
            'tool' => ['required', 'string', 'in:'.implode(',', self::TOOLS)],
            'model' => ['nullable', 'string', 'max:255'],
            'effort' => ['nullable', 'string', 'in:low,medium,high,xhigh,max'],
            'max_tokens' => ['nullable', 'integer', 'min:1', 'max:200000'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ]);

        $aiSettings->setToolConfig($data['tool'], collect($data)->except('tool')->all());

        return back()->with('success', "Settings for {$data['tool']} updated.");
    }

    public function updateBudgets(Request $request, AiSettings $aiSettings): RedirectResponse
    {
        $data = $request->validate([
            'global_monthly_token_cap' => ['nullable', 'integer', 'min:0'],
            'per_bo_token_cap' => ['nullable', 'integer', 'min:0'],
            'alert_threshold_pct' => ['required', 'integer', 'min:1', 'max:100'],
            'rate_limit_per_minute' => ['required', 'integer', 'min:1'],
            'budget_mode' => ['required', 'string', 'in:hard,soft'],
        ]);

        $aiSettings->setBudgets($data);

        return back()->with('success', 'AI budgets updated.');
    }

    public function updatePricing(Request $request, AiSettings $aiSettings): RedirectResponse
    {
        $data = $request->validate([
            'pricing' => ['required', 'array'],
            'pricing.*.input' => ['required', 'numeric', 'min:0'],
            'pricing.*.output' => ['required', 'numeric', 'min:0'],
        ]);

        $aiSettings->setPricingTable($data['pricing']);

        return back()->with('success', 'Pricing table updated.');
    }

    public function storePromptTemplate(Request $request, PromptTemplateRegistry $registry): RedirectResponse
    {
        $data = $request->validate([
            'tool' => ['required', 'string', 'in:'.implode(',', self::TOOLS)],
            'system_prompt' => ['required', 'string', 'max:20000'],
        ]);

        $registry->saveOverride($data['tool'], $data['system_prompt']);

        return back()->with('success', "New prompt version saved for {$data['tool']}.");
    }

    public function resetPromptTemplate(Request $request, PromptTemplateRegistry $registry): RedirectResponse
    {
        $data = $request->validate([
            'tool' => ['required', 'string', 'in:'.implode(',', self::TOOLS)],
        ]);

        $registry->resetToDefault($data['tool']);

        return back()->with('success', "{$data['tool']} reverted to its default prompt.");
    }
}
