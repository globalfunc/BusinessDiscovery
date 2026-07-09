import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useMemo, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select } from '@/components/ui/select';
import { SpecMarkdownRenderer } from '@/components/spec/SpecMarkdownRenderer';
import { formatAmountRangeInCurrency } from '@/lib/currency';
import { lineDiff } from '@/lib/lineDiff';
import { cn } from '@/lib/utils';
import AdminLayout from '@/Layouts/AdminLayout';

type SpecVersion = {
    id: number;
    version: number;
    markdown: string;
    generated_by: 'ai' | 'fallback';
    change_summary: string | null;
    created_at: string | null;
};

type SelectedServiceView = {
    id: number;
    name: string | null;
    description: string | null;
    features: string[];
    priority: boolean;
    custom: boolean;
    origin: string | null;
    price_min: number | null;
    price_max: number | null;
};

type DecisionSurface = {
    services: SelectedServiceView[];
    billing: {
        billing_model: string | null;
        budget_min: number | null;
        budget_max: number | null;
        timeline_choice: string | null;
        timeline_note: string | null;
    };
    branding: {
        style_chips: string[];
        color_preset: string | null;
        color_custom_hex: string | null;
        accepted_directions: { title: string | null; summary: string | null; features: string[]; note: string | null }[];
    };
} | null;

const BILLING_LABELS: Record<string, string> = {
    one_time: 'One-time build',
    build_support: 'Build + monthly support',
    saas_subscription: 'Service subscription',
    advise_me: 'Not sure — advise me',
};

const TIMELINE_LABELS: Record<string, string> = {
    asap: 'ASAP',
    one_month: 'Within 1 month',
    one_to_three_months: '1–3 months',
    flexible: 'Flexible',
};

function formatDate(value: string | null) {
    return value ? new Date(value).toLocaleString() : '—';
}

function DiffView({ before, after }: { before: string; after: string }) {
    const diff = useMemo(() => lineDiff(before, after), [before, after]);

    return (
        <div className="flex flex-col gap-0.5 overflow-x-auto rounded-md border border-line bg-surface-2 p-4 font-mono text-xs">
            {diff.map((line, index) => (
                <div
                    key={index}
                    className={cn(
                        'whitespace-pre-wrap px-2 py-0.5',
                        line.type === 'added' && 'bg-teal/10 text-teal',
                        line.type === 'removed' && 'bg-red/10 text-red line-through',
                        line.type === 'unchanged' && 'text-text-faint',
                    )}
                >
                    {line.type === 'added' ? '+ ' : line.type === 'removed' ? '- ' : '  '}
                    {line.text || ' '}
                </div>
            ))}
        </div>
    );
}

export default function BusinessOwnerSpec({
    businessOwner,
    versions,
    decisionSurface,
}: {
    businessOwner: { id: number; name: string; company: string; language: string };
    versions: SpecVersion[];
    decisionSurface: DecisionSurface;
}) {
    const [activeVersionId, setActiveVersionId] = useState<number | null>(versions[0]?.id ?? null);
    const [showRaw, setShowRaw] = useState(false);
    const [diffMode, setDiffMode] = useState(false);
    const [compareFromId, setCompareFromId] = useState<number | null>(versions[1]?.id ?? versions[0]?.id ?? null);
    const [compareToId, setCompareToId] = useState<number | null>(versions[0]?.id ?? null);

    const activeVersion = versions.find((v) => v.id === activeVersionId) ?? null;
    const compareFrom = versions.find((v) => v.id === compareFromId) ?? null;
    const compareTo = versions.find((v) => v.id === compareToId) ?? null;

    return (
        <AdminLayout>
            <Head title={`${businessOwner.name} — Spec review`} />

            <Link
                href={route('admin.business-owners.show', businessOwner.id)}
                className="inline-flex items-center gap-1.5 font-ui text-sm text-text-muted hover:text-text"
            >
                <ArrowLeft className="h-4 w-4" />
                {businessOwner.name}
            </Link>

            <section className="animate-rise mt-4 rounded-section border border-line-strong bg-surface-glass p-8 backdrop-blur-2xl">
                <p className="eyebrow text-accent">Specification review</p>
                <h1 className="mt-1 font-display text-3xl font-semibold text-text">{businessOwner.name}</h1>
                <p className="font-body text-sm text-text-muted">{businessOwner.company}</p>
            </section>

            {versions.length === 0 ? (
                <Card className="mt-6">
                    <CardContent className="p-8 text-center">
                        <p className="font-body text-sm text-text-faint">No specification has been generated for this business owner yet.</p>
                    </CardContent>
                </Card>
            ) : (
                <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="flex flex-col gap-6 lg:col-span-2">
                        <Card>
                            <CardHeader className="flex-row flex-wrap items-center justify-between gap-3 space-y-0">
                                <CardTitle>{diffMode ? 'Version diff' : 'Specification'}</CardTitle>
                                <div className="flex flex-wrap items-center gap-2">
                                    {!diffMode && (
                                        <div className="flex overflow-hidden rounded-md border border-line-strong">
                                            <button
                                                type="button"
                                                onClick={() => setShowRaw(false)}
                                                className={cn(
                                                    'px-2.5 py-1 font-ui text-xs font-medium transition-colors',
                                                    !showRaw ? 'bg-accent text-[#241305]' : 'text-text-muted hover:text-text',
                                                )}
                                            >
                                                Rendered
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => setShowRaw(true)}
                                                className={cn(
                                                    'px-2.5 py-1 font-ui text-xs font-medium transition-colors',
                                                    showRaw ? 'bg-accent text-[#241305]' : 'text-text-muted hover:text-text',
                                                )}
                                            >
                                                Raw markdown
                                            </button>
                                        </div>
                                    )}
                                    <button
                                        type="button"
                                        onClick={() => setDiffMode((v) => !v)}
                                        className={cn(
                                            'rounded-md border px-2.5 py-1 font-ui text-xs font-medium transition-colors',
                                            diffMode
                                                ? 'border-accent/40 bg-accent/10 text-accent'
                                                : 'border-line-strong text-text-muted hover:text-text',
                                        )}
                                        disabled={versions.length < 2}
                                    >
                                        Compare versions
                                    </button>
                                </div>
                            </CardHeader>
                            <CardContent>
                                {diffMode ? (
                                    <div className="flex flex-col gap-4">
                                        <div className="flex flex-wrap items-center gap-3">
                                            <label className="flex items-center gap-2 font-body text-xs text-text-muted">
                                                From
                                                <Select
                                                    className="w-auto"
                                                    value={compareFromId ?? ''}
                                                    onChange={(e) => setCompareFromId(Number(e.target.value))}
                                                >
                                                    {versions.map((v) => (
                                                        <option key={v.id} value={v.id}>
                                                            v{v.version}
                                                        </option>
                                                    ))}
                                                </Select>
                                            </label>
                                            <label className="flex items-center gap-2 font-body text-xs text-text-muted">
                                                To
                                                <Select
                                                    className="w-auto"
                                                    value={compareToId ?? ''}
                                                    onChange={(e) => setCompareToId(Number(e.target.value))}
                                                >
                                                    {versions.map((v) => (
                                                        <option key={v.id} value={v.id}>
                                                            v{v.version}
                                                        </option>
                                                    ))}
                                                </Select>
                                            </label>
                                        </div>
                                        {compareFrom && compareTo ? (
                                            <DiffView before={compareFrom.markdown} after={compareTo.markdown} />
                                        ) : (
                                            <p className="font-body text-sm text-text-faint">Pick two versions to compare.</p>
                                        )}
                                    </div>
                                ) : activeVersion ? (
                                    <div className="flex flex-col gap-4">
                                        <p className="font-ui text-xs text-text-faint">
                                            v{activeVersion.version} · {activeVersion.generated_by}
                                            {activeVersion.change_summary && <> — {activeVersion.change_summary}</>} ·{' '}
                                            {formatDate(activeVersion.created_at)}
                                        </p>
                                        {showRaw ? (
                                            <pre className="overflow-x-auto whitespace-pre-wrap rounded-md border border-line bg-surface-2 p-4 font-mono text-xs text-text">
                                                {activeVersion.markdown}
                                            </pre>
                                        ) : (
                                            <div className="rounded-md border border-line bg-surface-2 p-4">
                                                <SpecMarkdownRenderer markdown={activeVersion.markdown} />
                                            </div>
                                        )}
                                    </div>
                                ) : null}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Versions</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ul className="flex flex-col gap-2">
                                    {versions.map((v) => (
                                        <li key={v.id}>
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    setActiveVersionId(v.id);
                                                    setDiffMode(false);
                                                }}
                                                className={cn(
                                                    'flex w-full flex-wrap items-center justify-between gap-2 rounded-md border px-3 py-2 text-left transition-colors',
                                                    !diffMode && activeVersionId === v.id
                                                        ? 'border-accent/40 bg-accent/5'
                                                        : 'border-line bg-surface-2 hover:border-line-strong',
                                                )}
                                            >
                                                <div className="flex items-center gap-2">
                                                    <Badge variant={v.generated_by === 'ai' ? 'blue' : 'muted'}>v{v.version}</Badge>
                                                    <span className="font-body text-xs text-text-muted">{v.generated_by}</span>
                                                    {v.change_summary && (
                                                        <span className="font-body text-xs text-text-faint">— {v.change_summary}</span>
                                                    )}
                                                </div>
                                                <span className="font-body text-xs text-text-faint">{formatDate(v.created_at)}</span>
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="flex flex-col gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Selected services</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {!decisionSurface || decisionSurface.services.length === 0 ? (
                                    <p className="font-body text-sm text-text-faint">No services selected yet.</p>
                                ) : (
                                    <div className="flex flex-col gap-3">
                                        {decisionSurface.services.map((service) => (
                                            <div key={service.id} className="rounded-md border border-line bg-surface-2 p-3">
                                                <div className="flex items-center justify-between gap-2">
                                                    <p className="font-ui text-sm font-medium text-text">{service.name}</p>
                                                    {service.priority && <Badge variant="accent">Priority</Badge>}
                                                </div>
                                                {service.description && (
                                                    <p className="mt-1 font-body text-xs text-text-muted">{service.description}</p>
                                                )}
                                                {service.features.length > 0 && (
                                                    <ul className="mt-2 flex flex-col gap-0.5">
                                                        {service.features.map((feature, i) => (
                                                            <li key={i} className="font-body text-xs text-text-faint">
                                                                • {feature}
                                                            </li>
                                                        ))}
                                                    </ul>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Billing, budget &amp; timeline</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {!decisionSurface || decisionSurface.billing.billing_model === null ? (
                                    <p className="font-body text-sm text-text-faint">Not decided yet.</p>
                                ) : (
                                    <dl className="flex flex-col gap-2 font-body text-sm">
                                        <div>
                                            <dt className="font-ui text-xs text-text-faint">Billing model</dt>
                                            <dd className="text-text">
                                                {BILLING_LABELS[decisionSurface.billing.billing_model] ?? decisionSurface.billing.billing_model}
                                            </dd>
                                        </div>
                                        {decisionSurface.billing.budget_min !== null && decisionSurface.billing.budget_max !== null && (
                                            <div>
                                                <dt className="font-ui text-xs text-text-faint">Budget</dt>
                                                <dd className="text-text">
                                                    {formatAmountRangeInCurrency(
                                                        decisionSurface.billing.budget_min,
                                                        decisionSurface.billing.budget_max,
                                                        'EUR',
                                                    )}
                                                </dd>
                                            </div>
                                        )}
                                        {decisionSurface.billing.timeline_choice && (
                                            <div>
                                                <dt className="font-ui text-xs text-text-faint">Timeline</dt>
                                                <dd className="text-text">
                                                    {TIMELINE_LABELS[decisionSurface.billing.timeline_choice] ?? decisionSurface.billing.timeline_choice}
                                                </dd>
                                            </div>
                                        )}
                                        {decisionSurface.billing.timeline_note && (
                                            <div>
                                                <dt className="font-ui text-xs text-text-faint">Timeline note</dt>
                                                <dd className="whitespace-pre-wrap text-text-muted">{decisionSurface.billing.timeline_note}</dd>
                                            </div>
                                        )}
                                    </dl>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Branding directions</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {!decisionSurface ||
                                (decisionSurface.branding.style_chips.length === 0 &&
                                    !decisionSurface.branding.color_preset &&
                                    !decisionSurface.branding.color_custom_hex &&
                                    decisionSurface.branding.accepted_directions.length === 0) ? (
                                    <p className="font-body text-sm text-text-faint">No branding preferences recorded yet.</p>
                                ) : (
                                    <div className="flex flex-col gap-3">
                                        {decisionSurface.branding.style_chips.length > 0 && (
                                            <div className="flex flex-wrap gap-1.5">
                                                {decisionSurface.branding.style_chips.map((chip) => (
                                                    <Badge key={chip} variant="muted">
                                                        {chip}
                                                    </Badge>
                                                ))}
                                            </div>
                                        )}
                                        {(decisionSurface.branding.color_preset || decisionSurface.branding.color_custom_hex) && (
                                            <p className="font-body text-xs text-text-muted">
                                                Color: {decisionSurface.branding.color_preset ?? decisionSurface.branding.color_custom_hex}
                                            </p>
                                        )}
                                        {decisionSurface.branding.accepted_directions.map((direction, i) => (
                                            <div key={i} className="rounded-md border border-line bg-surface-2 p-3">
                                                <p className="font-ui text-sm font-medium text-text">{direction.title}</p>
                                                {direction.summary && (
                                                    <p className="mt-1 font-body text-xs text-text-muted">{direction.summary}</p>
                                                )}
                                                {direction.note && (
                                                    <p className="mt-1 font-body text-xs text-text-faint">Note: {direction.note}</p>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
