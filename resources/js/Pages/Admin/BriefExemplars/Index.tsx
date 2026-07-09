import { Head } from '@inertiajs/react';

import { Badge } from '@/components/ui/badge';
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

/**
 * Read-only S5.6 view of the advisory-brief exemplar library — the
 * hand-written gold pairs injected as context into the content/social and
 * growth suggestion calls. Editing arrives with S5.7's calibration surface.
 */
export default function Index({ exemplars }: { exemplars: Exemplar[] }) {
    return (
        <AdminLayout>
            <Head title="Brief exemplars" />

            <section className="animate-rise rounded-section border border-line-strong bg-surface-glass p-8 backdrop-blur-2xl">
                <p className="eyebrow text-accent">Admin</p>
                <h1 className="mt-2 font-display text-3xl font-semibold text-text">Brief exemplars</h1>
                <p className="mt-2 max-w-2xl font-body text-sm text-text-muted">
                    Hand-written gold pairs (context → advisory brief) the AI sees when writing a &ldquo;note from
                    the studio&rdquo; for the content and growth phases. The most relevant pairs are selected per
                    call by tag match. Read-only for now — the editor ships with the S5.7 calibration tooling.
                </p>
            </section>

            <div className="mt-6 flex flex-col gap-4">
                {exemplars.length === 0 && (
                    <p className="rounded-admin border border-line bg-surface px-4 py-8 text-center font-body text-sm text-text-faint">
                        No exemplars yet — run the BriefExemplarSeeder.
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
