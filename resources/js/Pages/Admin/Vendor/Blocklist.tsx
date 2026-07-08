import { Head, router, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/Layouts/AdminLayout';

type Term = {
    id: number;
    term: string;
    is_regex: boolean;
    replacement: string | null;
    active: boolean;
    category: string | null;
};

function TermFields({
    data,
    setData,
    errors,
    defaultReplacement,
    idPrefix,
}: {
    data: { term: string; is_regex: boolean; replacement: string; active: boolean; category: string };
    setData: (key: string, value: string | boolean) => void;
    errors: Partial<Record<string, string>>;
    defaultReplacement: string;
    idPrefix: string;
}) {
    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-col gap-1.5">
                <Label htmlFor={`${idPrefix}-term`}>Term or regex</Label>
                <Input
                    id={`${idPrefix}-term`}
                    value={data.term}
                    onChange={(e) => setData('term', e.target.value)}
                    placeholder="e.g. Calendly"
                    autoFocus
                />
                {errors.term && <p className="font-body text-xs text-red">{errors.term}</p>}
            </div>
            <div className="grid grid-cols-2 gap-4">
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor={`${idPrefix}-category`}>Category (optional)</Label>
                    <Input
                        id={`${idPrefix}-category`}
                        value={data.category}
                        onChange={(e) => setData('category', e.target.value)}
                        placeholder="booking, social…"
                    />
                </div>
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor={`${idPrefix}-replacement`}>Redaction label (optional)</Label>
                    <Input
                        id={`${idPrefix}-replacement`}
                        value={data.replacement}
                        onChange={(e) => setData('replacement', e.target.value)}
                        placeholder={defaultReplacement}
                    />
                </div>
            </div>
            <div className="flex items-center gap-6">
                <label className="flex items-center gap-2 font-body text-sm text-text-muted">
                    <input
                        type="checkbox"
                        checked={data.is_regex}
                        onChange={(e) => setData('is_regex', e.target.checked)}
                    />
                    Treat as regex
                </label>
                <label className="flex items-center gap-2 font-body text-sm text-text-muted">
                    <input
                        type="checkbox"
                        checked={data.active}
                        onChange={(e) => setData('active', e.target.checked)}
                    />
                    Active
                </label>
            </div>
        </div>
    );
}

function AddTermDialog({ defaultReplacement }: { defaultReplacement: string }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ term: '', is_regex: false, replacement: '', active: true, category: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('admin.vendor-blocklist.store'), {
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
                <Button size="sm">
                    <Plus className="h-3.5 w-3.5" />
                    Add term
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add blocklist term</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit}>
                    <TermFields
                        data={form.data}
                        setData={form.setData}
                        errors={form.errors}
                        defaultReplacement={defaultReplacement}
                        idPrefix="new"
                    />
                    <DialogFooter className="mt-6">
                        <Button type="submit" disabled={form.processing}>
                            Add
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EditTermDialog({ term, defaultReplacement }: { term: Term; defaultReplacement: string }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        term: term.term,
        is_regex: term.is_regex,
        replacement: term.replacement ?? '',
        active: term.active,
        category: term.category ?? '',
        _method: 'patch',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('admin.vendor-blocklist.update', term.id), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="secondary">
                    Edit
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit blocklist term</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit}>
                    <TermFields
                        data={form.data}
                        setData={form.setData}
                        errors={form.errors}
                        defaultReplacement={defaultReplacement}
                        idPrefix={`edit-${term.id}`}
                    />
                    <DialogFooter className="mt-6">
                        <Button type="submit" disabled={form.processing}>
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function DeleteTermButton({ term }: { term: Term }) {
    const remove = () => {
        if (confirm(`Remove "${term.term}" from the blocklist?`)) {
            router.delete(route('admin.vendor-blocklist.destroy', term.id), { preserveScroll: true });
        }
    };

    return (
        <Button size="sm" variant="ghost" onClick={remove} aria-label="Remove term">
            <Trash2 className="h-3.5 w-3.5 text-text-faint hover:text-red" />
        </Button>
    );
}

export default function VendorBlocklist({
    terms,
    defaultReplacement,
}: {
    terms: Term[];
    defaultReplacement: string;
}) {
    return (
        <AdminLayout>
            <Head title="Vendor blocklist" />

            <section className="animate-rise rounded-section border border-line-strong bg-surface-glass p-8 backdrop-blur-2xl">
                <p className="eyebrow text-accent">Admin</p>
                <h1 className="mt-2 font-display text-3xl font-semibold text-text">Vendor blocklist</h1>
                <p className="mt-2 max-w-2xl font-body text-sm text-text-muted">
                    Brand and product names the AI output filter scans for (§7.6). On a hit the model is asked to
                    rewrite once; a repeat hit is redacted to the generic label and flagged for review. Regex rows use{' '}
                    <code className="text-text">/…/i</code> — escape any literal slash.
                </p>
            </section>

            <div className="mt-6 flex items-center justify-between">
                <h2 className="font-display text-xl font-semibold text-text">
                    Terms <span className="font-body text-sm text-text-faint">({terms.length})</span>
                </h2>
                <AddTermDialog defaultReplacement={defaultReplacement} />
            </div>

            <div className="mt-4 overflow-hidden rounded-admin border border-line bg-surface">
                <table className="w-full border-collapse font-body text-sm">
                    <thead>
                        <tr className="border-b border-line text-left font-ui text-xs uppercase tracking-wide text-text-faint">
                            <th className="px-4 py-3 font-medium">Term</th>
                            <th className="px-4 py-3 font-medium">Category</th>
                            <th className="px-4 py-3 font-medium">Redacts to</th>
                            <th className="px-4 py-3 font-medium">Status</th>
                            <th className="px-4 py-3 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {terms.length === 0 && (
                            <tr>
                                <td colSpan={5} className="px-4 py-8 text-center text-text-faint">
                                    No blocklist terms yet.
                                </td>
                            </tr>
                        )}
                        {terms.map((term) => (
                            <tr key={term.id} className="border-b border-line last:border-0 hover:bg-surface-2">
                                <td className="px-4 py-3">
                                    <span className="font-ui font-medium text-text">{term.term}</span>
                                    {term.is_regex && (
                                        <Badge variant="accent" className="ml-2">
                                            regex
                                        </Badge>
                                    )}
                                </td>
                                <td className="px-4 py-3 text-text-muted">{term.category ?? '—'}</td>
                                <td className="px-4 py-3 text-text-muted">
                                    {term.replacement ?? (
                                        <span className="text-text-faint italic">{defaultReplacement}</span>
                                    )}
                                </td>
                                <td className="px-4 py-3">
                                    {term.active ? (
                                        <Badge variant="blue">Active</Badge>
                                    ) : (
                                        <Badge variant="muted">Off</Badge>
                                    )}
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex items-center justify-end gap-1">
                                        <EditTermDialog term={term} defaultReplacement={defaultReplacement} />
                                        <DeleteTermButton term={term} />
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
