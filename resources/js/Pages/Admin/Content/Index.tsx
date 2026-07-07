import { Head, router, useForm } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/Layouts/AdminLayout';

type Niche = {
    id: number;
    name: { en: string; bg: string };
    sort: number;
    hidden: boolean;
};

type Category = {
    id: number;
    name: { en: string; bg: string };
    sort: number;
    hidden: boolean;
    niches: Niche[];
};

type ServiceRow = {
    id: number;
    key: string;
    name: { en: string; bg: string };
    one_liner: { en: string; bg: string };
    base_features: string[];
    saas_eligible: boolean;
    tags: string[] | null;
    price_min: number | null;
    price_max: number | null;
    hidden: boolean;
    niche_ids: number[];
    recommended_niche_ids: number[];
};

function ChipListEditor({
    label,
    values,
    onChange,
    placeholder,
}: {
    label: string;
    values: string[];
    onChange: (values: string[]) => void;
    placeholder?: string;
}) {
    const [draft, setDraft] = useState('');

    const add = () => {
        const trimmed = draft.trim();
        if (trimmed && !values.includes(trimmed)) {
            onChange([...values, trimmed]);
        }
        setDraft('');
    };

    return (
        <div className="flex flex-col gap-1.5">
            <Label>{label}</Label>
            <div className="flex flex-wrap gap-1.5">
                {values.map((value, index) => (
                    <span
                        key={`${value}-${index}`}
                        className="inline-flex items-center gap-1 rounded-full border border-line-strong bg-surface-2 px-2.5 py-1 font-body text-xs text-text"
                    >
                        {value}
                        <button
                            type="button"
                            onClick={() => onChange(values.filter((_, i) => i !== index))}
                            className="text-text-faint hover:text-red"
                        >
                            <X className="h-3 w-3" />
                        </button>
                    </span>
                ))}
            </div>
            <div className="flex gap-2">
                <Input
                    value={draft}
                    placeholder={placeholder}
                    onChange={(e) => setDraft(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            add();
                        }
                    }}
                />
                <Button type="button" variant="secondary" size="sm" onClick={add}>
                    Add
                </Button>
            </div>
        </div>
    );
}

function CategoryEditDialog({ category }: { category: Category }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        name_en: category.name.en,
        name_bg: category.name.bg,
        sort: category.sort,
        hidden: category.hidden,
        _method: 'patch',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('admin.content.taxonomy-categories.update', category.id), {
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
                    <DialogTitle>Edit category</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor={`cat-en-${category.id}`}>Name (EN)</Label>
                            <Input
                                id={`cat-en-${category.id}`}
                                value={form.data.name_en}
                                onChange={(e) => form.setData('name_en', e.target.value)}
                            />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor={`cat-bg-${category.id}`}>Name (BG)</Label>
                            <Input
                                id={`cat-bg-${category.id}`}
                                value={form.data.name_bg}
                                onChange={(e) => form.setData('name_bg', e.target.value)}
                            />
                        </div>
                    </div>
                    <label className="flex items-center gap-2 font-body text-sm text-text-muted">
                        <input
                            type="checkbox"
                            checked={form.data.hidden}
                            onChange={(e) => form.setData('hidden', e.target.checked)}
                        />
                        Hidden
                    </label>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function NicheEditDialog({ niche }: { niche: Niche }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        name_en: niche.name.en,
        name_bg: niche.name.bg,
        sort: niche.sort,
        hidden: niche.hidden,
        _method: 'patch',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('admin.content.taxonomy-niches.update', niche.id), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="ghost">
                    Edit
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit niche</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor={`niche-en-${niche.id}`}>Name (EN)</Label>
                            <Input
                                id={`niche-en-${niche.id}`}
                                value={form.data.name_en}
                                onChange={(e) => form.setData('name_en', e.target.value)}
                            />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor={`niche-bg-${niche.id}`}>Name (BG)</Label>
                            <Input
                                id={`niche-bg-${niche.id}`}
                                value={form.data.name_bg}
                                onChange={(e) => form.setData('name_bg', e.target.value)}
                            />
                        </div>
                    </div>
                    <label className="flex items-center gap-2 font-body text-sm text-text-muted">
                        <input
                            type="checkbox"
                            checked={form.data.hidden}
                            onChange={(e) => form.setData('hidden', e.target.checked)}
                        />
                        Hidden
                    </label>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function AddNicheDialog({ category }: { category: Category }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ name_en: '', name_bg: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('admin.content.taxonomy-niches.store', category.id), {
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
                <Button size="sm" variant="ghost">
                    <Plus className="h-3.5 w-3.5" />
                    Niche
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add niche to {category.name.en}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="new-niche-en">Name (EN)</Label>
                            <Input
                                id="new-niche-en"
                                value={form.data.name_en}
                                onChange={(e) => form.setData('name_en', e.target.value)}
                                autoFocus
                            />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="new-niche-bg">Name (BG)</Label>
                            <Input
                                id="new-niche-bg"
                                value={form.data.name_bg}
                                onChange={(e) => form.setData('name_bg', e.target.value)}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Add
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function AddCategoryDialog() {
    const [open, setOpen] = useState(false);
    const form = useForm({ name_en: '', name_bg: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('admin.content.taxonomy-categories.store'), {
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
                    Category
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add category</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="new-cat-en">Name (EN)</Label>
                            <Input
                                id="new-cat-en"
                                value={form.data.name_en}
                                onChange={(e) => form.setData('name_en', e.target.value)}
                                autoFocus
                            />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="new-cat-bg">Name (BG)</Label>
                            <Input
                                id="new-cat-bg"
                                value={form.data.name_bg}
                                onChange={(e) => form.setData('name_bg', e.target.value)}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Add
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

type ServiceFormData = {
    name_en: string;
    name_bg: string;
    one_liner_en: string;
    one_liner_bg: string;
    base_features: string[];
    tags: string[];
    saas_eligible: boolean;
    price_min: string;
    price_max: string;
    hidden: boolean;
    niche_ids: number[];
    recommended_niche_ids: number[];
};

function emptyServiceForm(): ServiceFormData {
    return {
        name_en: '',
        name_bg: '',
        one_liner_en: '',
        one_liner_bg: '',
        base_features: [],
        tags: [],
        saas_eligible: false,
        price_min: '',
        price_max: '',
        hidden: false,
        niche_ids: [],
        recommended_niche_ids: [],
    };
}

function ServiceFormFields({
    form,
    categories,
}: {
    form: ReturnType<typeof useForm<ServiceFormData>>;
    categories: Category[];
}) {
    const toggleNiche = (nicheId: number) => {
        const has = form.data.niche_ids.includes(nicheId);
        form.setData('niche_ids', has ? form.data.niche_ids.filter((id) => id !== nicheId) : [...form.data.niche_ids, nicheId]);
        if (has) {
            form.setData(
                'recommended_niche_ids',
                form.data.recommended_niche_ids.filter((id) => id !== nicheId),
            );
        }
    };

    const toggleRecommended = (nicheId: number) => {
        const has = form.data.recommended_niche_ids.includes(nicheId);
        form.setData(
            'recommended_niche_ids',
            has
                ? form.data.recommended_niche_ids.filter((id) => id !== nicheId)
                : [...form.data.recommended_niche_ids, nicheId],
        );
    };

    return (
        <div className="flex flex-col gap-4">
            <div className="grid grid-cols-2 gap-4">
                <div className="flex flex-col gap-1.5">
                    <Label>Name (EN)</Label>
                    <Input value={form.data.name_en} onChange={(e) => form.setData('name_en', e.target.value)} />
                </div>
                <div className="flex flex-col gap-1.5">
                    <Label>Name (BG)</Label>
                    <Input value={form.data.name_bg} onChange={(e) => form.setData('name_bg', e.target.value)} />
                </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
                <div className="flex flex-col gap-1.5">
                    <Label>One-liner (EN)</Label>
                    <Textarea
                        value={form.data.one_liner_en}
                        onChange={(e) => form.setData('one_liner_en', e.target.value)}
                    />
                </div>
                <div className="flex flex-col gap-1.5">
                    <Label>One-liner (BG)</Label>
                    <Textarea
                        value={form.data.one_liner_bg}
                        onChange={(e) => form.setData('one_liner_bg', e.target.value)}
                    />
                </div>
            </div>

            <ChipListEditor
                label="Feature list"
                values={form.data.base_features}
                onChange={(values) => form.setData('base_features', values)}
                placeholder="Type a feature and press Enter"
            />

            <ChipListEditor
                label="Tags"
                values={form.data.tags}
                onChange={(values) => form.setData('tags', values)}
                placeholder="Type a tag and press Enter"
            />

            <div className="grid grid-cols-3 gap-4">
                <div className="flex flex-col gap-1.5">
                    <Label>Price min (EUR)</Label>
                    <Input
                        type="number"
                        min={0}
                        value={form.data.price_min}
                        onChange={(e) => form.setData('price_min', e.target.value)}
                    />
                </div>
                <div className="flex flex-col gap-1.5">
                    <Label>Price max (EUR)</Label>
                    <Input
                        type="number"
                        min={0}
                        value={form.data.price_max}
                        onChange={(e) => form.setData('price_max', e.target.value)}
                    />
                </div>
                <div className="flex flex-col justify-end gap-1.5 pb-2">
                    <label className="flex items-center gap-2 font-body text-sm text-text-muted">
                        <input
                            type="checkbox"
                            checked={form.data.saas_eligible}
                            onChange={(e) => form.setData('saas_eligible', e.target.checked)}
                        />
                        SaaS eligible
                    </label>
                    <label className="flex items-center gap-2 font-body text-sm text-text-muted">
                        <input
                            type="checkbox"
                            checked={form.data.hidden}
                            onChange={(e) => form.setData('hidden', e.target.checked)}
                        />
                        Hidden
                    </label>
                </div>
            </div>

            <div className="flex flex-col gap-2">
                <Label>Niche mappings (check = mapped, star = recommended)</Label>
                <div className="max-h-64 overflow-y-auto rounded-md border border-line p-3">
                    {categories.map((category) => (
                        <div key={category.id} className="mb-3 last:mb-0">
                            <p className="font-ui text-xs uppercase tracking-wide text-text-faint">
                                {category.name.en}
                            </p>
                            <div className="mt-1 flex flex-col gap-1">
                                {category.niches.map((niche) => (
                                    <div key={niche.id} className="flex items-center gap-3 font-body text-sm text-text">
                                        <label className="flex flex-1 items-center gap-2">
                                            <input
                                                type="checkbox"
                                                checked={form.data.niche_ids.includes(niche.id)}
                                                onChange={() => toggleNiche(niche.id)}
                                            />
                                            {niche.name.en}
                                        </label>
                                        <label className="flex items-center gap-1.5 text-xs text-text-muted">
                                            <input
                                                type="checkbox"
                                                disabled={!form.data.niche_ids.includes(niche.id)}
                                                checked={form.data.recommended_niche_ids.includes(niche.id)}
                                                onChange={() => toggleRecommended(niche.id)}
                                            />
                                            Recommended
                                        </label>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

function AddServiceDialog({ categories }: { categories: Category[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm<ServiceFormData>(emptyServiceForm());

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('admin.content.services.store'), {
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
                    Service
                </Button>
            </DialogTrigger>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Add service</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <ServiceFormFields form={form} categories={categories} />
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Create
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EditServiceDialog({ service, categories }: { service: ServiceRow; categories: Category[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm<ServiceFormData>({
        name_en: service.name.en,
        name_bg: service.name.bg,
        one_liner_en: service.one_liner.en,
        one_liner_bg: service.one_liner.bg,
        base_features: service.base_features ?? [],
        tags: service.tags ?? [],
        saas_eligible: service.saas_eligible,
        price_min: service.price_min?.toString() ?? '',
        price_max: service.price_max?.toString() ?? '',
        hidden: service.hidden,
        niche_ids: service.niche_ids,
        recommended_niche_ids: service.recommended_niche_ids,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        router.patch(route('admin.content.services.update', service.id), form.data as never, {
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
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Edit {service.name.en}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <ServiceFormFields form={form} categories={categories} />
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ShowPricesToggle({ initialEnabled }: { initialEnabled: boolean }) {
    const [enabled, setEnabled] = useState(initialEnabled);

    const toggle = () => {
        const next = !enabled;
        setEnabled(next);
        router.patch(
            route('admin.content.settings.update', 'show_prices_to_bo'),
            { enabled: next },
            { preserveScroll: true },
        );
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Pricing visibility</CardTitle>
            </CardHeader>
            <CardContent className="flex items-center justify-between gap-4">
                <p className="font-body text-sm text-text-muted">
                    When enabled, service cards and the Phase 6 approx. total show indicative prices to the business
                    owner. When off, no price element renders anywhere in the discovery flow.
                </p>
                <label className="flex shrink-0 items-center gap-2 font-ui text-sm text-text">
                    <input type="checkbox" checked={enabled} onChange={toggle} />
                    Show prices to BO
                </label>
            </CardContent>
        </Card>
    );
}

export default function ContentIndex({
    categories,
    services,
    showPricesToBo,
}: {
    categories: Category[];
    services: ServiceRow[];
    showPricesToBo: boolean;
}) {
    return (
        <AdminLayout>
            <Head title="Content & funnel management" />

            <section className="animate-rise rounded-section border border-line-strong bg-surface-glass p-8 backdrop-blur-2xl">
                <p className="eyebrow text-accent">Admin</p>
                <h1 className="mt-2 font-display text-3xl font-semibold text-text">Content & funnel management</h1>
                <p className="mt-2 max-w-2xl font-body text-sm text-text-muted">
                    Manage the business taxonomy, service catalog and pricing visibility that power the discovery
                    flow.
                </p>
            </section>

            <div className="mt-6">
                <ShowPricesToggle initialEnabled={showPricesToBo} />
            </div>

            <div className="mt-6 flex items-center justify-between">
                <h2 className="font-display text-xl font-semibold text-text">Taxonomy</h2>
                <AddCategoryDialog />
            </div>

            <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                {categories.map((category) => (
                    <Card key={category.id}>
                        <CardHeader className="flex-row items-center justify-between">
                            <div>
                                <CardTitle>{category.name.en}</CardTitle>
                                <p className="font-body text-xs text-text-faint">{category.name.bg}</p>
                            </div>
                            <div className="flex items-center gap-2">
                                {category.hidden && <Badge variant="muted">Hidden</Badge>}
                                <CategoryEditDialog category={category} />
                            </div>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2">
                            {category.niches.map((niche) => (
                                <div
                                    key={niche.id}
                                    className="flex items-center justify-between rounded-md border border-line bg-surface-2 px-3 py-2"
                                >
                                    <div>
                                        <p className="font-body text-sm text-text">{niche.name.en}</p>
                                        <p className="font-body text-xs text-text-faint">{niche.name.bg}</p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {niche.hidden && <Badge variant="muted">Hidden</Badge>}
                                        <NicheEditDialog niche={niche} />
                                    </div>
                                </div>
                            ))}
                            <div className="pt-1">
                                <AddNicheDialog category={category} />
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>

            <div className="mt-8 flex items-center justify-between">
                <h2 className="font-display text-xl font-semibold text-text">Service catalog</h2>
                <AddServiceDialog categories={categories} />
            </div>

            <div className="mt-4 overflow-hidden rounded-admin border border-line bg-surface">
                <table className="w-full border-collapse font-body text-sm">
                    <thead>
                        <tr className="border-b border-line text-left font-ui text-xs uppercase tracking-wide text-text-faint">
                            <th className="px-4 py-3 font-medium">Service</th>
                            <th className="px-4 py-3 font-medium">Price (EUR)</th>
                            <th className="px-4 py-3 font-medium">SaaS</th>
                            <th className="px-4 py-3 font-medium">Niches</th>
                            <th className="px-4 py-3 font-medium">Status</th>
                            <th className="px-4 py-3 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {services.map((service) => (
                            <tr key={service.id} className="border-b border-line last:border-0 hover:bg-surface-2">
                                <td className="px-4 py-3">
                                    <p className="font-ui font-medium text-text">{service.name.en}</p>
                                    <p className="font-body text-xs text-text-faint">{service.key}</p>
                                </td>
                                <td className="px-4 py-3 tabular-nums text-text-muted">
                                    {service.price_min != null && service.price_max != null
                                        ? `€${service.price_min}–${service.price_max}`
                                        : '—'}
                                </td>
                                <td className="px-4 py-3">
                                    {service.saas_eligible ? (
                                        <Badge variant="accent">SaaS</Badge>
                                    ) : (
                                        <span className="text-text-faint">—</span>
                                    )}
                                </td>
                                <td className="px-4 py-3 tabular-nums text-text-muted">{service.niche_ids.length}</td>
                                <td className="px-4 py-3">
                                    {service.hidden ? (
                                        <Badge variant="muted">Hidden</Badge>
                                    ) : (
                                        <Badge variant="blue">Visible</Badge>
                                    )}
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <EditServiceDialog service={service} categories={categories} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
