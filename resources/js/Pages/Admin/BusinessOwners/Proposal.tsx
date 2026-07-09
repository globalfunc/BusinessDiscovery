import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Copy, FileDown, Lock, Sparkles, Upload as UploadIcon } from 'lucide-react';
import { lazy, Suspense, useRef, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/components/ui/use-toast';
import { cn } from '@/lib/utils';
import AdminLayout from '@/Layouts/AdminLayout';

type DocumentVersion = {
    id: number;
    version: number;
    markdown: string | null;
    generated_by: string;
    created_at: string | null;
};

type ProposalVersion = DocumentVersion & {
    attachments: number[];
    upload: { id: number; original_name: string; url: string } | null;
};

type EmailDraftView = {
    id: number;
    kind: string;
    language: string;
    subject: string;
    body: string;
    created_at: string | null;
};

type UploadView = { id: number; original_name: string; kind: string };

const EMAIL_KIND_LABELS: Record<string, string> = {
    warm_tease: 'Warm tease',
    follow_up: 'Follow-up',
    proposal_cover: 'Proposal cover',
};

function formatDate(value: string | null) {
    return value ? new Date(value).toLocaleString() : '—';
}

function VersionList({
    versions,
    activeId,
    onSelect,
}: {
    versions: DocumentVersion[];
    activeId: number | null;
    onSelect: (version: DocumentVersion) => void;
}) {
    return (
        <ul className="flex flex-col gap-2">
            {versions.map((v) => (
                <li key={v.id}>
                    <button
                        type="button"
                        onClick={() => onSelect(v)}
                        className={cn(
                            'flex w-full flex-wrap items-center justify-between gap-2 rounded-md border px-3 py-2 text-left transition-colors',
                            activeId === v.id ? 'border-accent/40 bg-accent/5' : 'border-line bg-surface-2 hover:border-line-strong',
                        )}
                    >
                        <div className="flex items-center gap-2">
                            <Badge variant={v.generated_by === 'ai' ? 'blue' : 'muted'}>v{v.version}</Badge>
                            <span className="font-body text-xs text-text-muted">{v.generated_by}</span>
                        </div>
                        <span className="font-body text-xs text-text-faint">{formatDate(v.created_at)}</span>
                    </button>
                </li>
            ))}
        </ul>
    );
}

const LazyMarkdownEditor = lazy(() => import('@/components/proposal/MarkdownEditor'));

function MarkdownEditor(props: { value: string; onChange: (value: string) => void }) {
    return (
        <Suspense fallback={<div className="h-[480px] animate-pulse rounded-md border border-line bg-surface-2" />}>
            <LazyMarkdownEditor {...props} />
        </Suspense>
    );
}

export default function ProposalBuilder({
    businessOwner,
    hasSpec,
    assessments,
    proposals,
    emailDrafts,
    uploads,
}: {
    businessOwner: { id: number; name: string; company: string; language: string };
    hasSpec: boolean;
    assessments: DocumentVersion[];
    proposals: ProposalVersion[];
    emailDrafts: EmailDraftView[];
    uploads: UploadView[];
}) {
    const { toast } = useToast();
    const [tab, setTab] = useState<'assessment' | 'proposal' | 'emails'>('assessment');
    const [busy, setBusy] = useState(false);

    // Assessment state
    const latestAssessment = assessments[0] ?? null;
    const [assessmentDraft, setAssessmentDraft] = useState(latestAssessment?.markdown ?? '');
    const [assessmentBaseId, setAssessmentBaseId] = useState<number | null>(latestAssessment?.id ?? null);
    const [assessmentNotes, setAssessmentNotes] = useState('');
    const assessmentBase = assessments.find((v) => v.id === assessmentBaseId) ?? null;

    // Proposal state
    const latestProposal = proposals[0] ?? null;
    const [proposalDraft, setProposalDraft] = useState(latestProposal?.markdown ?? '');
    const [proposalBaseId, setProposalBaseId] = useState<number | null>(latestProposal?.id ?? null);
    const [attachmentIds, setAttachmentIds] = useState<number[]>(latestProposal?.attachments ?? []);
    const proposalBase = proposals.find((v) => v.id === proposalBaseId) ?? null;
    const fileInputRef = useRef<HTMLInputElement>(null);
    const uploadForm = useForm<{ file: File | null }>({ file: null });

    // Email state
    const [emailLanguage, setEmailLanguage] = useState(businessOwner.language);

    const post = (routeName: string, data: Record<string, string | number[] | null> = {}, onSuccess?: () => void) => {
        setBusy(true);
        router.post(route(routeName, businessOwner.id), data, {
            preserveScroll: true,
            onSuccess,
            onFinish: () => setBusy(false),
        });
    };

    const copyToClipboard = async (label: string, text: string) => {
        await navigator.clipboard.writeText(text);
        toast({ title: `${label} copied to clipboard.` });
    };

    const tabs = [
        { key: 'assessment' as const, label: `Assessment${assessments.length ? ` (v${assessments[0].version})` : ''}` },
        { key: 'proposal' as const, label: `Proposal${proposals.length ? ` (v${proposals[0].version})` : ''}` },
        { key: 'emails' as const, label: 'Emails' },
    ];

    return (
        <AdminLayout>
            <Head title={`${businessOwner.name} — Proposal builder`} />

            <Link
                href={route('admin.business-owners.show', businessOwner.id)}
                className="inline-flex items-center gap-1.5 font-ui text-sm text-text-muted hover:text-text"
            >
                <ArrowLeft className="h-4 w-4" />
                {businessOwner.name}
            </Link>

            <section className="animate-rise mt-4 rounded-section border border-line-strong bg-surface-glass p-8 backdrop-blur-2xl">
                <p className="eyebrow text-accent">Proposal builder</p>
                <h1 className="mt-1 font-display text-3xl font-semibold text-text">{businessOwner.name}</h1>
                <p className="font-body text-sm text-text-muted">{businessOwner.company}</p>
                {!hasSpec && (
                    <p className="mt-3 font-body text-sm text-yellow">
                        No compiled specification yet — generators will draft from the raw discovery data available so far.
                    </p>
                )}
            </section>

            <div className="mt-6 flex gap-1 overflow-hidden rounded-md border border-line-strong p-1">
                {tabs.map((t) => (
                    <button
                        key={t.key}
                        type="button"
                        onClick={() => setTab(t.key)}
                        className={cn(
                            'flex-1 rounded px-3 py-1.5 font-ui text-sm font-medium transition-colors',
                            tab === t.key ? 'bg-accent text-[#241305]' : 'text-text-muted hover:text-text',
                        )}
                    >
                        {t.label}
                    </button>
                ))}
            </div>

            {tab === 'assessment' && (
                <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="flex flex-col gap-6 lg:col-span-2">
                        <Card>
                            <CardHeader className="flex-row flex-wrap items-center justify-between gap-3 space-y-0">
                                <CardTitle>Internal technical assessment</CardTitle>
                                <Badge variant="muted">Never shown to the client</Badge>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-4">
                                {assessments.length === 0 ? (
                                    <p className="font-body text-sm text-text-faint">
                                        No assessment yet. Generate one from the spec &amp; discovery data — the proposal will ground its
                                        pricing and timeline in it.
                                    </p>
                                ) : (
                                    <>
                                        {assessmentBase && (
                                            <p className="font-ui text-xs text-text-faint">
                                                Editing from v{assessmentBase.version} · {assessmentBase.generated_by} ·{' '}
                                                {formatDate(assessmentBase.created_at)}
                                            </p>
                                        )}
                                        <MarkdownEditor value={assessmentDraft} onChange={setAssessmentDraft} />
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Button
                                                size="sm"
                                                disabled={busy || assessmentDraft.trim() === '' || assessmentDraft === (assessmentBase?.markdown ?? '')}
                                                onClick={() => post('admin.business-owners.assessment.store', { markdown: assessmentDraft })}
                                            >
                                                Save as new version
                                            </Button>
                                        </div>
                                    </>
                                )}
                                <div className="flex flex-col gap-2 rounded-md border border-line bg-surface-2 p-3">
                                    <label className="font-ui text-xs text-text-muted" htmlFor="assessment-notes">
                                        Admin notes for the generator (optional)
                                    </label>
                                    <Textarea
                                        id="assessment-notes"
                                        value={assessmentNotes}
                                        onChange={(e) => setAssessmentNotes(e.target.value)}
                                        placeholder="Anything the AI should factor in — constraints, ideas from the onsite visit, client quirks…"
                                        rows={3}
                                    />
                                    <div>
                                        <Button
                                            size="sm"
                                            variant="secondary"
                                            disabled={busy}
                                            onClick={() => post('admin.business-owners.assessment.generate', { notes: assessmentNotes })}
                                        >
                                            <Sparkles className="h-3.5 w-3.5" />
                                            {assessments.length === 0 ? 'Generate assessment' : 'Regenerate (new version)'}
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                    <Card className="self-start">
                        <CardHeader>
                            <CardTitle>Versions</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {assessments.length === 0 ? (
                                <p className="font-body text-sm text-text-faint">No versions yet.</p>
                            ) : (
                                <VersionList
                                    versions={assessments}
                                    activeId={assessmentBaseId}
                                    onSelect={(v) => {
                                        setAssessmentBaseId(v.id);
                                        setAssessmentDraft(v.markdown ?? '');
                                    }}
                                />
                            )}
                        </CardContent>
                    </Card>
                </div>
            )}

            {tab === 'proposal' && (
                <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="flex flex-col gap-6 lg:col-span-2">
                        <Card>
                            <CardHeader className="flex-row flex-wrap items-center justify-between gap-3 space-y-0">
                                <CardTitle>Client-facing proposal</CardTitle>
                                <div className="flex items-center gap-2">
                                    <Button
                                        size="sm"
                                        variant="secondary"
                                        disabled={busy || assessments.length === 0}
                                        title={
                                            assessments.length === 0
                                                ? 'Generate an assessment first — the proposal grounds its numbers in it.'
                                                : undefined
                                        }
                                        onClick={() => post('admin.business-owners.proposal.generate')}
                                    >
                                        {assessments.length === 0 ? <Lock className="h-3.5 w-3.5" /> : <Sparkles className="h-3.5 w-3.5" />}
                                        Generate proposal
                                    </Button>
                                    <Button size="sm" variant="ghost" disabled title="PDF export arrives in a later update.">
                                        <FileDown className="h-3.5 w-3.5" />
                                        Export as PDF (coming soon)
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-4">
                                {assessments.length === 0 && (
                                    <p className="rounded-md border border-yellow/30 bg-yellow/10 p-3 font-body text-sm text-yellow">
                                        Proposal generation is locked until an assessment exists — its pricing and timeline are grounded in
                                        the (admin-reviewed) assessment, not guessed from the spec alone.
                                    </p>
                                )}
                                {proposals.length === 0 ? (
                                    <p className="font-body text-sm text-text-faint">No proposal yet.</p>
                                ) : proposalBase && proposalBase.markdown === null ? (
                                    <div className="rounded-md border border-line bg-surface-2 p-4">
                                        <p className="font-body text-sm text-text-muted">
                                            Externally-written proposal:{' '}
                                            {proposalBase.upload ? (
                                                <a href={proposalBase.upload.url} className="text-accent hover:underline">
                                                    {proposalBase.upload.original_name}
                                                </a>
                                            ) : (
                                                'file missing'
                                            )}
                                        </p>
                                    </div>
                                ) : (
                                    <>
                                        {proposalBase && (
                                            <p className="font-ui text-xs text-text-faint">
                                                Editing from v{proposalBase.version} · {proposalBase.generated_by} ·{' '}
                                                {formatDate(proposalBase.created_at)}
                                            </p>
                                        )}
                                        <MarkdownEditor value={proposalDraft} onChange={setProposalDraft} />
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Button
                                                size="sm"
                                                disabled={
                                                    busy ||
                                                    proposalDraft.trim() === '' ||
                                                    (proposalDraft === (proposalBase?.markdown ?? '') &&
                                                        JSON.stringify(attachmentIds) === JSON.stringify(proposalBase?.attachments ?? []))
                                                }
                                                onClick={() =>
                                                    post('admin.business-owners.proposal.store', {
                                                        markdown: proposalDraft,
                                                        attachments: attachmentIds,
                                                    })
                                                }
                                            >
                                                Save as new version
                                            </Button>
                                        </div>
                                    </>
                                )}

                                {uploads.length > 0 && proposals.length > 0 && (
                                    <div className="flex flex-col gap-2 rounded-md border border-line bg-surface-2 p-3">
                                        <p className="font-ui text-xs text-text-muted">Attach assets to the next saved version</p>
                                        <div className="flex flex-col gap-1">
                                            {uploads.map((upload) => (
                                                <label key={upload.id} className="flex items-center gap-2 font-body text-sm text-text">
                                                    <input
                                                        type="checkbox"
                                                        checked={attachmentIds.includes(upload.id)}
                                                        onChange={(e) =>
                                                            setAttachmentIds((ids) =>
                                                                e.target.checked ? [...ids, upload.id] : ids.filter((id) => id !== upload.id),
                                                            )
                                                        }
                                                    />
                                                    {upload.original_name}
                                                    <span className="font-body text-xs text-text-faint">({upload.kind})</span>
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                <div className="flex flex-col gap-2 rounded-md border border-line bg-surface-2 p-3">
                                    <p className="font-ui text-xs text-text-muted">
                                        Or upload an externally-written proposal (pdf, docx, odt, md, txt)
                                    </p>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <input
                                            ref={fileInputRef}
                                            type="file"
                                            accept=".pdf,.docx,.doc,.odt,.md,.txt"
                                            className="font-body text-sm text-text-muted"
                                            onChange={(e) => uploadForm.setData('file', e.target.files?.[0] ?? null)}
                                        />
                                        <Button
                                            size="sm"
                                            variant="secondary"
                                            disabled={busy || uploadForm.processing || !uploadForm.data.file}
                                            onClick={() =>
                                                uploadForm.post(route('admin.business-owners.proposal.upload', businessOwner.id), {
                                                    preserveScroll: true,
                                                    onSuccess: () => {
                                                        uploadForm.setData('file', null);
                                                        if (fileInputRef.current) fileInputRef.current.value = '';
                                                    },
                                                })
                                            }
                                        >
                                            <UploadIcon className="h-3.5 w-3.5" />
                                            Save as new version
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                    <Card className="self-start">
                        <CardHeader>
                            <CardTitle>Versions</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {proposals.length === 0 ? (
                                <p className="font-body text-sm text-text-faint">No versions yet.</p>
                            ) : (
                                <VersionList
                                    versions={proposals}
                                    activeId={proposalBaseId}
                                    onSelect={(v) => {
                                        const proposal = proposals.find((p) => p.id === v.id);
                                        setProposalBaseId(v.id);
                                        setProposalDraft(v.markdown ?? '');
                                        setAttachmentIds(proposal?.attachments ?? []);
                                    }}
                                />
                            )}
                        </CardContent>
                    </Card>
                </div>
            )}

            {tab === 'emails' && (
                <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <Card className="self-start">
                        <CardHeader>
                            <CardTitle>Generate email copy</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            <label className="flex items-center justify-between gap-2 font-body text-sm text-text-muted">
                                Language
                                <Select className="w-auto" value={emailLanguage} onChange={(e) => setEmailLanguage(e.target.value)}>
                                    <option value="bg">Български</option>
                                    <option value="en">English</option>
                                </Select>
                            </label>
                            {Object.entries(EMAIL_KIND_LABELS).map(([kind, label]) => (
                                <Button
                                    key={kind}
                                    variant="secondary"
                                    size="sm"
                                    disabled={busy}
                                    onClick={() => post('admin.business-owners.emails.generate', { kind, language: emailLanguage })}
                                >
                                    <Sparkles className="h-3.5 w-3.5" />
                                    {label}
                                </Button>
                            ))}
                            <p className="font-body text-xs text-text-faint">
                                Drafts are copy-to-clipboard only — no emails are sent from here.
                            </p>
                        </CardContent>
                    </Card>
                    <div className="flex flex-col gap-4 lg:col-span-2">
                        {emailDrafts.length === 0 ? (
                            <Card>
                                <CardContent className="p-8 text-center">
                                    <p className="font-body text-sm text-text-faint">No email drafts yet.</p>
                                </CardContent>
                            </Card>
                        ) : (
                            emailDrafts.map((draft) => (
                                <Card key={draft.id}>
                                    <CardHeader className="flex-row flex-wrap items-center justify-between gap-2 space-y-0">
                                        <div className="flex items-center gap-2">
                                            <Badge variant="blue">{EMAIL_KIND_LABELS[draft.kind] ?? draft.kind}</Badge>
                                            <Badge variant="muted">{draft.language.toUpperCase()}</Badge>
                                            <span className="font-body text-xs text-text-faint">{formatDate(draft.created_at)}</span>
                                        </div>
                                        <div className="flex items-center gap-1.5">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() => copyToClipboard('Subject', draft.subject)}
                                            >
                                                <Copy className="h-3.5 w-3.5" />
                                                Subject
                                            </Button>
                                            <Button size="sm" variant="ghost" onClick={() => copyToClipboard('Body', draft.body)}>
                                                <Copy className="h-3.5 w-3.5" />
                                                Body
                                            </Button>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="flex flex-col gap-2">
                                        <p className="font-ui text-sm font-medium text-text">{draft.subject}</p>
                                        <p className="whitespace-pre-wrap font-body text-sm text-text-muted">{draft.body}</p>
                                    </CardContent>
                                </Card>
                            ))
                        )}
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
