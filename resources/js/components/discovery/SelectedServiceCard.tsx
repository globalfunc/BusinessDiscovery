import { Star, X } from 'lucide-react';
import { createElement, useEffect, useRef, useState } from 'react';

import { ChipInput } from '@/components/discovery/ChipInput';
import { serviceIcon } from '@/components/discovery/serviceIcons';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { formatPriceRange } from '@/lib/currency';

export type SelectedServiceRecord = {
    id: number;
    service_id: number | null;
    custom: boolean;
    name: string | null;
    description: string | null;
    features: string[];
    priority: boolean;
    note: string | null;
    origin: 'catalog' | 'bo_custom' | 'ai_suggestion';
    reference_links: string[];
    price_min: number | null;
    price_max: number | null;
};

/**
 * The one shared "selected-service card" (design.md §6.3): renders
 * identically whether the item came from the gated catalog, an accepted
 * AI suggestion (S3.2), or a BO-authored custom entry. Feature list is an
 * editable chip list, priority is a star toggle, and the note field is
 * always visible (not collapsed) — this is what satisfies "append free
 * text to already-selected services" uniformly.
 */
export function SelectedServiceCard({
    t,
    record,
    serviceKey,
    displayName,
    displaySubtitle,
    showPrice,
    onFeaturesChange,
    onPriorityToggle,
    onNoteChange,
    onRemove,
}: {
    t: (key: string, vars?: Record<string, string>) => string;
    record: SelectedServiceRecord;
    serviceKey?: string | null;
    displayName: string;
    displaySubtitle?: string | null;
    showPrice: boolean;
    onFeaturesChange: (features: string[]) => void;
    onPriorityToggle: () => void;
    onNoteChange: (note: string) => void;
    onRemove: () => void;
}) {
    const priceLabel = showPrice ? formatPriceRange(record.price_min, record.price_max) : null;

    // The parent list keys each card by record.id, so this component remounts
    // (fresh state) whenever it switches to a different selected service.
    const [note, setNote] = useState(record.note ?? '');
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const skipNext = useRef(true);

    useEffect(() => {
        if (skipNext.current) {
            skipNext.current = false;
            return;
        }
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => onNoteChange(note), 600);
        return () => {
            if (debounceRef.current) clearTimeout(debounceRef.current);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [note]);

    return (
        <div className="flex flex-col gap-3 rounded-md border border-transparent bg-surface p-4 shadow-[0_0_0_1.5px_var(--lb-accent-glow)]">
            <div className="flex items-start justify-between gap-3">
                <div className="flex items-start gap-3">
                    {createElement(serviceIcon(serviceKey), {
                        className: 'mt-0.5 h-5 w-5 shrink-0 text-accent',
                        'aria-hidden': true,
                    })}
                    <div className="flex flex-col gap-0.5">
                        <span className="font-ui text-sm font-semibold text-text">{displayName}</span>
                        {displaySubtitle && <span className="font-body text-xs text-text-muted">{displaySubtitle}</span>}
                        {record.origin === 'bo_custom' && (
                            <span className="font-ui text-[10px] font-semibold uppercase tracking-wide text-text-faint">
                                {t('phase2.customBadge')}
                            </span>
                        )}
                    </div>
                </div>

                <div className="flex shrink-0 items-center gap-2">
                    {priceLabel && <span className="font-ui text-xs text-text-muted">{priceLabel}</span>}
                    <button
                        type="button"
                        onClick={onPriorityToggle}
                        aria-pressed={record.priority}
                        aria-label={t('phase2.priorityToggle')}
                        className={cn(
                            'flex h-8 w-8 items-center justify-center rounded-md border transition-colors',
                            record.priority
                                ? 'border-accent/40 text-accent'
                                : 'border-line-strong text-text-faint hover:text-text',
                        )}
                    >
                        <Star className="h-4 w-4" fill={record.priority ? 'currentColor' : 'none'} />
                    </button>
                    <button
                        type="button"
                        onClick={onRemove}
                        aria-label={t('phase2.remove')}
                        className="flex h-8 w-8 items-center justify-center rounded-md border border-line-strong text-text-faint hover:border-red/40 hover:text-red"
                    >
                        <X className="h-4 w-4" />
                    </button>
                </div>
            </div>

            <ChipInput values={record.features} onChange={onFeaturesChange} placeholder={t('phase2.featurePlaceholder')} />

            <div className="flex flex-col gap-1.5">
                <Textarea
                    value={note}
                    onChange={(e) => setNote(e.target.value)}
                    placeholder={t('phase2.notePlaceholder')}
                    rows={2}
                    className="text-xs"
                />
            </div>
        </div>
    );
}
