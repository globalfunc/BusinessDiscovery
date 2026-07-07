import { Head } from '@inertiajs/react';
import { Activity, Kanban, Sparkles } from 'lucide-react';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AdminLayout from '@/Layouts/AdminLayout';

const kpis = [
    { label: 'Total BOs' },
    { label: 'Links visited' },
    { label: 'In progress' },
    { label: 'Submitted' },
    { label: 'Proposals sent' },
    { label: 'Closed' },
];

export default function Dashboard() {
    return (
        <AdminLayout>
            <Head title="Dashboard" />

            <section className="animate-rise rounded-section border border-line-strong bg-surface-glass p-8 backdrop-blur-2xl">
                <p className="eyebrow text-accent">Admin</p>
                <h1 className="mt-2 font-display text-3xl font-semibold text-text">Dashboard</h1>
                <p className="mt-2 max-w-xl font-body text-sm text-text-muted">
                    Pipeline and AI-usage KPIs land here once BOs start moving through the discovery
                    flow (Stage 4).
                </p>

                <div className="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                    {kpis.map((kpi) => (
                        <div key={kpi.label} className="rounded-admin border border-line bg-surface p-4">
                            <div className="font-display text-3xl font-semibold tabular-nums text-text">—</div>
                            <div className="mt-1 font-ui text-xs text-text-faint">{kpi.label}</div>
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
                        <p className="font-body text-sm text-text-faint">
                            No activity yet — link visits, phase completions, and submissions will
                            appear here.
                        </p>
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
                        <p className="font-body text-sm text-text-faint">
                            Token and cost summaries wire up once the AI layer (Stage 3) is live.
                        </p>
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
                            The lead pipeline board arrives in S1.5 — this card will summarize
                            stage counts.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
