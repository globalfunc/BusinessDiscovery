import { useEffect, useRef, useState } from 'react';
import { CheckCircle2, Loader2, RefreshCw, Sparkles } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { SpecMarkdownRenderer } from '@/components/spec/SpecMarkdownRenderer';

export type SpecDocumentPayload = {
    version: number;
    markdown: string;
    generated_by: 'ai' | 'fallback';
    change_summary: string | null;
    created_at: string;
};

/**
 * The real §3.8 Review surface: serves the latest stored spec version, fires
 * spec.compile on first open (and on Regenerate), and runs the amend loop —
 * instruction → revised version + change summary. Compile always yields a
 * document (server falls back to the deterministic renderer when AI is
 * unavailable); amend can fail, in which case the current version stays live
 * and an "unavailable" notice appears — submission is never blocked.
 */
export function ReviewPreview({
    t,
    initialDocument,
    stale,
}: {
    t: (key: string, vars?: Record<string, string>) => string;
    initialDocument: SpecDocumentPayload | null;
    stale: boolean;
}) {
    const [doc, setDoc] = useState<SpecDocumentPayload | null>(initialDocument);
    const [compiling, setCompiling] = useState(false);
    const [compileFailed, setCompileFailed] = useState(false);
    const [isStale, setIsStale] = useState(stale);
    const [amendInstruction, setAmendInstruction] = useState('');
    const [amending, setAmending] = useState(false);
    const [amendNotice, setAmendNotice] = useState<'applied' | 'unavailable' | null>(null);
    const compileRequested = useRef(false);

    const compile = () => {
        setCompiling(true);
        setCompileFailed(false);
        setAmendNotice(null);
        window.axios
            .post(route('discovery.spec.compile'))
            .then(({ data }) => {
                setDoc(data.document as SpecDocumentPayload);
                setIsStale(false);
            })
            .catch(() => setCompileFailed(true))
            .finally(() => setCompiling(false));
    };

    useEffect(() => {
        // §7.2: Review-screen open triggers spec.compile when no version exists.
        if (doc === null && !compileRequested.current) {
            compileRequested.current = true;
            compile();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const applyAmendment = () => {
        if (amendInstruction.trim() === '') return;
        setAmending(true);
        setAmendNotice(null);
        window.axios
            .post(route('discovery.spec.amend'), { instruction: amendInstruction.trim() })
            .then(({ data }) => {
                if (data.status === 'ok') {
                    setDoc(data.document as SpecDocumentPayload);
                    setAmendInstruction('');
                    setAmendNotice('applied');
                } else {
                    setAmendNotice('unavailable');
                }
            })
            .catch(() => setAmendNotice('unavailable'))
            .finally(() => setAmending(false));
    };

    if (doc === null) {
        return (
            <div className="flex flex-col items-center gap-3 rounded-md border border-line bg-surface-2 p-10 text-center">
                {compileFailed ? (
                    <>
                        <p className="font-body text-sm text-text-muted">{t('review.compileFailed')}</p>
                        <Button type="button" variant="secondary" size="sm" onClick={compile} disabled={compiling}>
                            <RefreshCw className="h-3.5 w-3.5" />
                            {t('review.retryCompile')}
                        </Button>
                    </>
                ) : (
                    <>
                        <Loader2 className="h-6 w-6 animate-spin text-accent" />
                        <p className="font-body text-sm text-text-muted">{t('review.compiling')}</p>
                    </>
                )}
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="font-ui text-xs text-text-faint">
                    {t('review.versionLabel', { version: String(doc.version) })}
                    {doc.generated_by === 'fallback' && <> · {t('review.fallbackNote')}</>}
                </p>
                <Button type="button" variant="ghost" size="sm" onClick={compile} disabled={compiling || amending} className="gap-1.5">
                    <RefreshCw className={`h-3.5 w-3.5 ${compiling ? 'animate-spin' : ''}`} />
                    {compiling ? t('review.compiling') : t('review.regenerate')}
                </Button>
            </div>

            {isStale && (
                <div className="rounded-md border border-accent/40 bg-accent/5 p-3 font-body text-xs text-text-muted">
                    {t('review.staleNotice')}
                </div>
            )}

            <div className="rounded-md border border-line bg-surface-2 p-4">
                <SpecMarkdownRenderer markdown={doc.markdown} />
            </div>

            <div className="flex flex-col gap-2 rounded-md border border-line bg-surface p-4">
                <p className="font-ui text-xs font-semibold uppercase tracking-wide text-text-muted">{t('review.amendHeading')}</p>
                <Textarea
                    value={amendInstruction}
                    onChange={(e) => setAmendInstruction(e.target.value)}
                    placeholder={t('review.amendPlaceholder')}
                    rows={3}
                    disabled={amending}
                />
                <div className="flex items-center justify-between gap-3">
                    {amendNotice === 'applied' && doc.change_summary ? (
                        <p className="flex items-start gap-1.5 font-body text-xs text-teal">
                            <CheckCircle2 className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                            <span>{doc.change_summary}</span>
                        </p>
                    ) : amendNotice === 'unavailable' ? (
                        <p className="font-body text-xs text-text-faint">{t('review.amendUnavailable')}</p>
                    ) : (
                        <p className="font-body text-xs text-text-faint">{t('review.amendHint')}</p>
                    )}
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        onClick={applyAmendment}
                        disabled={amending || compiling || amendInstruction.trim() === ''}
                        className="shrink-0 gap-1.5 border-accent/40 text-accent"
                    >
                        {amending ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Sparkles className="h-3.5 w-3.5" />}
                        {amending ? t('review.amendApplying') : t('review.amendCta')}
                    </Button>
                </div>
            </div>
        </div>
    );
}
