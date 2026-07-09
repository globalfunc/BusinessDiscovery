import { Head, router } from '@inertiajs/react';
import { type FormEventHandler, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import AdminLayout from '@/Layouts/AdminLayout';

type Filters = {
    from: string;
    to: string;
    business_owner_id: number | null;
    tool: string | null;
};

type Summary = {
    input_tokens: number;
    output_tokens: number;
    cost: number;
    calls: number;
    blocked_or_failed: number;
};

type ToolUsage = {
    tool: string;
    input_tokens: number;
    output_tokens: number;
    cost: number;
    calls: number;
};

type BoUsage = {
    business_owner_id: number;
    name: string | null;
    company: string | null;
    input_tokens: number;
    output_tokens: number;
    cost: number;
    calls: number;
    cap: number | null;
    pct_of_cap: number | null;
};

type UsageRow = {
    id: number;
    tool: string;
    business_owner: string | null;
    model: string;
    input_tokens: number;
    output_tokens: number;
    cost_estimate: number | null;
    latency_ms: number | null;
    status: string;
    vendor_leak: boolean;
    created_at: string;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
};

type GlobalBudget = {
    cap: number | null;
    tokens_this_month: number;
    alert_threshold_pct: number;
    pct_of_cap: number | null;
};

const STATUS_VARIANT: Record<string, 'default' | 'accent' | 'blue' | 'muted' | 'red'> = {
    success: 'blue',
    failed: 'red',
    budget_exhausted: 'muted',
    rate_limited: 'muted',
};

function money(value: number): string {
    return `$${value.toFixed(4)}`;
}

function FiltersBar({ filters, businessOwners, tools }: {
    filters: Filters;
    businessOwners: { id: number; name: string; company: string | null }[];
    tools: string[];
}) {
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [businessOwnerId, setBusinessOwnerId] = useState(filters.business_owner_id?.toString() ?? '');
    const [tool, setTool] = useState(filters.tool ?? '');

    const apply: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(
            route('admin.ai-usage.index'),
            { from, to, business_owner_id: businessOwnerId || undefined, tool: tool || undefined },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <Card>
            <CardContent className="pt-6">
                <form onSubmit={apply} className="grid grid-cols-2 gap-4 md:grid-cols-5">
                    <div className="flex flex-col gap-1.5">
                        <Label>From</Label>
                        <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>To</Label>
                        <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Business owner</Label>
                        <Select value={businessOwnerId} onChange={(e) => setBusinessOwnerId(e.target.value)}>
                            <option value="">All</option>
                            {businessOwners.map((bo) => (
                                <option key={bo.id} value={bo.id}>
                                    {bo.name}
                                    {bo.company ? ` (${bo.company})` : ''}
                                </option>
                            ))}
                        </Select>
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Call type</Label>
                        <Select value={tool} onChange={(e) => setTool(e.target.value)}>
                            <option value="">All</option>
                            {tools.map((t) => (
                                <option key={t} value={t}>
                                    {t}
                                </option>
                            ))}
                        </Select>
                    </div>
                    <div className="flex items-end">
                        <Button type="submit" className="w-full">
                            Apply filters
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

function SummaryCards({ summary, globalBudget }: { summary: Summary; globalBudget: GlobalBudget }) {
    const overThreshold = globalBudget.pct_of_cap !== null && globalBudget.pct_of_cap >= globalBudget.alert_threshold_pct;

    return (
        <div className="mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
            <Card>
                <CardContent className="pt-6">
                    <p className="font-ui text-xs uppercase tracking-wide text-text-faint">Calls (filtered)</p>
                    <p className="mt-1 font-display text-2xl font-semibold text-text">{summary.calls}</p>
                    {summary.blocked_or_failed > 0 && (
                        <p className="mt-1 font-body text-xs text-text-faint">{summary.blocked_or_failed} blocked/failed</p>
                    )}
                </CardContent>
            </Card>
            <Card>
                <CardContent className="pt-6">
                    <p className="font-ui text-xs uppercase tracking-wide text-text-faint">Tokens (in + out)</p>
                    <p className="mt-1 font-display text-2xl font-semibold text-text tabular-nums">
                        {(summary.input_tokens + summary.output_tokens).toLocaleString()}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent className="pt-6">
                    <p className="font-ui text-xs uppercase tracking-wide text-text-faint">Estimated cost</p>
                    <p className="mt-1 font-display text-2xl font-semibold text-text tabular-nums">{money(summary.cost)}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent className="pt-6">
                    <div className="flex items-center justify-between">
                        <p className="font-ui text-xs uppercase tracking-wide text-text-faint">Global monthly cap</p>
                        {overThreshold && <Badge variant="red">Nearing cap</Badge>}
                    </div>
                    <p className="mt-1 font-display text-2xl font-semibold text-text tabular-nums">
                        {globalBudget.cap ? `${globalBudget.pct_of_cap}%` : 'unlimited'}
                    </p>
                    {globalBudget.cap && (
                        <p className="mt-1 font-body text-xs text-text-faint">
                            {globalBudget.tokens_this_month.toLocaleString()} / {globalBudget.cap.toLocaleString()} tokens this month
                        </p>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

function ByToolTable({ rows }: { rows: ToolUsage[] }) {
    return (
        <Card className="mt-6">
            <CardHeader>
                <CardTitle className="text-base">By call type</CardTitle>
            </CardHeader>
            <CardContent className="p-0">
                <table className="w-full border-collapse font-body text-sm">
                    <thead>
                        <tr className="border-b border-line text-left font-ui text-xs uppercase tracking-wide text-text-faint">
                            <th className="px-4 py-3 font-medium">Tool</th>
                            <th className="px-4 py-3 font-medium">Calls</th>
                            <th className="px-4 py-3 font-medium">Tokens</th>
                            <th className="px-4 py-3 font-medium">Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr>
                                <td colSpan={4} className="px-4 py-8 text-center text-text-faint">
                                    No calls in this period.
                                </td>
                            </tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.tool} className="border-b border-line last:border-0">
                                <td className="px-4 py-3 font-ui font-medium text-text">{row.tool}</td>
                                <td className="px-4 py-3 tabular-nums text-text-muted">{row.calls}</td>
                                <td className="px-4 py-3 tabular-nums text-text-muted">
                                    {(row.input_tokens + row.output_tokens).toLocaleString()}
                                </td>
                                <td className="px-4 py-3 tabular-nums text-text-muted">{money(row.cost)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </CardContent>
        </Card>
    );
}

function ByBoTable({ rows }: { rows: BoUsage[] }) {
    return (
        <Card className="mt-6">
            <CardHeader>
                <CardTitle className="text-base">By business owner</CardTitle>
            </CardHeader>
            <CardContent className="p-0">
                <table className="w-full border-collapse font-body text-sm">
                    <thead>
                        <tr className="border-b border-line text-left font-ui text-xs uppercase tracking-wide text-text-faint">
                            <th className="px-4 py-3 font-medium">Business owner</th>
                            <th className="px-4 py-3 font-medium">Calls</th>
                            <th className="px-4 py-3 font-medium">Tokens</th>
                            <th className="px-4 py-3 font-medium">Cost</th>
                            <th className="px-4 py-3 font-medium">% of cap</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr>
                                <td colSpan={5} className="px-4 py-8 text-center text-text-faint">
                                    No calls in this period.
                                </td>
                            </tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.business_owner_id} className="border-b border-line last:border-0">
                                <td className="px-4 py-3">
                                    <p className="font-ui font-medium text-text">{row.name ?? '—'}</p>
                                    {row.company && <p className="font-body text-xs text-text-faint">{row.company}</p>}
                                </td>
                                <td className="px-4 py-3 tabular-nums text-text-muted">{row.calls}</td>
                                <td className="px-4 py-3 tabular-nums text-text-muted">
                                    {(row.input_tokens + row.output_tokens).toLocaleString()}
                                </td>
                                <td className="px-4 py-3 tabular-nums text-text-muted">{money(row.cost)}</td>
                                <td className="px-4 py-3 tabular-nums">
                                    {row.pct_of_cap !== null ? (
                                        <Badge variant={row.pct_of_cap >= 80 ? 'red' : 'muted'}>{row.pct_of_cap}%</Badge>
                                    ) : (
                                        <span className="text-text-faint">—</span>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </CardContent>
        </Card>
    );
}

function RowsTable({ paginated }: { paginated: Paginated<UsageRow> }) {
    const goToPage = (page: number) => {
        router.get(route('admin.ai-usage.index'), { page }, { preserveState: true, preserveScroll: true });
    };

    return (
        <Card className="mt-6">
            <CardHeader>
                <CardTitle className="text-base">Call log</CardTitle>
            </CardHeader>
            <CardContent className="p-0">
                <table className="w-full border-collapse font-body text-sm">
                    <thead>
                        <tr className="border-b border-line text-left font-ui text-xs uppercase tracking-wide text-text-faint">
                            <th className="px-4 py-3 font-medium">When</th>
                            <th className="px-4 py-3 font-medium">Tool</th>
                            <th className="px-4 py-3 font-medium">BO</th>
                            <th className="px-4 py-3 font-medium">Model</th>
                            <th className="px-4 py-3 font-medium">Tokens</th>
                            <th className="px-4 py-3 font-medium">Cost</th>
                            <th className="px-4 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {paginated.data.map((row) => (
                            <tr key={row.id} className="border-b border-line last:border-0">
                                <td className="px-4 py-3 text-text-muted">{new Date(row.created_at).toLocaleString()}</td>
                                <td className="px-4 py-3 text-text">{row.tool}</td>
                                <td className="px-4 py-3 text-text-muted">{row.business_owner ?? '—'}</td>
                                <td className="px-4 py-3 text-text-muted">{row.model}</td>
                                <td className="px-4 py-3 tabular-nums text-text-muted">
                                    {(row.input_tokens + row.output_tokens).toLocaleString()}
                                </td>
                                <td className="px-4 py-3 tabular-nums text-text-muted">
                                    {row.cost_estimate !== null ? money(row.cost_estimate) : '—'}
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex items-center gap-1.5">
                                        <Badge variant={STATUS_VARIANT[row.status] ?? 'default'}>{row.status}</Badge>
                                        {row.vendor_leak && <Badge variant="red">vendor leak</Badge>}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {paginated.last_page > 1 && (
                    <div className="flex items-center justify-between px-4 py-3">
                        <p className="font-body text-xs text-text-faint">
                            Page {paginated.current_page} of {paginated.last_page} · {paginated.total} calls
                        </p>
                        <div className="flex gap-2">
                            <Button
                                size="sm"
                                variant="secondary"
                                disabled={paginated.current_page <= 1}
                                onClick={() => goToPage(paginated.current_page - 1)}
                            >
                                Previous
                            </Button>
                            <Button
                                size="sm"
                                variant="secondary"
                                disabled={paginated.current_page >= paginated.last_page}
                                onClick={() => goToPage(paginated.current_page + 1)}
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

export default function AiUsageIndex({
    filters,
    summary,
    byTool,
    byBusinessOwner,
    rows,
    businessOwners,
    tools,
    globalBudget,
}: {
    filters: Filters;
    summary: Summary;
    byTool: ToolUsage[];
    byBusinessOwner: BoUsage[];
    rows: Paginated<UsageRow>;
    businessOwners: { id: number; name: string; company: string | null }[];
    tools: string[];
    globalBudget: GlobalBudget;
}) {
    return (
        <AdminLayout>
            <Head title="AI usage explorer" />

            <section className="animate-rise rounded-section border border-line-strong bg-surface-glass p-8 backdrop-blur-2xl">
                <p className="eyebrow text-accent">Admin</p>
                <h1 className="mt-2 font-display text-3xl font-semibold text-text">AI usage explorer</h1>
                <p className="mt-2 max-w-2xl font-body text-sm text-text-muted">
                    Token counts and cost estimates per period, business owner, and call type (§6.7), computed against
                    the pricing table in AI settings.
                </p>
            </section>

            <div className="mt-6">
                <FiltersBar filters={filters} businessOwners={businessOwners} tools={tools} />
            </div>

            <SummaryCards summary={summary} globalBudget={globalBudget} />
            <ByToolTable rows={byTool} />
            <ByBoTable rows={byBusinessOwner} />
            <RowsTable paginated={rows} />
        </AdminLayout>
    );
}
