import { Head, Link, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/Layouts/AdminLayout';

type BusinessOwnerRow = {
    id: number;
    name: string;
    company: string;
    status: string;
    current_stage: string;
    language: string | null;
    referral_tokens_count: number;
    created_at: string | null;
};

const stageLabels: Record<string, string> = {
    prospect: 'Prospect',
    referral_sent: 'Referral sent',
    link_visited: 'Link visited',
    discovery_in_progress: 'Discovery in progress',
    discovery_complete: 'Discovery complete',
    proposal_sent: 'Proposal sent',
    negotiation: 'Negotiation',
    won: 'Won',
    lost: 'Lost',
};

type NicheOption = {
    id: number;
    name: { en: string; bg: string };
    category_name: { en: string; bg: string } | null;
};

export default function BusinessOwnersIndex({
    businessOwners,
    niches,
}: {
    businessOwners: BusinessOwnerRow[];
    niches: NicheOption[];
}) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm<{
        name: string;
        company: string;
        logo: File | null;
        greeting_override: string;
        admin_context: string;
        language: string;
        pre_selected_niche_id: string;
    }>({
        name: '',
        company: '',
        logo: null,
        greeting_override: '',
        admin_context: '',
        language: '',
        pre_selected_niche_id: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('admin.business-owners.store'), {
            forceFormData: true,
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    return (
        <AdminLayout>
            <Head title="Business Owners" />

            <section className="animate-rise rounded-section border border-line-strong bg-surface-glass p-8 backdrop-blur-2xl">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="eyebrow text-accent">Admin</p>
                        <h1 className="mt-2 font-display text-3xl font-semibold text-text">Business Owners</h1>
                        <p className="mt-2 max-w-xl font-body text-sm text-text-muted">
                            Provision a BO profile, then generate their referral link from the detail page.
                        </p>
                    </div>

                    <Dialog open={open} onOpenChange={setOpen}>
                        <DialogTrigger asChild>
                            <Button>
                                <Plus className="h-4 w-4" />
                                New business owner
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="max-w-lg">
                            <DialogHeader>
                                <DialogTitle>New business owner</DialogTitle>
                                <DialogDescription>
                                    Referral link generation happens on the detail page after creation.
                                </DialogDescription>
                            </DialogHeader>

                            <form onSubmit={submit} className="flex flex-col gap-4">
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="flex flex-col gap-1.5">
                                        <Label htmlFor="name">Name</Label>
                                        <Input
                                            id="name"
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            autoFocus
                                        />
                                        {errors.name && <p className="font-body text-xs text-red">{errors.name}</p>}
                                    </div>
                                    <div className="flex flex-col gap-1.5">
                                        <Label htmlFor="company">Company</Label>
                                        <Input
                                            id="company"
                                            value={data.company}
                                            onChange={(e) => setData('company', e.target.value)}
                                        />
                                        {errors.company && (
                                            <p className="font-body text-xs text-red">{errors.company}</p>
                                        )}
                                    </div>
                                </div>

                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="logo">Logo</Label>
                                    <Input
                                        id="logo"
                                        type="file"
                                        accept="image/*"
                                        onChange={(e) => setData('logo', e.target.files?.[0] ?? null)}
                                    />
                                    {errors.logo && <p className="font-body text-xs text-red">{errors.logo}</p>}
                                </div>

                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="language">Language default</Label>
                                    <Select
                                        id="language"
                                        value={data.language}
                                        onChange={(e) => setData('language', e.target.value)}
                                    >
                                        <option value="">Auto-detect (browser default)</option>
                                        <option value="bg">Bulgarian</option>
                                        <option value="en">English</option>
                                    </Select>
                                </div>

                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="pre_selected_niche_id">Pre-selected niche</Label>
                                    <Select
                                        id="pre_selected_niche_id"
                                        value={data.pre_selected_niche_id}
                                        onChange={(e) => setData('pre_selected_niche_id', e.target.value)}
                                    >
                                        <option value="">None — BO picks their own niche</option>
                                        {niches.map((niche) => (
                                            <option key={niche.id} value={niche.id}>
                                                {niche.category_name ? `${niche.category_name.en} — ` : ''}
                                                {niche.name.en}
                                            </option>
                                        ))}
                                    </Select>
                                </div>

                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="greeting_override">Greeting override</Label>
                                    <Textarea
                                        id="greeting_override"
                                        placeholder="Leave blank to use the default greeting copy"
                                        value={data.greeting_override}
                                        onChange={(e) => setData('greeting_override', e.target.value)}
                                    />
                                </div>

                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="admin_context">Business context (for AI, not shown to BO)</Label>
                                    <Textarea
                                        id="admin_context"
                                        placeholder="Anything you already know about this business — feeds the AI later"
                                        value={data.admin_context}
                                        onChange={(e) => setData('admin_context', e.target.value)}
                                    />
                                </div>

                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        Create
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>
            </section>

            <div className="mt-6 overflow-hidden rounded-admin border border-line bg-surface">
                <table className="w-full border-collapse font-body text-sm">
                    <thead>
                        <tr className="border-b border-line text-left font-ui text-xs uppercase tracking-wide text-text-faint">
                            <th className="px-4 py-3 font-medium">Name</th>
                            <th className="px-4 py-3 font-medium">Company</th>
                            <th className="px-4 py-3 font-medium">Stage</th>
                            <th className="px-4 py-3 font-medium">Status</th>
                            <th className="px-4 py-3 font-medium">Links</th>
                            <th className="px-4 py-3 font-medium">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        {businessOwners.length === 0 && (
                            <tr>
                                <td colSpan={6} className="px-4 py-8 text-center text-text-faint">
                                    No business owners yet — create the first one above.
                                </td>
                            </tr>
                        )}
                        {businessOwners.map((bo) => (
                            <tr key={bo.id} className="border-b border-line last:border-0 hover:bg-surface-2">
                                <td className="px-4 py-3">
                                    <Link
                                        href={route('admin.business-owners.show', bo.id)}
                                        className="font-ui font-medium text-text hover:text-blue"
                                    >
                                        {bo.name}
                                    </Link>
                                </td>
                                <td className="px-4 py-3 text-text-muted">{bo.company}</td>
                                <td className="px-4 py-3">
                                    <Badge variant="blue">{stageLabels[bo.current_stage] ?? bo.current_stage}</Badge>
                                </td>
                                <td className="px-4 py-3">
                                    <Badge variant={bo.status === 'active' ? 'accent' : 'muted'}>{bo.status}</Badge>
                                </td>
                                <td className="px-4 py-3 tabular-nums text-text-muted">
                                    {bo.referral_tokens_count}
                                </td>
                                <td className="px-4 py-3 text-text-faint">
                                    {bo.created_at ? new Date(bo.created_at).toLocaleDateString() : '—'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
