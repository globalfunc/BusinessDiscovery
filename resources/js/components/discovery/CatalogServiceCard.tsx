import { ChevronDown, ChevronUp, Plus } from 'lucide-react';
import { createElement, useState } from 'react';

import { serviceIcon } from '@/components/discovery/serviceIcons';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatPriceRange } from '@/lib/currency';
import type { Locale } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export type CatalogService = {
    id: number;
    key: string;
    name: { en: string; bg: string };
    one_liner: { en: string; bg: string };
    base_features: string[];
    saas_eligible: boolean;
    price_min: number | null;
    price_max: number | null;
    recommended: boolean;
};

/**
 * Browsing card for the gated catalog grid (design.md/tech-spec §3.3): icon,
 * name, one-liner, expandable feature list, recommended badge, price tag,
 * and an Add toggle. Once added the item's editable state lives in the
 * shared SelectedServiceCard below — this card just reflects "added" status.
 */
export function CatalogServiceCard({
    t,
    locale,
    service,
    added,
    showPrice,
    onAdd,
    onRemove,
}: {
    t: (key: string, vars?: Record<string, string>) => string;
    locale: Locale;
    service: CatalogService;
    added: boolean;
    showPrice: boolean;
    onAdd: () => void;
    onRemove: () => void;
}) {
    const [expanded, setExpanded] = useState(false);
    const priceLabel = showPrice ? formatPriceRange(service.price_min, service.price_max) : null;

    return (
        <div
            className={cn(
                'flex flex-col gap-2.5 rounded-md border bg-surface p-4',
                added ? 'border-transparent shadow-[0_0_0_1.5px_var(--lb-accent-glow)]' : 'border-line hover:border-line-strong',
            )}
        >
            <div className="flex items-start justify-between gap-2">
                {createElement(serviceIcon(service.key), { className: 'h-5 w-5 shrink-0 text-accent', 'aria-hidden': true })}
                {priceLabel && <span className="font-ui text-xs text-text-muted">{priceLabel}</span>}
            </div>

            {service.recommended && (
                <Badge variant="accent" className="w-fit">
                    {t('phase2.recommendedBadge')}
                </Badge>
            )}

            <div className="flex flex-col gap-0.5">
                <span className="font-ui text-sm font-semibold text-text">{service.name[locale]}</span>
                <span className="font-body text-xs text-text-muted">{service.one_liner[locale]}</span>
            </div>

            <button
                type="button"
                onClick={() => setExpanded((v) => !v)}
                className="flex items-center gap-1 self-start font-ui text-xs text-text-faint hover:text-text"
            >
                {expanded ? <ChevronUp className="h-3.5 w-3.5" /> : <ChevronDown className="h-3.5 w-3.5" />}
                {t('phase2.featuresToggle')}
            </button>

            {expanded && (
                <ul className="flex flex-col gap-1 font-body text-xs text-text-muted">
                    {service.base_features.map((feature) => (
                        <li key={feature}>• {feature}</li>
                    ))}
                </ul>
            )}

            <Button
                type="button"
                variant={added ? 'secondary' : 'default'}
                size="sm"
                onClick={added ? onRemove : onAdd}
                className="mt-1 self-start"
            >
                {!added && <Plus className="h-3.5 w-3.5" />}
                {added ? t('phase2.added') : t('phase2.add')}
            </Button>
        </div>
    );
}
