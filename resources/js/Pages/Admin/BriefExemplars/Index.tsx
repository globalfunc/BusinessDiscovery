import { Head, useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/Layouts/AdminLayout';

type Exemplar = {
    id: number;
    context_tags: string[];
    dcp_excerpt: string;
    exemplar_brief: { paragraph?: string; bullets?: string[] };
    quality_notes: string | null;
    active: boolean;
    version: number;
};

type ExemplarFormData = {
    context_tags: string[];
    dcp_excerpt: string;
    paragraph: string;
    bullets: string[];
    quality_notes: string;
    active: boolean;
};

function ExemplarFields({
    data,
    setData,
    errors,
    idPrefix,
}: {
    data: ExemplarFormData & { tagsText: string; bulletsText: string };
    setData: (key: string, value: string | boolean | string[]) => void;
    errors: Partial<Record<string, string>>;
    idPrefix: string;
}) {
    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-col gap-1.5">
                <Label htmlFor={`${idPrefix}-tags`}>Context tags (comma-separated)</Label>
                <Input
                    id={`${idPrefix}-tags`}
                    value={data.tagsText}
                    onChange={(e) => setData('tagsText', e.target.value)}
                    placeholder="e.g. restaurant, local, instagram"
                />
                {errors.context_tags && <p className="font-body text-xs text-red">{errors.context_tags}</p>}
            </div>
            <div className="flex flex-col gap-1.5">
                <Label htmlFor={`${idPrefix}-excerpt`}>Context (DCP-style excerpt)</Label>
                <Textarea
                    id={`${idPrefix}-excerpt`}
                    rows={4}
                    value={data.dcp_excerpt}
                    onChange={(e) => setData('dcp_excerpt', e.target.value)}
                />
                {errors.dcp_excerpt && <p className="font-body text-xs text-red">{errors.dcp_excerpt}</p>}
            </div>
            <div className="flex flex-col gap-1.5">
                <Label htmlFor={`${idPrefix}-paragraph`}>Exemplar brief — paragraph</Label>
                <Textarea
                    id={`${idPrefix}-paragraph`}
                    rows={3}
                    value={data.paragraph}
                    onChange={(e) => setData('paragraph', e.target.value)}
                />
                {errors.paragraph && <p className="font-body text-xs text-red">{errors.paragraph}</p>}
            </div>
            <div className="flex flex-col gap-1.5">
                <Label htmlFor={`${idPrefix}-bullets`}>Bullets (one per line, max 4)</Label>
                <Textarea
                    id={`${idPrefix}-bullets`}
                    rows={4}
                    value={data.bulletsText}
                    onChange={(e) => setData('bulletsText', e.target.value)}
                />
                {errors.bullets && <p className="font-body text-xs text-red">{errors.bullets}</p>}
            </div>
            <div className="flex flex-col gap-1.5">
                <Label htmlFor={`${idPrefix}-notes`}>Quality notes (why this pair is gold)</Label>
                <Textarea
                    id={`${idPrefix}-notes`}
                    rows={2}
                    value={data.quality_notes}
                    onChange={(e) => setData('quality_notes', e.target.value)}
                />
            </div>
            <label className="flex items-center gap-2 font-body text-sm text-text">
                <input type="checkbox" checked={data.active} onChange={(e) => setData('active', e.target.checked)} />
                Active (injected into generation calls)
            </label>
        </div>
    );
}

const splitTags = (text: string) =>
    text
        .split(',')
        .map((tag) => tag.trim())
        .filter(Boolean);

const splitBullets = (text: string) =>
    text
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean);

function AddExemplarDialog() {
    const [open, setOpen] = useState(false);
    const form = useForm({
        tagsText: '',
        dcp_excerpt: '',
        paragraph: '',
        bulletsText: '',
        quality_notes: '',
        active: true,
        context_tags: [] as string[],
        bullets: [] as string[],
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            context_tags: splitTags(data.tagsText),
            dcp_excerpt: data.dcp_excerpt,
            paragraph: data.paragraph,
            bullets: splitBullets(data.bulletsText),
            quality_notes: data.quality_notes || null,
            active: data.active,
        }));
        form.post('/admin/brief-exemplars', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button type="button" size="sm" className="gap-1.5">
                    <Plus className="h-3.5 w-3.5" />
                    Add exemplar
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-y-auto">
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>New exemplar</DialogTitle>
                    </DialogHeader>
                    <div className="mt-4">
                        <ExemplarFields data={form.data} setData={form.setData} errors={form.errors} idPrefix="add" />
                    </div>
                    <DialogFooter className="mt-6">
                        <Button type="submit" size="sm" disabled={form.processing}>
                            Save exemplar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EditExemplarDialog({ exemplar }: { exemplar: Exemplar }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        tagsText: exemplar.context_tags.join(', '),
        dcp_excerpt: exemplar.dcp_excerpt,
        paragraph: exemplar.exemplar_brief.paragraph ?? '',
        bulletsText: (exemplar.exemplar_brief.bullets ?? []).join('\n'),
        quality_notes: exemplar.quality_notes ?? '',
        active: exemplar.active,
        context_tags: [] as string[],
        bullets: [] as string[],
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            context_tags: splitTags(data.tagsText),
            dcp_excerpt: data.dcp_excerpt,
            paragraph: data.paragraph,
            bullets: splitBullets(data.bulletsText),
            quality_notes: data.quality_notes || null,
            active: data.active,
        }));
        form.patch(`/admin/brief-exemplars/${exemplar.id}`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button type="button" variant="ghost" size="sm" className="gap-1.5 text-text-muted">
                    <Pencil className="h-3.5 w-3.5" />
                    Edit
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-y-auto">
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>Edit exemplar #{exemplar.id} (v{exemplar.version})</DialogTitle>
                    </DialogHeader>
                    <div className="mt-4">
                        <ExemplarFields data={form.data} setData={form.setData} errors={form.errors} idPrefix={`edit-${exemplar.id}`} />
                    </div>
                    <DialogFooter className="mt-6">
                        <Button type="submit" size="sm" disabled={form.processing}>
                            Save changes
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/**
 * The advisory-brief exemplar library (S5.6) with the S5.7 editor — the
 * hand-written gold pairs injected as context into the content/social and
 * growth suggestion calls, and the primary quality lever for briefs.
 * Rewriting content bumps the exemplar's version (persisted briefs reference
 * id+version); retiring one is the Active toggle, never deletion.
 */
export default function Index({ exemplars }: { exemplars: Exemplar[] }) {
    return (
        <AdminLayout>
            <Head title="Brief exemplars" />

            <section className="animate-rise rounded-section border border-line-strong bg-surface-glass p-8 backdrop-blur-2xl">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <p className="eyebrow text-accent">Admin</p>
                        <h1 className="mt-2 font-display text-3xl font-semibold text-text">Brief exemplars</h1>
                        <p className="mt-2 max-w-2xl font-body text-sm text-text-muted">
                            Hand-written gold pairs (context → advisory brief) the AI sees when writing a &ldquo;note
                            from the studio&rdquo; for the content and growth phases. The most relevant pairs are
                            selected per call by tag match. These are the primary quality lever — calibrate them
                            against the labeled briefs in the review screen.
                        </p>
                    </div>
                    <AddExemplarDialog />
                </div>
            </section>

            <div className="mt-6 flex flex-col gap-4">
                {exemplars.length === 0 && (
                    <p className="rounded-admin border border-line bg-surface px-4 py-8 text-center font-body text-sm text-text-faint">
                        No exemplars yet — run the BriefExemplarSeeder or add one.
                    </p>
                )}
                {exemplars.map((exemplar) => (
                    <article key={exemplar.id} className="rounded-admin border border-line bg-surface p-5">
                        <div className="flex items-start justify-between gap-3">
                            <div className="flex flex-wrap items-center gap-1.5">
                                {exemplar.context_tags.map((tag) => (
                                    <Badge key={tag} variant="muted">
                                        {tag}
                                    </Badge>
                                ))}
                            </div>
                            <div className="flex shrink-0 items-center gap-2">
                                <span className="font-ui text-xs text-text-faint">v{exemplar.version}</span>
                                {exemplar.active ? <Badge variant="blue">Active</Badge> : <Badge variant="muted">Off</Badge>}
                                <EditExemplarDialog exemplar={exemplar} />
                            </div>
                        </div>

                        <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                            <div>
                                <h2 className="font-ui text-xs font-semibold uppercase tracking-wide text-text-faint">
                                    Context (DCP excerpt)
                                </h2>
                                <p className="mt-2 whitespace-pre-line font-body text-sm text-text-muted">
                                    {exemplar.dcp_excerpt}
                                </p>
                            </div>
                            <div>
                                <h2 className="font-ui text-xs font-semibold uppercase tracking-wide text-text-faint">
                                    Exemplar brief
                                </h2>
                                <p className="mt-2 font-body text-sm text-text">{exemplar.exemplar_brief.paragraph}</p>
                                {(exemplar.exemplar_brief.bullets ?? []).length > 0 && (
                                    <ul className="mt-2 flex flex-col gap-1 font-body text-sm text-text">
                                        {exemplar.exemplar_brief.bullets!.map((bullet, i) => (
                                            <li key={i} className="flex gap-2">
                                                <span aria-hidden className="text-accent">
                                                    •
                                                </span>
                                                <span>{bullet}</span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        </div>

                        {exemplar.quality_notes && (
                            <p className="mt-4 border-t border-line pt-3 font-body text-xs italic text-text-faint">
                                {exemplar.quality_notes}
                            </p>
                        )}
                    </article>
                ))}
            </div>
        </AdminLayout>
    );
}
