import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus, SlidersHorizontal, Trash2 } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/Layouts/AdminLayout';

type BriefRow = {
    id: number;
    business_owner: { id: number; name: string; company: string } | null;
    phase: string;
    module: string | null;
    verdict: string;
    drop_reason: string | null;
    composite: number | null;
    label: string | null;
    paragraph_excerpt: string | null;
    created_at: string;
};

type RubricDimension = { key: string; label: string; description: string; weight: number };

type Rubric = { version: number; mode: string; threshold: number; dimensions: RubricDimension[] };

type Filters = {
    verdict?: string;
    label?: string;
    phase?: string;
    min_composite?: string;
    max_composite?: string;
};

const verdictBadge = (verdict: string) =>
    verdict === 'shown' ? <Badge variant="blue">Shown</Badge> : verdict === 'hidden_low_value' ? <Badge variant="muted">Hidden (low value)</Badge> : <Badge variant="muted">Dropped</Badge>;

/**
 * S5.7 advisory-brief review surface: every persisted brief — shown,
 * judge-hidden, gate-dropped — filterable by verdict/score/label, plus the
 * grading-rubric editor (dimensions/weights, threshold, log_only|enforce
 * mode). Labeling happens on the detail page, where the full context is
 * visible.
 */
export default function Index({
    briefs,
    filters,
    rubric,
}: {
    briefs: { data: BriefRow[]; current_page: number; last_page: number; prev_page_url: string | null; next_page_url: string | null; total: number };
    filters: Filters;
    rubric: Rubric;
}) {
    const applyFilter = (key: keyof Filters, value: string) => {
        router.get('/admin/advisory-briefs', { ...filters, [key]: value || undefined }, { preserveState: true, replace: true });
    };

    return (
        <AdminLayout>
            <Head title="Advisory briefs" />

            <section className="animate-rise rounded-section border border-line-strong bg-surface-glass p-8 backdrop-blur-2xl">
                <p className="eyebrow text-accent">Admin</p>
                <h1 className="mt-2 font-display text-3xl font-semibold text-text">Advisory briefs</h1>
                <p className="mt-2 max-w-2xl font-body text-sm text-text-muted">
                    Every &ldquo;note from the studio&rdquo; the pipeline produced — shown, hidden by the judge, or
                    dropped by the deterministic gate. Open one to see the exact context it was written from and
                    label it good/bad; those labels calibrate the rubric threshold and the exemplar library.
                </p>
            </section>

            <RubricEditor rubric={rubric} />

            <div className="mt-6 flex flex-wrap items-end gap-3 rounded-admin border border-line bg-surface p-4">
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor="filter-verdict">Verdict</Label>
                    <Select id="filter-verdict" value={filters.verdict ?? ''} onChange={(e) => applyFilter('verdict', e.target.value)}>
                        <option value="">All</option>
                        <option value="shown">Shown</option>
                        <option value="hidden_low_value">Hidden (low value)</option>
                        <option value="dropped">Dropped</option>
                    </Select>
                </div>
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor="filter-label">Label</Label>
                    <Select id="filter-label" value={filters.label ?? ''} onChange={(e) => applyFilter('label', e.target.value)}>
                        <option value="">All</option>
                        <option value="good">Actually good</option>
                        <option value="bad">Actually bad</option>
                        <option value="unlabeled">Unlabeled</option>
                    </Select>
                </div>
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor="filter-min">Min score</Label>
                    <Input
                        id="filter-min"
                        type="number"
                        step="0.1"
                        min={1}
                        max={5}
                        className="w-24"
                        defaultValue={filters.min_composite ?? ''}
                        onBlur={(e) => applyFilter('min_composite', e.target.value)}
                    />
                </div>
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor="filter-max">Max score</Label>
                    <Input
                        id="filter-max"
                        type="number"
                        step="0.1"
                        min={1}
                        max={5}
                        className="w-24"
                        defaultValue={filters.max_composite ?? ''}
                        onBlur={(e) => applyFilter('max_composite', e.target.value)}
                    />
                </div>
                <p className="ml-auto font-body text-xs text-text-faint">{briefs.total} briefs</p>
            </div>

            <div className="mt-4 flex flex-col gap-3">
                {briefs.data.length === 0 && (
                    <p className="rounded-admin border border-line bg-surface px-4 py-8 text-center font-body text-sm text-text-faint">
                        No briefs match these filters.
                    </p>
                )}
                {briefs.data.map((brief) => (
                    <Link
                        key={brief.id}
                        href={`/admin/advisory-briefs/${brief.id}`}
                        className="block rounded-admin border border-line bg-surface p-4 transition hover:border-line-strong"
                    >
                        <div className="flex flex-wrap items-center gap-2">
                            {verdictBadge(brief.verdict)}
                            {brief.composite !== null && <Badge variant="muted">score {brief.composite.toFixed(2)}</Badge>}
                            {brief.label === 'good' && <Badge variant="blue">actually good</Badge>}
                            {brief.label === 'bad' && <Badge variant="muted">actually bad</Badge>}
                            {brief.drop_reason && <span className="font-ui text-xs text-text-faint">({brief.drop_reason})</span>}
                            <span className="ml-auto font-ui text-xs text-text-faint">
                                {brief.phase}
                                {brief.module ? ` · ${brief.module}` : ''} · {brief.created_at}
                            </span>
                        </div>
                        <p className="mt-2 font-body text-sm text-text">
                            <span className="font-semibold">{brief.business_owner?.company ?? brief.business_owner?.name ?? '—'}</span>
                            {brief.paragraph_excerpt && <span className="text-text-muted"> — {brief.paragraph_excerpt}…</span>}
                            {!brief.paragraph_excerpt && <span className="italic text-text-faint"> — no brief payload</span>}
                        </p>
                    </Link>
                ))}
            </div>

            {(briefs.prev_page_url || briefs.next_page_url) && (
                <div className="mt-4 flex items-center justify-between">
                    <Button type="button" variant="secondary" size="sm" disabled={!briefs.prev_page_url} onClick={() => briefs.prev_page_url && router.get(briefs.prev_page_url)}>
                        Previous
                    </Button>
                    <span className="font-ui text-xs text-text-faint">
                        Page {briefs.current_page} of {briefs.last_page}
                    </span>
                    <Button type="button" variant="secondary" size="sm" disabled={!briefs.next_page_url} onClick={() => briefs.next_page_url && router.get(briefs.next_page_url)}>
                        Next
                    </Button>
                </div>
            )}
        </AdminLayout>
    );
}

function RubricEditor({ rubric }: { rubric: Rubric }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        mode: rubric.mode,
        threshold: String(rubric.threshold),
        dimensions: rubric.dimensions.map((dimension) => ({ ...dimension, weight: String(dimension.weight) })),
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.patch('/admin/advisory-briefs/rubric', { preserveScroll: true });
    };

    const setDimension = (index: number, key: keyof RubricDimension, value: string) => {
        form.setData(
            'dimensions',
            form.data.dimensions.map((dimension, i) => (i === index ? { ...dimension, [key]: value } : dimension)),
        );
    };

    return (
        <section className="mt-6 rounded-admin border border-line bg-surface p-5">
            <div className="flex items-center justify-between gap-3">
                <div>
                    <h2 className="font-ui text-sm font-semibold text-text">Grading rubric</h2>
                    <p className="mt-1 font-body text-xs text-text-muted">
                        v{rubric.version} · mode <span className="font-semibold">{rubric.mode === 'enforce' ? 'Enforce' : 'Log only'}</span> · threshold{' '}
                        {rubric.threshold} — {rubric.mode === 'enforce' ? 'briefs below the threshold are hidden.' : 'everything that passed the deterministic gate is revealed; scores are recorded for calibration.'}
                    </p>
                </div>
                <Button type="button" variant="secondary" size="sm" onClick={() => setOpen((v) => !v)} className="gap-1.5">
                    <SlidersHorizontal className="h-3.5 w-3.5" />
                    {open ? 'Close' : 'Edit rubric'}
                </Button>
            </div>

            {open && (
                <form onSubmit={submit} className="mt-4 flex flex-col gap-4 border-t border-line pt-4">
                    <div className="grid grid-cols-2 gap-4 sm:max-w-md">
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="rubric-mode">Mode</Label>
                            <Select id="rubric-mode" value={form.data.mode} onChange={(e) => form.setData('mode', e.target.value)}>
                                <option value="log_only">Log only</option>
                                <option value="enforce">Enforce</option>
                            </Select>
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="rubric-threshold">Threshold (1–5)</Label>
                            <Input
                                id="rubric-threshold"
                                type="number"
                                step="0.1"
                                min={1}
                                max={5}
                                value={form.data.threshold}
                                onChange={(e) => form.setData('threshold', e.target.value)}
                            />
                            {form.errors.threshold && <p className="font-body text-xs text-red">{form.errors.threshold}</p>}
                        </div>
                    </div>

                    <div className="flex flex-col gap-3">
                        {form.data.dimensions.map((dimension, index) => (
                            <div key={index} className="rounded-md border border-line p-3">
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_1fr_6rem_auto]">
                                    <div className="flex flex-col gap-1.5">
                                        <Label htmlFor={`dim-${index}-key`}>Key</Label>
                                        <Input id={`dim-${index}-key`} value={dimension.key} onChange={(e) => setDimension(index, 'key', e.target.value)} />
                                    </div>
                                    <div className="flex flex-col gap-1.5">
                                        <Label htmlFor={`dim-${index}-label`}>Label</Label>
                                        <Input id={`dim-${index}-label`} value={dimension.label} onChange={(e) => setDimension(index, 'label', e.target.value)} />
                                    </div>
                                    <div className="flex flex-col gap-1.5">
                                        <Label htmlFor={`dim-${index}-weight`}>Weight</Label>
                                        <Input
                                            id={`dim-${index}-weight`}
                                            type="number"
                                            step="0.05"
                                            min={0.01}
                                            max={1}
                                            value={dimension.weight}
                                            onChange={(e) => setDimension(index, 'weight', e.target.value)}
                                        />
                                    </div>
                                    <div className="flex items-end">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            disabled={form.data.dimensions.length <= 1}
                                            onClick={() => form.setData('dimensions', form.data.dimensions.filter((_, i) => i !== index))}
                                        >
                                            <Trash2 className="h-3.5 w-3.5" />
                                        </Button>
                                    </div>
                                </div>
                                <div className="mt-3 flex flex-col gap-1.5">
                                    <Label htmlFor={`dim-${index}-description`}>Description (the judge's standard for this dimension)</Label>
                                    <Textarea
                                        id={`dim-${index}-description`}
                                        rows={2}
                                        value={dimension.description}
                                        onChange={(e) => setDimension(index, 'description', e.target.value)}
                                    />
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="flex items-center gap-3">
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            className="gap-1.5"
                            onClick={() =>
                                form.setData('dimensions', [...form.data.dimensions, { key: '', label: '', description: '', weight: '0.1' }])
                            }
                        >
                            <Plus className="h-3.5 w-3.5" />
                            Add dimension
                        </Button>
                        <Button type="submit" size="sm" disabled={form.processing}>
                            Save rubric
                        </Button>
                        <p className="font-body text-xs text-text-faint">Changing dimensions bumps the rubric version; threshold/mode changes don't.</p>
                    </div>
                </form>
            )}
        </section>
    );
}
