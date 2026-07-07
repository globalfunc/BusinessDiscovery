import { Head, router } from '@inertiajs/react';
import { GripVertical } from 'lucide-react';
import { useMemo, useState, type DragEvent } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/Layouts/AdminLayout';

type LocalizedName = { en: string; bg: string };

type Stage = { value: string; label: string };

type Category = { id: number; name: LocalizedName };

type Niche = { id: number; name: LocalizedName; taxonomy_category_id: number };

type LeadCard = {
    id: number;
    name: string;
    company: string;
    current_stage: string;
    niche: { id: number; name: LocalizedName; category_name: LocalizedName | null } | null;
    note: string | null;
    created_at: string | null;
};

type Filters = {
    taxonomy_category_id?: number | string | null;
    taxonomy_niche_id?: number | string | null;
    date_from?: string | null;
    date_to?: string | null;
};

export default function PipelineIndex({
    businessOwners,
    categories,
    niches,
    stages,
    filters,
}: {
    businessOwners: LeadCard[];
    categories: Category[];
    niches: Niche[];
    stages: Stage[];
    filters: Filters;
}) {
    const [categoryId, setCategoryId] = useState(filters.taxonomy_category_id?.toString() ?? '');
    const [nicheId, setNicheId] = useState(filters.taxonomy_niche_id?.toString() ?? '');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [dragBoId, setDragBoId] = useState<number | null>(null);
    const [pendingMove, setPendingMove] = useState<{ bo: LeadCard; stage: string } | null>(null);
    const [note, setNote] = useState('');

    const visibleNiches = useMemo(
        () => (categoryId ? niches.filter((n) => n.taxonomy_category_id === Number(categoryId)) : niches),
        [niches, categoryId],
    );

    const applyFilters = (overrides: Partial<Filters> = {}) => {
        const params: Record<string, string> = {};
        const next = {
            taxonomy_category_id: categoryId,
            taxonomy_niche_id: nicheId,
            date_from: dateFrom,
            date_to: dateTo,
            ...overrides,
        };
        if (next.taxonomy_category_id) params.taxonomy_category_id = String(next.taxonomy_category_id);
        if (next.taxonomy_niche_id) params.taxonomy_niche_id = String(next.taxonomy_niche_id);
        if (next.date_from) params.date_from = String(next.date_from);
        if (next.date_to) params.date_to = String(next.date_to);

        router.get(route('admin.pipeline.index'), params, { preserveState: true, preserveScroll: true });
    };

    const columns = useMemo(
        () =>
            stages.map((stage) => ({
                stage,
                cards: businessOwners.filter((bo) => bo.current_stage === stage.value),
            })),
        [stages, businessOwners],
    );

    const handleDragStart = (e: DragEvent<HTMLDivElement>, boId: number) => {
        e.dataTransfer.setData('text/plain', String(boId));
        setDragBoId(boId);
    };

    const handleDrop = (e: DragEvent<HTMLDivElement>, stageValue: string) => {
        e.preventDefault();
        const boId = Number(e.dataTransfer.getData('text/plain'));
        const bo = businessOwners.find((b) => b.id === boId);
        setDragBoId(null);
        if (!bo || bo.current_stage === stageValue) return;
        setNote('');
        setPendingMove({ bo, stage: stageValue });
    };

    const confirmMove = () => {
        if (!pendingMove) return;
        router.patch(
            route('admin.pipeline.update-stage', pendingMove.bo.id),
            { stage: pendingMove.stage, note: note || null },
            { preserveScroll: true, onSuccess: () => setPendingMove(null) },
        );
    };

    return (
        <AdminLayout>
            <Head title="Pipeline" />

            <section className="animate-rise rounded-section border border-line-strong bg-surface-glass p-8 backdrop-blur-2xl">
                <p className="eyebrow text-accent">Admin</p>
                <h1 className="mt-2 font-display text-3xl font-semibold text-text">Pipeline</h1>
                <p className="mt-2 max-w-xl font-body text-sm text-text-muted">
                    Drag a lead card to a new stage. Each move is logged with a note and timestamp.
                </p>

                <div className="mt-6 flex flex-wrap items-end gap-4">
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="filter-category">Category</Label>
                        <Select
                            id="filter-category"
                            className="w-56"
                            value={categoryId}
                            onChange={(e) => {
                                setCategoryId(e.target.value);
                                setNicheId('');
                                applyFilters({ taxonomy_category_id: e.target.value, taxonomy_niche_id: '' });
                            }}
                        >
                            <option value="">All categories</option>
                            {categories.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name.en}
                                </option>
                            ))}
                        </Select>
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="filter-niche">Niche</Label>
                        <Select
                            id="filter-niche"
                            className="w-56"
                            value={nicheId}
                            onChange={(e) => {
                                setNicheId(e.target.value);
                                applyFilters({ taxonomy_niche_id: e.target.value });
                            }}
                        >
                            <option value="">All niches</option>
                            {visibleNiches.map((n) => (
                                <option key={n.id} value={n.id}>
                                    {n.name.en}
                                </option>
                            ))}
                        </Select>
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="filter-date-from">Created from</Label>
                        <Input
                            id="filter-date-from"
                            type="date"
                            className="w-40"
                            value={dateFrom}
                            onChange={(e) => {
                                setDateFrom(e.target.value);
                                applyFilters({ date_from: e.target.value });
                            }}
                        />
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="filter-date-to">Created to</Label>
                        <Input
                            id="filter-date-to"
                            type="date"
                            className="w-40"
                            value={dateTo}
                            onChange={(e) => {
                                setDateTo(e.target.value);
                                applyFilters({ date_to: e.target.value });
                            }}
                        />
                    </div>

                    {(categoryId || nicheId || dateFrom || dateTo) && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                setCategoryId('');
                                setNicheId('');
                                setDateFrom('');
                                setDateTo('');
                                router.get(route('admin.pipeline.index'), {}, { preserveState: true, preserveScroll: true });
                            }}
                        >
                            Clear filters
                        </Button>
                    )}
                </div>
            </section>

            <div className="mt-6 flex gap-4 overflow-x-auto pb-4">
                {columns.map(({ stage, cards }) => (
                    <div
                        key={stage.value}
                        onDragOver={(e) => e.preventDefault()}
                        onDrop={(e) => handleDrop(e, stage.value)}
                        className="flex w-72 shrink-0 flex-col rounded-admin border border-line bg-surface p-3"
                    >
                        <div className="mb-3 flex items-center justify-between px-1">
                            <h2 className="font-ui text-sm font-semibold text-text">{stage.label}</h2>
                            <span className="font-body text-xs tabular-nums text-text-faint">{cards.length}</span>
                        </div>

                        <div className="flex min-h-16 flex-col gap-3">
                            {cards.map((bo) => (
                                <div
                                    key={bo.id}
                                    draggable
                                    onDragStart={(e) => handleDragStart(e, bo.id)}
                                    onDragEnd={() => setDragBoId(null)}
                                    className={`rounded-md border border-line-strong bg-surface-2 p-3 transition-opacity ${
                                        dragBoId === bo.id ? 'opacity-40' : 'opacity-100'
                                    }`}
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <p className="font-ui text-sm font-medium text-text">{bo.name}</p>
                                            <p className="font-body text-xs text-text-faint">{bo.company}</p>
                                        </div>
                                        <GripVertical className="mt-0.5 h-4 w-4 shrink-0 cursor-grab text-text-faint" />
                                    </div>

                                    {bo.niche && (
                                        <Badge variant="blue" className="mt-2">
                                            {bo.niche.category_name ? `${bo.niche.category_name.en} — ` : ''}
                                            {bo.niche.name.en}
                                        </Badge>
                                    )}

                                    <p className="mt-2 line-clamp-2 font-body text-xs text-text-muted">
                                        {bo.note || 'No notes yet.'}
                                    </p>

                                    <div className="mt-3 flex flex-wrap gap-1">
                                        {stages.map((s) => (
                                            <span
                                                key={s.value}
                                                className={`h-1.5 flex-1 rounded-full ${
                                                    s.value === bo.current_stage
                                                        ? 'bg-accent'
                                                        : stages.findIndex((x) => x.value === s.value) <
                                                            stages.findIndex((x) => x.value === bo.current_stage)
                                                          ? 'bg-line-strong'
                                                          : 'bg-line'
                                                }`}
                                                title={s.label}
                                            />
                                        ))}
                                    </div>
                                </div>
                            ))}

                            {cards.length === 0 && (
                                <p className="px-1 font-body text-xs text-text-faint">Drop a lead here.</p>
                            )}
                        </div>
                    </div>
                ))}
            </div>

            <Dialog open={pendingMove !== null} onOpenChange={(open) => !open && setPendingMove(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Move {pendingMove?.bo.name} to{' '}
                            {stages.find((s) => s.value === pendingMove?.stage)?.label}
                        </DialogTitle>
                    </DialogHeader>

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="move-note">Note (optional)</Label>
                        <Textarea
                            id="move-note"
                            placeholder="What changed?"
                            value={note}
                            onChange={(e) => setNote(e.target.value)}
                            autoFocus
                        />
                    </div>

                    <DialogFooter>
                        <Button variant="secondary" onClick={() => setPendingMove(null)}>
                            Cancel
                        </Button>
                        <Button onClick={confirmMove}>Confirm move</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}
