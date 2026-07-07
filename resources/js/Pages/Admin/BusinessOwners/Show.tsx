import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Check, Copy, RefreshCw, Send, Ban, Clock } from 'lucide-react';
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
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/components/ui/use-toast';
import AdminLayout from '@/Layouts/AdminLayout';

type BusinessOwner = {
    id: number;
    name: string;
    company: string;
    logo_path: string | null;
    greeting_override: string | null;
    admin_context: string | null;
    language: string | null;
    pre_selected_niche_id: number | null;
    status: string;
    current_stage: string;
    created_at: string | null;
};

type NicheOption = {
    id: number;
    name: { en: string; bg: string };
    category_name: { en: string; bg: string } | null;
};

type ReferralToken = {
    id: number;
    state: string;
    expires_at: string | null;
    sent_at: string | null;
    first_visited_at: string | null;
    revoked_at: string | null;
    created_at: string | null;
};

type ActivityEvent = {
    id: number;
    type: string;
    payload: Record<string, unknown> | null;
    created_at: string | null;
};

const stateVariant: Record<string, 'default' | 'accent' | 'blue' | 'muted' | 'red'> = {
    created: 'muted',
    sent: 'blue',
    visited: 'blue',
    in_progress: 'accent',
    submitted: 'accent',
    revoked: 'red',
    expired: 'red',
};

function formatDate(value: string | null) {
    return value ? new Date(value).toLocaleString() : '—';
}

export default function BusinessOwnerShow({
    businessOwner,
    referralTokens,
    activity,
    niches,
}: {
    businessOwner: BusinessOwner;
    referralTokens: ReferralToken[];
    activity: ActivityEvent[];
    niches: NicheOption[];
}) {
    const { props } = usePage<{ flash?: { plainReferralUrl?: string | null } }>();
    const { toast } = useToast();
    const [editOpen, setEditOpen] = useState(false);
    const [expiryTokenId, setExpiryTokenId] = useState<number | null>(null);
    const [expiryValue, setExpiryValue] = useState('');

    const editForm = useForm<{
        name: string;
        company: string;
        logo: File | null;
        greeting_override: string;
        admin_context: string;
        language: string;
        pre_selected_niche_id: string;
        status: string;
        _method: string;
    }>({
        name: businessOwner.name,
        company: businessOwner.company,
        logo: null,
        greeting_override: businessOwner.greeting_override ?? '',
        admin_context: businessOwner.admin_context ?? '',
        language: businessOwner.language ?? '',
        pre_selected_niche_id: businessOwner.pre_selected_niche_id?.toString() ?? '',
        status: businessOwner.status,
        _method: 'put',
    });

    const submitEdit: FormEventHandler = (e) => {
        e.preventDefault();
        editForm.post(route('admin.business-owners.update', businessOwner.id), {
            forceFormData: true,
            onSuccess: () => setEditOpen(false),
        });
    };

    const plainUrl = props.flash?.plainReferralUrl ?? null;
    const hasUsableToken = referralTokens.some((t) => ['created', 'sent', 'visited', 'in_progress'].includes(t.state));

    const copy = (text: string) => {
        navigator.clipboard.writeText(text);
        toast({ title: 'Copied to clipboard' });
    };

    const generate = () => {
        router.post(route('admin.business-owners.referral-tokens.store', businessOwner.id), {}, { preserveScroll: true });
    };

    const regenerate = (tokenId: number) => {
        router.post(
            route('admin.business-owners.referral-tokens.regenerate', [businessOwner.id, tokenId]),
            {},
            { preserveScroll: true },
        );
    };

    const revoke = (tokenId: number) => {
        router.post(
            route('admin.business-owners.referral-tokens.revoke', [businessOwner.id, tokenId]),
            {},
            { preserveScroll: true },
        );
    };

    const markSent = (tokenId: number) => {
        router.post(
            route('admin.business-owners.referral-tokens.mark-sent', [businessOwner.id, tokenId]),
            {},
            { preserveScroll: true },
        );
    };

    const saveExpiry = (tokenId: number) => {
        router.patch(
            route('admin.business-owners.referral-tokens.expiry', [businessOwner.id, tokenId]),
            { expires_at: expiryValue },
            { preserveScroll: true, onSuccess: () => setExpiryTokenId(null) },
        );
    };

    return (
        <AdminLayout>
            <Head title={businessOwner.name} />

            <Link
                href={route('admin.business-owners.index')}
                className="inline-flex items-center gap-1.5 font-ui text-sm text-text-muted hover:text-text"
            >
                <ArrowLeft className="h-4 w-4" />
                Business Owners
            </Link>

            <section className="animate-rise mt-4 rounded-section border border-line-strong bg-surface-glass p-8 backdrop-blur-2xl">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex items-center gap-4">
                        {businessOwner.logo_path && (
                            <img
                                src={businessOwner.logo_path}
                                alt={`${businessOwner.company} logo`}
                                className="h-14 w-14 rounded-md border border-line object-cover"
                            />
                        )}
                        <div>
                            <p className="eyebrow text-accent">Business Owner</p>
                            <h1 className="mt-1 font-display text-3xl font-semibold text-text">
                                {businessOwner.name}
                            </h1>
                            <p className="font-body text-sm text-text-muted">{businessOwner.company}</p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Badge variant="blue">{businessOwner.current_stage.replace(/_/g, ' ')}</Badge>
                        <Badge variant={businessOwner.status === 'active' ? 'accent' : 'muted'}>
                            {businessOwner.status}
                        </Badge>

                        <Dialog open={editOpen} onOpenChange={setEditOpen}>
                            <DialogTrigger asChild>
                                <Button variant="secondary">Edit</Button>
                            </DialogTrigger>
                            <DialogContent className="max-w-lg">
                                <DialogHeader>
                                    <DialogTitle>Edit business owner</DialogTitle>
                                </DialogHeader>
                                <form onSubmit={submitEdit} className="flex flex-col gap-4">
                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="flex flex-col gap-1.5">
                                            <Label htmlFor="edit-name">Name</Label>
                                            <Input
                                                id="edit-name"
                                                value={editForm.data.name}
                                                onChange={(e) => editForm.setData('name', e.target.value)}
                                            />
                                        </div>
                                        <div className="flex flex-col gap-1.5">
                                            <Label htmlFor="edit-company">Company</Label>
                                            <Input
                                                id="edit-company"
                                                value={editForm.data.company}
                                                onChange={(e) => editForm.setData('company', e.target.value)}
                                            />
                                        </div>
                                    </div>

                                    <div className="flex flex-col gap-1.5">
                                        <Label htmlFor="edit-logo">Replace logo</Label>
                                        <Input
                                            id="edit-logo"
                                            type="file"
                                            accept="image/*"
                                            onChange={(e) => editForm.setData('logo', e.target.files?.[0] ?? null)}
                                        />
                                    </div>

                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="flex flex-col gap-1.5">
                                            <Label htmlFor="edit-language">Language default</Label>
                                            <Select
                                                id="edit-language"
                                                value={editForm.data.language}
                                                onChange={(e) => editForm.setData('language', e.target.value)}
                                            >
                                                <option value="">Auto-detect</option>
                                                <option value="bg">Bulgarian</option>
                                                <option value="en">English</option>
                                            </Select>
                                        </div>
                                        <div className="flex flex-col gap-1.5">
                                            <Label htmlFor="edit-status">Status</Label>
                                            <Select
                                                id="edit-status"
                                                value={editForm.data.status}
                                                onChange={(e) => editForm.setData('status', e.target.value)}
                                            >
                                                <option value="active">Active</option>
                                                <option value="archived">Archived</option>
                                            </Select>
                                        </div>
                                    </div>

                                    <div className="flex flex-col gap-1.5">
                                        <Label htmlFor="edit-niche">Pre-selected niche</Label>
                                        <Select
                                            id="edit-niche"
                                            value={editForm.data.pre_selected_niche_id}
                                            onChange={(e) => editForm.setData('pre_selected_niche_id', e.target.value)}
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
                                        <Label htmlFor="edit-greeting">Greeting override</Label>
                                        <Textarea
                                            id="edit-greeting"
                                            value={editForm.data.greeting_override}
                                            onChange={(e) => editForm.setData('greeting_override', e.target.value)}
                                        />
                                    </div>

                                    <div className="flex flex-col gap-1.5">
                                        <Label htmlFor="edit-context">Business context</Label>
                                        <Textarea
                                            id="edit-context"
                                            value={editForm.data.admin_context}
                                            onChange={(e) => editForm.setData('admin_context', e.target.value)}
                                        />
                                    </div>

                                    <DialogFooter>
                                        <Button type="submit" disabled={editForm.processing}>
                                            Save
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>
            </section>

            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Referral link</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        {plainUrl && (
                            <div className="rounded-md border border-accent/40 bg-surface-2 p-3">
                                <p className="font-ui text-xs text-accent">
                                    Shown once — copy it now, it can&apos;t be retrieved later.
                                </p>
                                <div className="mt-2 flex items-center gap-2">
                                    <code className="flex-1 truncate font-body text-xs text-text">{plainUrl}</code>
                                    <Button size="sm" variant="secondary" onClick={() => copy(plainUrl)}>
                                        <Copy className="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                            </div>
                        )}

                        {!hasUsableToken && (
                            <Button onClick={generate} className="self-start">
                                Generate referral link
                            </Button>
                        )}

                        <div className="flex flex-col gap-3">
                            {referralTokens.map((token) => (
                                <div key={token.id} className="rounded-md border border-line bg-surface-2 p-3">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <Badge variant={stateVariant[token.state] ?? 'default'}>{token.state}</Badge>
                                        <span className="font-body text-xs text-text-faint">
                                            created {formatDate(token.created_at)}
                                        </span>
                                    </div>

                                    <dl className="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 font-body text-xs text-text-muted">
                                        <dt>Expires</dt>
                                        <dd>{formatDate(token.expires_at)}</dd>
                                        <dt>Sent</dt>
                                        <dd>{formatDate(token.sent_at)}</dd>
                                        <dt>First visited</dt>
                                        <dd>{formatDate(token.first_visited_at)}</dd>
                                        <dt>Revoked</dt>
                                        <dd>{formatDate(token.revoked_at)}</dd>
                                    </dl>

                                    {['created', 'sent', 'visited', 'in_progress'].includes(token.state) && (
                                        <div className="mt-3 flex flex-wrap items-center gap-2">
                                            {token.state === 'created' && (
                                                <Button size="sm" variant="secondary" onClick={() => markSent(token.id)}>
                                                    <Send className="h-3.5 w-3.5" />
                                                    Mark sent
                                                </Button>
                                            )}
                                            <Button size="sm" variant="secondary" onClick={() => regenerate(token.id)}>
                                                <RefreshCw className="h-3.5 w-3.5" />
                                                Regenerate
                                            </Button>
                                            <Button size="sm" variant="destructive" onClick={() => revoke(token.id)}>
                                                <Ban className="h-3.5 w-3.5" />
                                                Revoke
                                            </Button>

                                            {expiryTokenId === token.id ? (
                                                <div className="flex items-center gap-2">
                                                    <Input
                                                        type="datetime-local"
                                                        className="h-8 w-auto text-xs"
                                                        value={expiryValue}
                                                        onChange={(e) => setExpiryValue(e.target.value)}
                                                    />
                                                    <Button size="sm" onClick={() => saveExpiry(token.id)}>
                                                        <Check className="h-3.5 w-3.5" />
                                                    </Button>
                                                </div>
                                            ) : (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() => {
                                                        setExpiryTokenId(token.id);
                                                        setExpiryValue('');
                                                    }}
                                                >
                                                    <Clock className="h-3.5 w-3.5" />
                                                    Set expiry
                                                </Button>
                                            )}
                                        </div>
                                    )}
                                </div>
                            ))}

                            {referralTokens.length === 0 && (
                                <p className="font-body text-sm text-text-faint">No referral link generated yet.</p>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Activity</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {activity.length === 0 && (
                            <p className="font-body text-sm text-text-faint">No activity recorded yet.</p>
                        )}
                        <ul className="flex flex-col gap-3">
                            {activity.map((event) => (
                                <li key={event.id} className="border-b border-line pb-2 last:border-0 last:pb-0">
                                    <p className="font-ui text-sm text-text">{event.type.replace(/_/g, ' ')}</p>
                                    <p className="font-body text-xs text-text-faint">{formatDate(event.created_at)}</p>
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>

                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Business context (admin-only, feeds AI later)</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="whitespace-pre-wrap font-body text-sm text-text-muted">
                            {businessOwner.admin_context || 'No context provided yet.'}
                        </p>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
