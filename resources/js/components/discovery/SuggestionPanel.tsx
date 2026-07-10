import { PenLine, RotateCw, Sparkles } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { SuggestionCard, type SuggestionCardData } from '@/components/discovery/SuggestionCard';
import { Button } from '@/components/ui/button';

type Status = 'idle' | 'loading' | 'ready' | 'unavailable';

/** S5.6 advisory brief — optional §7.4 addition on the suggest response. */
export type AdvisoryBrief = {
    paragraph: string;
    bullets: string[];
};

const STATUS_LINE_KEYS = ['suggestions.loading1', 'suggestions.loading2', 'suggestions.loading3'] as const;

/**
 * Wraps the ✨ Get AI suggestions touchpoint for one phase (design.md §6.2/§6.4).
 * Fetches from the synchronous suggestion endpoint (25s budget, §7.1), renders
 * a designed loading state (skeleton outlines + rotating status line, §5.3),
 * then the Suggestion Card grid. On an "unavailable" response it shows the
 * graceful fallback banner and any static preset cards the server returned
 * (§7.7) — progress is never blocked. Accept is delegated to `onAccept`;
 * dismiss is soft with an inline undo (§1.4.2).
 */
export function SuggestionPanel({
    t,
    endpoint,
    ctaLabel,
    showNoteOnAccept = false,
    onAccept,
    onNoteChange,
}: {
    t: (key: string, vars?: Record<string, string>) => string;
    endpoint: string;
    ctaLabel: string;
    showNoteOnAccept?: boolean;
    onAccept: (card: SuggestionCardData, index: number) => Promise<void> | void;
    onNoteChange?: (index: number, note: string) => void;
}) {
    const [status, setStatus] = useState<Status>('idle');
    const [cards, setCards] = useState<SuggestionCardData[]>([]);
    // Kept independent from `cards` on purpose: the brief renders from its
    // own state so S5.7's graded async-reveal can set it from a second
    // request without touching the cards path.
    const [brief, setBrief] = useState<AdvisoryBrief | null>(null);
    const [accepted, setAccepted] = useState<Set<number>>(new Set());
    const [dismissed, setDismissed] = useState<Set<number>>(new Set());
    const [busyIndex, setBusyIndex] = useState<number | null>(null);
    const [statusLine, setStatusLine] = useState(0);
    const rotateRef = useRef<ReturnType<typeof setInterval> | null>(null);
    // Regeneration guard for the async brief reveal: a reveal response only
    // lands if no newer fetch has started since it was requested.
    const fetchSeqRef = useRef(0);

    useEffect(() => {
        if (status !== 'loading') {
            if (rotateRef.current) clearInterval(rotateRef.current);
            return;
        }
        rotateRef.current = setInterval(() => setStatusLine((n) => (n + 1) % STATUS_LINE_KEYS.length), 2200);
        return () => {
            if (rotateRef.current) clearInterval(rotateRef.current);
        };
    }, [status]);

    const fetchSuggestions = () => {
        const seq = ++fetchSeqRef.current;
        setStatus('loading');
        setAccepted(new Set());
        setDismissed(new Set());
        setBrief(null);
        setStatusLine(0);
        window.axios
            .post(endpoint)
            .then(({ data }) => {
                setCards(Array.isArray(data.suggestions) ? data.suggestions : []);
                setStatus(data.status === 'ok' ? 'ready' : 'unavailable');
                // S5.7 async-reveal: cards are never blocked on the brief —
                // grading runs as a second request and the brief appears a
                // beat later only if it clears the judge; a failed or slow
                // grade simply means no note shows up.
                if (typeof data.brief_url === 'string') {
                    window.axios
                        .post(data.brief_url)
                        .then(({ data: reveal }) => {
                            if (seq === fetchSeqRef.current && reveal.brief && typeof reveal.brief.paragraph === 'string') {
                                setBrief({ paragraph: reveal.brief.paragraph, bullets: Array.isArray(reveal.brief.bullets) ? reveal.brief.bullets : [] });
                            }
                        })
                        .catch(() => {});
                }
            })
            .catch(() => {
                setCards([]);
                setBrief(null);
                setStatus('unavailable');
            });
    };

    const acceptCard = async (index: number) => {
        setBusyIndex(index);
        try {
            await onAccept(cards[index], index);
            setAccepted((prev) => new Set(prev).add(index));
        } finally {
            setBusyIndex(null);
        }
    };

    const dismissCard = (index: number) => {
        setDismissed((prev) => new Set(prev).add(index));
        if (accepted.has(index)) {
            setAccepted((prev) => {
                const next = new Set(prev);
                next.delete(index);
                return next;
            });
        }
    };

    const undoDismiss = (index: number) => {
        setDismissed((prev) => {
            const next = new Set(prev);
            next.delete(index);
            return next;
        });
    };

    const dismissedIndexes = [...dismissed];
    const visibleCards = cards.map((card, index) => ({ card, index })).filter(({ index }) => !dismissed.has(index));

    if (status === 'idle') {
        return (
            <div className="flex flex-wrap items-center gap-3">
                <Button type="button" variant="secondary" size="sm" onClick={fetchSuggestions} className="gap-1.5 border-accent/40 text-accent">
                    <Sparkles className="h-3.5 w-3.5" />
                    {ctaLabel}
                </Button>
                <p className="font-body text-xs text-text-faint">{t('suggestions.optIn')}</p>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-4 rounded-bo border border-line bg-surface-2/40 p-4">
            <div className="flex items-center justify-between gap-3">
                <span className="inline-flex items-center gap-1.5 font-ui text-sm font-semibold text-text">
                    <Sparkles className="h-4 w-4 text-accent" />
                    {ctaLabel}
                </span>
                {status !== 'loading' && (
                    <div className="flex items-center gap-2">
                        <Button type="button" variant="ghost" size="sm" onClick={fetchSuggestions} className="gap-1.5 text-text-muted">
                            <RotateCw className="h-3.5 w-3.5" />
                            {t('suggestions.regenerate')}
                        </Button>
                        <Button type="button" variant="ghost" size="sm" onClick={() => setStatus('idle')} className="text-text-faint">
                            {t('suggestions.skip')}
                        </Button>
                    </div>
                )}
            </div>

            {status === 'loading' && (
                <div className="flex flex-col gap-3">
                    <p className="font-body text-sm text-text-muted" aria-live="polite">
                        {t(STATUS_LINE_KEYS[statusLine])}
                    </p>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        {[0, 1, 2, 3].map((i) => (
                            <div
                                key={i}
                                className="h-40 animate-pulse rounded-bo border border-line bg-surface shadow-[0_0_0_1.5px_var(--lb-accent-glow)]"
                            />
                        ))}
                    </div>
                </div>
            )}

            {status === 'unavailable' && (
                <p className="rounded-md border border-line bg-surface px-3 py-2 font-body text-sm text-text-muted">
                    {t('suggestions.unavailable')}
                </p>
            )}

            {status === 'ready' && brief && (
                <aside className="rounded-bo border border-accent/30 bg-accent/5 p-4">
                    <span className="inline-flex items-center gap-1.5 font-ui text-[11px] font-semibold uppercase tracking-[0.14em] text-accent">
                        <PenLine className="h-3.5 w-3.5" />
                        {t('suggestions.briefTitle')}
                    </span>
                    <p className="mt-2 font-body text-sm text-text">{brief.paragraph}</p>
                    {brief.bullets.length > 0 && (
                        <ul className="mt-2 flex flex-col gap-1 font-body text-sm text-text-muted">
                            {brief.bullets.map((bullet, i) => (
                                <li key={i} className="flex gap-2">
                                    <span aria-hidden className="text-accent">
                                        •
                                    </span>
                                    <span>{bullet}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </aside>
            )}

            {(status === 'ready' || status === 'unavailable') && visibleCards.length > 0 && (
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    {visibleCards.map(({ card, index }) => (
                        <SuggestionCard
                            key={index}
                            t={t}
                            card={card}
                            accepted={accepted.has(index)}
                            busy={busyIndex === index}
                            showNoteOnAccept={showNoteOnAccept}
                            onAccept={() => acceptCard(index)}
                            onDismiss={() => dismissCard(index)}
                            onNoteChange={onNoteChange ? (note) => onNoteChange(index, note) : undefined}
                        />
                    ))}
                </div>
            )}

            {dismissedIndexes.length > 0 && (
                <div className="flex flex-col gap-1">
                    {dismissedIndexes.map((index) => (
                        <div
                            key={index}
                            className="flex items-center justify-between gap-2 rounded-md border border-dashed border-line px-3 py-1.5 font-body text-xs text-text-faint"
                        >
                            <span>{t('suggestions.dismissedNotice', { title: cards[index]?.title ?? '' })}</span>
                            <button type="button" onClick={() => undoDismiss(index)} className="font-ui font-semibold text-accent hover:underline">
                                {t('suggestions.undo')}
                            </button>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
