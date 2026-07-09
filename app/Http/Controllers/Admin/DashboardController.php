<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PipelineStage;
use App\Http\Controllers\Controller;
use App\Models\ActivityEvent;
use App\Models\AiCall;
use App\Models\BusinessOwner;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Stage order used to turn the single `current_stage` column into a
     * funnel of "reached this stage or beyond" KPI counts.
     */
    private const FUNNEL_ORDER = [
        PipelineStage::Prospect,
        PipelineStage::ReferralSent,
        PipelineStage::LinkVisited,
        PipelineStage::DiscoveryInProgress,
        PipelineStage::DiscoveryComplete,
        PipelineStage::ProposalSent,
        PipelineStage::Negotiation,
        PipelineStage::Won,
        PipelineStage::Lost,
    ];

    public function __invoke(Request $request): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'kpis' => $this->kpis(),
            'aiUsage' => $this->aiUsage(),
            'activity' => $this->activity($request),
        ]);
    }

    private function kpis(): array
    {
        $stageAtOrBeyond = fn (PipelineStage $stage) => BusinessOwner::query()
            ->whereIn('current_stage', $this->stagesFrom($stage))
            ->count();

        return [
            'total' => BusinessOwner::count(),
            'links_visited' => $stageAtOrBeyond(PipelineStage::LinkVisited),
            'in_progress' => BusinessOwner::where('current_stage', PipelineStage::DiscoveryInProgress)->count(),
            'submitted' => $stageAtOrBeyond(PipelineStage::DiscoveryComplete),
            'proposals_sent' => $stageAtOrBeyond(PipelineStage::ProposalSent),
            'closed' => BusinessOwner::whereIn('current_stage', [PipelineStage::Won, PipelineStage::Lost])->count(),
        ];
    }

    /**
     * @return list<string>
     */
    private function stagesFrom(PipelineStage $stage): array
    {
        $index = array_search($stage, self::FUNNEL_ORDER, strict: true);

        return array_map(
            fn (PipelineStage $s) => $s->value,
            array_slice(self::FUNNEL_ORDER, $index),
        );
    }

    private function aiUsage(): array
    {
        $totals = fn ($query) => $query
            ->selectRaw('coalesce(sum(input_tokens + output_tokens), 0) as tokens, coalesce(sum(cost_estimate), 0) as cost, count(*) as calls')
            ->first();

        $overall = $totals(AiCall::query());
        $thisMonth = $totals(AiCall::query()->where('created_at', '>=', now()->startOfMonth()));

        $topConsumers = AiCall::query()
            ->select('business_owner_id')
            ->selectRaw('sum(input_tokens + output_tokens) as tokens, sum(cost_estimate) as cost')
            ->whereNotNull('business_owner_id')
            ->groupBy('business_owner_id')
            ->orderByDesc('tokens')
            ->with('businessOwner:id,name,company')
            ->limit(5)
            ->get()
            ->map(fn (AiCall $row) => [
                'business_owner_id' => $row->business_owner_id,
                'name' => $row->businessOwner?->name,
                'company' => $row->businessOwner?->company,
                'tokens' => (int) $row->tokens,
                'cost' => round((float) $row->cost, 6),
            ]);

        return [
            'overall' => ['tokens' => (int) $overall->tokens, 'cost' => round((float) $overall->cost, 6), 'calls' => (int) $overall->calls],
            'this_month' => ['tokens' => (int) $thisMonth->tokens, 'cost' => round((float) $thisMonth->cost, 6), 'calls' => (int) $thisMonth->calls],
            'top_consumers' => $topConsumers,
        ];
    }

    private function activity(Request $request): array
    {
        $paginator = ActivityEvent::query()
            ->with('businessOwner:id,name,company')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return [
            'data' => collect($paginator->items())->map(fn (ActivityEvent $event) => [
                'id' => $event->id,
                'type' => $event->type,
                'payload' => $event->payload,
                'business_owner' => $event->businessOwner ? [
                    'id' => $event->businessOwner->id,
                    'name' => $event->businessOwner->name,
                    'company' => $event->businessOwner->company,
                ] : null,
                'created_at' => $event->created_at?->toIso8601String(),
            ]),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}
