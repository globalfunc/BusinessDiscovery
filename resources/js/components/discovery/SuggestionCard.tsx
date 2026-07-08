import { Check, Plus, Sparkles, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

/**
 * Maps 1:1 to the §7.4 Suggestion Card JSON contract. Both live AI cards and
 * static preset-fallback cards render through this component.
 */
export type SuggestionCardData = {
    title: string;
    summary: string;
    features: string[];
    rationale: string;
    tags: string[];
    saas_eligible: boolean;
    related_catalog_key: string | null;
};

/**
 * The core AI touchpoint (design.md §6.2). Unselected: solid surface, line
 * border, ✨ Suggested eyebrow. Accepted: accent-glow border, filled "Added"
 * pill, and — when `showNoteOnAccept` — an inline free-text note field
 * (Phase 3 branding keeps its note here; Phase 2 services carry it on the
 * selected-service card below instead). "Not for me" is a soft dismiss the
 * parent panel makes undo-able.
 */
export function SuggestionCard({
    t,
    card,
    accepted,
    busy,
    showNoteOnAccept = false,
    note = '',
    onAccept,
    onDismiss,
    onNoteChange,
}: {
    t: (key: string, vars?: Record<string, string>) => string;
    card: SuggestionCardData;
    accepted: boolean;
    busy?: boolean;
    showNoteOnAccept?: boolean;
    note?: string;
    onAccept: () => void;
    onDismiss: () => void;
    onNoteChange?: (note: string) => void;
}) {
    const [localNote, setLocalNote] = useState(note);
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const skipNext = useRef(true);

    useEffect(() => {
        if (skipNext.current) {
            skipNext.current = false;
            return;
        }
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => onNoteChange?.(localNote), 600);
        return () => {
            if (debounceRef.current) clearTimeout(debounceRef.current);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [localNote]);

    return (
        <div
            className={cn(
                'flex flex-col gap-3 rounded-bo border bg-surface p-4 transition-shadow',
                accepted ? 'border-transparent shadow-[0_0_0_1.5px_var(--lb-accent-glow)]' : 'border-line',
            )}
        >
            <div className="flex items-start justify-between gap-2">
                {accepted ? (
                    <Badge variant="accent" className="gap-1 border-accent/40 bg-accent/10">
                        <Check className="h-3 w-3" />
                        {t('suggestions.added')}
                    </Badge>
                ) : (
                    <span className="inline-flex items-center gap-1 font-ui text-[11px] font-semibold uppercase tracking-[0.14em] text-accent">
                        <Sparkles className="h-3.5 w-3.5" />
                        {t('suggestions.suggested')}
                    </span>
                )}
                {card.tags.length > 0 && (
                    <div className="flex flex-wrap justify-end gap-1">
                        {card.tags.slice(0, 3).map((tag) => (
                            <Badge key={tag} variant="muted">
                                {tag.replace(/_/g, ' ')}
                            </Badge>
                        ))}
                    </div>
                )}
            </div>

            <div className="flex flex-col gap-1">
                <h3 className="font-ui text-base font-semibold text-text">{card.title}</h3>
                <p className="font-body text-sm text-text-muted">{card.summary}</p>
            </div>

            <ul className="flex flex-col gap-1 font-body text-sm text-text">
                {card.features.map((feature, i) => (
                    <li key={i} className="flex gap-2">
                        <span aria-hidden className="text-accent">
                            •
                        </span>
                        <span>{feature}</span>
                    </li>
                ))}
            </ul>

            {card.rationale && (
                <p className="border-t border-line pt-3 font-body text-xs italic text-text-faint">
                    <span aria-hidden>✎ </span>
                    {card.rationale}
                </p>
            )}

            {accepted && showNoteOnAccept && (
                <Textarea
                    value={localNote}
                    onChange={(e) => setLocalNote(e.target.value)}
                    placeholder={t('suggestions.notePlaceholder')}
                    rows={2}
                    className="text-xs"
                />
            )}

            <div className="mt-1 flex items-center gap-2">
                <Button
                    type="button"
                    variant={accepted ? 'secondary' : 'default'}
                    size="sm"
                    disabled={busy}
                    onClick={accepted ? onDismiss : onAccept}
                    className="gap-1.5"
                >
                    {accepted ? <Check className="h-3.5 w-3.5" /> : <Plus className="h-3.5 w-3.5" />}
                    {accepted ? t('suggestions.added') : t('suggestions.accept')}
                </Button>
                {!accepted && (
                    <Button type="button" variant="ghost" size="sm" disabled={busy} onClick={onDismiss} className="gap-1.5 text-text-faint">
                        <X className="h-3.5 w-3.5" />
                        {t('suggestions.dismiss')}
                    </Button>
                )}
            </div>
        </div>
    );
}
