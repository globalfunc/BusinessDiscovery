import { X } from 'lucide-react';

import type { SuggestionCardData } from '@/components/discovery/SuggestionCard';
import { Badge } from '@/components/ui/badge';
import { Textarea } from '@/components/ui/textarea';

/** A suggestion card the BO accepted, persisted with their own free-text note (§6.3). */
export type AcceptedSuggestionCard = SuggestionCardData & { note: string };

/**
 * The list of non-catalog suggestion cards the BO has accepted for a phase or
 * module (branding, content/social, each growth module). Each keeps an always-
 * visible free-text note (§6.3) and a soft remove. Accepted cards persist as a
 * jsonb `accepted_suggestions` structured answer — there's no catalog link, so
 * they never become selected_services rows.
 */
export function AcceptedSuggestionList({
    t,
    heading,
    cards,
    onNoteChange,
    onRemove,
}: {
    t: (key: string, vars?: Record<string, string>) => string;
    heading: string;
    cards: AcceptedSuggestionCard[];
    onNoteChange: (index: number, note: string) => void;
    onRemove: (index: number) => void;
}) {
    if (cards.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-3">
            <p className="font-ui text-xs font-semibold uppercase tracking-wide text-text-muted">{heading}</p>
            {cards.map((card, index) => (
                <div
                    key={index}
                    className="flex flex-col gap-2 rounded-bo border border-transparent bg-surface p-4 shadow-[0_0_0_1.5px_var(--lb-accent-glow)]"
                >
                    <div className="flex items-start justify-between gap-2">
                        <div className="flex flex-col gap-0.5">
                            <span className="font-ui text-sm font-semibold text-text">{card.title}</span>
                            {card.summary && <span className="font-body text-xs text-text-muted">{card.summary}</span>}
                        </div>
                        <div className="flex items-center gap-2">
                            {card.tags.slice(0, 2).map((tag) => (
                                <Badge key={tag} variant="muted">
                                    {tag.replace(/_/g, ' ')}
                                </Badge>
                            ))}
                            <button
                                type="button"
                                onClick={() => onRemove(index)}
                                aria-label={t('phase2.remove')}
                                className="flex h-8 w-8 items-center justify-center rounded-md border border-line-strong text-text-faint hover:border-red/40 hover:text-red"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                    {card.features.length > 0 && (
                        <ul className="flex flex-col gap-1 font-body text-xs text-text-muted">
                            {card.features.map((feature, i) => (
                                <li key={i}>• {feature}</li>
                            ))}
                        </ul>
                    )}
                    <Textarea
                        value={card.note}
                        onChange={(e) => onNoteChange(index, e.target.value)}
                        placeholder={t('suggestions.notePlaceholder')}
                        rows={2}
                        className="text-xs"
                    />
                </div>
            ))}
        </div>
    );
}
