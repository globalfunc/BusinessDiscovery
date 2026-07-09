<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiCall;
use App\Models\BusinessOwner;
use App\Services\Ai\AiSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * §6.7 usage explorer: per period/BO/call-type token counts and cost
 * estimates, filterable, backed entirely by the existing ai_calls log
 * (§7.1). Read-only — no gating/filtering logic lives here.
 */
class AiUsageController extends Controller
{
    public function index(Request $request, AiSettings $aiSettings): Response
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'business_owner_id' => ['nullable', 'integer'],
            'tool' => ['nullable', 'string'],
        ]);

        $from = isset($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->startOfMonth();
        $to = isset($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now()->endOfDay();

        $query = AiCall::query()->whereBetween('created_at', [$from, $to]);

        if (! empty($filters['business_owner_id'])) {
            $query->where('business_owner_id', $filters['business_owner_id']);
        }

        if (! empty($filters['tool'])) {
            $query->where('tool', $filters['tool']);
        }

        $summary = (clone $query)
            ->selectRaw('coalesce(sum(input_tokens), 0) as input_tokens')
            ->selectRaw('coalesce(sum(output_tokens), 0) as output_tokens')
            ->selectRaw('coalesce(sum(cost_estimate), 0) as cost')
            ->selectRaw('count(*) as calls')
            ->selectRaw("count(*) filter (where status != 'success') as blocked_or_failed")
            ->first();

        $byTool = (clone $query)
            ->select('tool')
            ->selectRaw('sum(input_tokens) as input_tokens, sum(output_tokens) as output_tokens, sum(cost_estimate) as cost, count(*) as calls')
            ->groupBy('tool')
            ->orderByDesc('calls')
            ->get();

        $byBusinessOwner = (clone $query)
            ->select('business_owner_id')
            ->selectRaw('sum(input_tokens) as input_tokens, sum(output_tokens) as output_tokens, sum(cost_estimate) as cost, count(*) as calls')
            ->whereNotNull('business_owner_id')
            ->groupBy('business_owner_id')
            ->orderByDesc('calls')
            ->with('businessOwner:id,name,company,ai_token_cap')
            ->get()
            ->map(function (AiCall $row) use ($aiSettings) {
                $cap = $row->businessOwner?->ai_token_cap ?? $aiSettings->budgets()['per_bo_token_cap'];
                $tokens = $row->input_tokens + $row->output_tokens;

                return [
                    'business_owner_id' => $row->business_owner_id,
                    'name' => $row->businessOwner?->name,
                    'company' => $row->businessOwner?->company,
                    'input_tokens' => (int) $row->input_tokens,
                    'output_tokens' => (int) $row->output_tokens,
                    'cost' => (float) $row->cost,
                    'calls' => (int) $row->calls,
                    'cap' => $cap,
                    'pct_of_cap' => $cap ? round(($tokens / $cap) * 100, 1) : null,
                ];
            });

        $rows = (clone $query)
            ->with('businessOwner:id,name,company')
            ->latest()
            ->paginate(50)
            ->withQueryString()
            ->through(fn (AiCall $call) => [
                'id' => $call->id,
                'tool' => $call->tool,
                'business_owner' => $call->businessOwner?->name,
                'model' => $call->model,
                'input_tokens' => $call->input_tokens,
                'output_tokens' => $call->output_tokens,
                'cost_estimate' => $call->cost_estimate,
                'latency_ms' => $call->latency_ms,
                'status' => $call->status->value,
                'vendor_leak' => $call->vendor_leak,
                'created_at' => $call->created_at?->toIso8601String(),
            ]);

        $globalBudget = $aiSettings->budgets();
        $globalTokensThisMonth = AiCall::query()
            ->where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('coalesce(sum(input_tokens + output_tokens), 0) as tokens')
            ->value('tokens');

        return Inertia::render('Admin/AiUsage/Index', [
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'business_owner_id' => $filters['business_owner_id'] ?? null,
                'tool' => $filters['tool'] ?? null,
            ],
            'summary' => [
                'input_tokens' => (int) $summary->input_tokens,
                'output_tokens' => (int) $summary->output_tokens,
                'cost' => (float) $summary->cost,
                'calls' => (int) $summary->calls,
                'blocked_or_failed' => (int) $summary->blocked_or_failed,
            ],
            'byTool' => $byTool->map(fn (AiCall $row) => [
                'tool' => $row->tool,
                'input_tokens' => (int) $row->input_tokens,
                'output_tokens' => (int) $row->output_tokens,
                'cost' => (float) $row->cost,
                'calls' => (int) $row->calls,
            ]),
            'byBusinessOwner' => $byBusinessOwner,
            'rows' => $rows,
            'businessOwners' => BusinessOwner::query()->orderBy('name')->get(['id', 'name', 'company']),
            'tools' => AiSettingsController::TOOLS,
            'globalBudget' => [
                'cap' => $globalBudget['global_monthly_token_cap'],
                'tokens_this_month' => (int) $globalTokensThisMonth,
                'alert_threshold_pct' => $globalBudget['alert_threshold_pct'],
                'pct_of_cap' => $globalBudget['global_monthly_token_cap']
                    ? round(($globalTokensThisMonth / $globalBudget['global_monthly_token_cap']) * 100, 1)
                    : null,
            ],
        ]);
    }
}
