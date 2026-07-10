import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ThumbsDown, ThumbsUp } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AdminLayout from '@/Layouts/AdminLayout';

type ExemplarInContext = {
    id: number | null;
    version_in_context: number | null;
    current_version: number | null;
    dcp_excerpt: string | null;
    exemplar_brief: { paragraph?: string; bullets?: string[] } | null;
    deleted: boolean;
};

type BriefDetail = {
    id: number;
    business_owner: { id: number; name: string; company: string } | null;
    phase: string;
    module: string | null;
    brief: { paragraph?: string; bullets?: string[] } | null;
    verdict: string;
    drop_reason: string | null;
    scores: Record<string, { score: number; reason: string }> | null;
    composite: number | null;
    judge_model: string | null;
    rubric_version: number | null;
    label: string | null;
    model: string | null;
    prompt_version: number | null;
    created_at: string;
    dcp_digest: string;
    exemplars: ExemplarInContext[];
};

/**
 * One advisory brief with everything that produced it: the brief itself, the
 * judge's per-dimension scores and reasons, the DCP digest and the exemplar
 * set (at the persisted id+version) that were in context. The good/bad label
 * set here is the ground truth the rubric and exemplars are calibrated
 * against — label the brief, not the verdict: a hidden brief can be
 * "actually good" (threshold too strict) and a shown one "actually bad".
 */
export default function Show({ brief }: { brief: BriefDetail }) {
    const setLabel = (label: 'good' | 'bad' | null) => {
        router.patch(`/admin/advisory-briefs/${brief.id}/label`, { label: label === brief.label ? null : label }, { preserveScroll: true });
    };

    return (
        <AdminLayout>
            <Head title={`Advisory brief #${brief.id}`} />

            <Link href="/admin/advisory-briefs" className="inline-flex items-center gap-1.5 font-ui text-sm text-text-muted hover:text-text">
                <ArrowLeft className="h-4 w-4" />
                All briefs
            </Link>

            <section className="mt-4 rounded-section border border-line-strong bg-surface-glass p-8 backdrop-blur-2xl">
                <div className="flex flex-wrap items-center gap-2">
                    <p className="eyebrow text-accent">Brief #{brief.id}</p>
                    {brief.verdict === 'shown' && <Badge variant="blue">Shown</Badge>}
                    {brief.verdict === 'hidden_low_value' && <Badge variant="muted">Hidden (low value)</Badge>}
                    {brief.verdict === 'dropped' && <Badge variant="muted">Dropped{brief.drop_reason ? ` · ${brief.drop_reason}` : ''}</Badge>}
                </div>
                <h1 className="mt-2 font-display text-2xl font-semibold text-text">
                    {brief.business_owner?.company ?? brief.business_owner?.name ?? 'Unknown owner'}
                </h1>
                <p className="mt-1 font-body text-sm text-text-muted">
                    {brief.phase}
                    {brief.module ? ` · ${brief.module}` : ''} · generated {brief.created_at} · model {brief.model ?? '—'} · prompt v
                    {brief.prompt_version ?? '—'}
                </p>

                <div className="mt-4 flex items-center gap-2">
                    <Button
                        type="button"
                        size="sm"
                        variant={brief.label === 'good' ? 'default' : 'secondary'}
                        onClick={() => setLabel('good')}
                        className="gap-1.5"
                    >
                        <ThumbsUp className="h-3.5 w-3.5" />
                        Actually good
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant={brief.label === 'bad' ? 'default' : 'secondary'}
                        onClick={() => setLabel('bad')}
                        className="gap-1.5"
                    >
                        <ThumbsDown className="h-3.5 w-3.5" />
                        Actually bad
                    </Button>
                    <p className="font-body text-xs text-text-faint">Click again to clear. Labels are the calibration ground truth.</p>
                </div>
            </section>

            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <section className="rounded-admin border border-line bg-surface p-5">
                    <h2 className="font-ui text-xs font-semibold uppercase tracking-wide text-text-faint">The brief</h2>
                    {brief.brief?.paragraph ? (
                        <>
                            <p className="mt-2 font-body text-sm text-text">{brief.brief.paragraph}</p>
                            {(brief.brief.bullets ?? []).length > 0 && (
                                <ul className="mt-2 flex flex-col gap-1 font-body text-sm text-text">
                                    {brief.brief.bullets!.map((bullet, i) => (
                                        <li key={i} className="flex gap-2">
                                            <span aria-hidden className="text-accent">
                                                •
                                            </span>
                                            <span>{bullet}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </>
                    ) : (
                        <p className="mt-2 font-body text-sm italic text-text-faint">No brief payload was returned (drop reason: {brief.drop_reason ?? 'missing'}).</p>
                    )}
                </section>

                <section className="rounded-admin border border-line bg-surface p-5">
                    <h2 className="font-ui text-xs font-semibold uppercase tracking-wide text-text-faint">Judge scores</h2>
                    {brief.scores ? (
                        <>
                            <p className="mt-2 font-display text-2xl font-semibold text-text">
                                {brief.composite?.toFixed(2)}
                                <span className="ml-2 font-body text-xs font-normal text-text-faint">
                                    composite · {brief.judge_model} · rubric v{brief.rubric_version}
                                </span>
                            </p>
                            <ul className="mt-3 flex flex-col gap-2">
                                {Object.entries(brief.scores).map(([key, entry]) => (
                                    <li key={key} className="flex flex-col gap-0.5">
                                        <span className="font-ui text-sm font-semibold text-text">
                                            {key} — {entry.score}/5
                                        </span>
                                        <span className="font-body text-xs text-text-muted">{entry.reason}</span>
                                    </li>
                                ))}
                            </ul>
                        </>
                    ) : (
                        <p className="mt-2 font-body text-sm italic text-text-faint">
                            Not graded — the brief was dropped before grading, the judge call failed, or the reveal request never fired.
                        </p>
                    )}
                </section>
            </div>

            <section className="mt-6 rounded-admin border border-line bg-surface p-5">
                <h2 className="font-ui text-xs font-semibold uppercase tracking-wide text-text-faint">DCP input (what the model saw)</h2>
                {brief.dcp_digest ? (
                    <p className="mt-2 whitespace-pre-line font-body text-sm text-text-muted">{brief.dcp_digest}</p>
                ) : (
                    <p className="mt-2 font-body text-sm italic text-text-faint">No DCP snapshot is linked to this brief.</p>
                )}
            </section>

            <section className="mt-6 rounded-admin border border-line bg-surface p-5">
                <h2 className="font-ui text-xs font-semibold uppercase tracking-wide text-text-faint">Exemplars in context</h2>
                {brief.exemplars.length === 0 && <p className="mt-2 font-body text-sm italic text-text-faint">No exemplars were injected for this call.</p>}
                <div className="mt-2 grid grid-cols-1 gap-4 lg:grid-cols-2">
                    {brief.exemplars.map((exemplar, i) => (
                        <article key={i} className="rounded-md border border-line p-4">
                            <div className="flex items-center gap-2">
                                <span className="font-ui text-xs font-semibold text-text">Exemplar #{exemplar.id}</span>
                                <Badge variant="muted">v{exemplar.version_in_context}</Badge>
                                {exemplar.deleted && <Badge variant="muted">deleted since</Badge>}
                                {!exemplar.deleted && exemplar.current_version !== exemplar.version_in_context && (
                                    <Badge variant="muted">rewritten since (now v{exemplar.current_version})</Badge>
                                )}
                            </div>
                            {exemplar.dcp_excerpt && (
                                <p className="mt-2 whitespace-pre-line font-body text-xs text-text-faint">{exemplar.dcp_excerpt}</p>
                            )}
                            {exemplar.exemplar_brief?.paragraph && (
                                <p className="mt-2 font-body text-sm text-text-muted">{exemplar.exemplar_brief.paragraph}</p>
                            )}
                        </article>
                    ))}
                </div>
            </section>
        </AdminLayout>
    );
}
