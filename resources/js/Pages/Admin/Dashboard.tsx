import { Head, Link, router } from '@inertiajs/react';
import { Activity, Kanban, Sparkles } from 'lucide-react';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AdminLayout from '@/Layouts/AdminLayout';

type Kpis = {
    total: number;
    links_visited: number;
    in_progress: number;
    submitted: number;
    proposals_sent: number;
    closed: number;
};

type UsageBucket = { tokens: number; cost: number; calls: number };

type TopConsumer = {
    business_owner_id: number;
    name: string | null;
    company: string | null;
    tokens: number;
    cost: number;
};

type AiUsage = {
    overall: UsageBucket;
    this_month: UsageBucket;
    top_consumers: TopConsumer[];
};

type ActivityEventRow = {
    id: number;
    type: string;
    payload: Record<string, unknown> | null;
    business_owner: { id: number; name: string; company: string } | null;
    created_at: string | null;
};

type ActivityFeed = {
    data: ActivityEventRow[];
    current_page: number;
    last_page: number;
};

function formatDate(value: string | null) {
    return value ? new Date(value).toLocaleString() : '—';
}

function formatCost(cost: number) {
    return `$${cost.toFixed(4)}`;
}

export default function Dashboard({ kpis, aiUsage, activity }: { kpis: Kpis; aiUsage: AiUsage; activity: ActivityFeed }) {
    const tiles = [
        { label: 'Total BOs', value: kpis.total },
        { label: 'Links visited', value: kpis.links_visited },
        { label: 'In progress', value: kpis.in_progress },
        { label: 'Submitted', value: kpis.submitted },
        { label: 'Proposals sent', value: kpis.proposals_sent },
        { label: 'Closed', value: kpis.closed },
    ];

    const goToPage = (page: number) => {
        router.get(route('admin.dashboard'), { page }, { preserveScroll: true, preserveState: true, only: ['activity'] });
    };

    return (
        <AdminLayout>
            <Head title="Dashboard" />

            <section className="animate-rise rounded-section border border-line-strong bg-surface-glass p-8 backdrop-blur-2xl">
                <p className="eyebrow text-accent">Admin</p>
                <h1 className="mt-2 font-display text-3xl font-semibold text-text">Dashboard</h1>
                <p className="mt-2 max-w-xl font-body text-sm text-text-muted">
                    Live pipeline and AI-usage KPIs across every business owner.
                </p>

                <div className="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                    {tiles.map((tile) => (
                        <div key={tile.label} className="rounded-admin border border-line bg-surface p-4">
                            <div className="font-display text-3xl font-semibold tabular-nums text-text">
                                {tile.value}
                            </div>
                            <div className="mt-1 font-ui text-xs text-text-faint">{tile.label}</div>
                        </div>
                    ))}
                </div>
            </section>

            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Activity className="h-4 w-4 text-blue" />
                            Recent activity
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {activity.data.length === 0 && (
                            <p className="font-body text-sm text-text-faint">
                                No activity yet — link visits, phase completions, and submissions will
                                appear here.
                            </p>
                        )}
                        <ul className="flex flex-col gap-3">
                            {activity.data.map((event) => (
                                <li key={event.id} className="border-b border-line pb-2 last:border-0 last:pb-0">
                                    <p className="font-ui text-sm text-text">{event.type.replace(/_/g, ' ')}</p>
                                    <p className="font-body text-xs text-text-faint">
                                        {event.business_owner ? `${event.business_owner.name} — ` : ''}
                                        {formatDate(event.created_at)}
                                    </p>
                                </li>
                            ))}
                        </ul>

                        {activity.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-between font-ui text-xs text-text-faint">
                                <button
                                    type="button"
                                    disabled={activity.current_page <= 1}
                                    onClick={() => goToPage(activity.current_page - 1)}
                                    className="disabled:opacity-40"
                                >
                                    Previous
                                </button>
                                <span>
                                    Page {activity.current_page} of {activity.last_page}
                                </span>
                                <button
                                    type="button"
                                    disabled={activity.current_page >= activity.last_page}
                                    onClick={() => goToPage(activity.current_page + 1)}
                                    className="disabled:opacity-40"
                                >
                                    Next
                                </button>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Sparkles className="h-4 w-4 text-accent" />
                            AI usage
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <div className="font-display text-xl font-semibold text-text">
                                    {aiUsage.overall.tokens.toLocaleString()}
                                </div>
                                <div className="font-ui text-xs text-text-faint">
                                    tokens overall · {formatCost(aiUsage.overall.cost)}
                                </div>
                            </div>
                            <div>
                                <div className="font-display text-xl font-semibold text-text">
                                    {aiUsage.this_month.tokens.toLocaleString()}
                                </div>
                                <div className="font-ui text-xs text-text-faint">
                                    tokens this month · {formatCost(aiUsage.this_month.cost)}
                                </div>
                            </div>
                        </div>

                        <div className="mt-5">
                            <p className="font-ui text-xs uppercase tracking-wide text-text-faint">Top consumers</p>
                            {aiUsage.top_consumers.length === 0 && (
                                <p className="mt-2 font-body text-sm text-text-faint">No AI usage recorded yet.</p>
                            )}
                            <ul className="mt-2 flex flex-col gap-2">
                                {aiUsage.top_consumers.map((consumer) => (
                                    <li
                                        key={consumer.business_owner_id}
                                        className="flex items-center justify-between border-b border-line pb-2 last:border-0 last:pb-0"
                                    >
                                        <Link
                                            href={route('admin.business-owners.show', consumer.business_owner_id)}
                                            className="font-ui text-sm text-text hover:text-accent"
                                        >
                                            {consumer.name ?? consumer.company ?? `BO #${consumer.business_owner_id}`}
                                        </Link>
                                        <span className="font-ui text-xs text-text-faint">
                                            {consumer.tokens.toLocaleString()} tok · {formatCost(consumer.cost)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </CardContent>
                </Card>

                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Kanban className="h-4 w-4 text-blue" />
                            Pipeline snapshot
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="font-body text-sm text-text-faint">
                            Full stage counts and drag-and-drop wiring land in S4.3 — see the{' '}
                            <Link href={route('admin.pipeline.index')} className="text-accent hover:underline">
                                Pipeline board
                            </Link>{' '}
                            for the live list today.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
