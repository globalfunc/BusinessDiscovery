import { type ReactNode } from 'react';

import { cn } from '@/lib/utils';

/**
 * Generic selectable card used by the Phase 1 taxonomy picker. Deliberately
 * generic (selected state, badge, title, subtitle) so it can be reused as
 * the base for the shared "selected-service card" in S2.3 (design.md §6.3).
 */
export function SelectableCard({
    selected,
    onSelect,
    title,
    subtitle,
    badge,
    className,
}: {
    selected: boolean;
    onSelect: () => void;
    title: ReactNode;
    subtitle?: ReactNode;
    badge?: ReactNode;
    className?: string;
}) {
    return (
        <button
            type="button"
            onClick={onSelect}
            aria-pressed={selected}
            className={cn(
                'flex flex-col items-start gap-1 rounded-md border bg-surface p-3 text-left transition-colors',
                selected
                    ? 'border-transparent shadow-[0_0_0_1.5px_var(--lb-accent-glow)]'
                    : 'border-line hover:border-line-strong',
                className,
            )}
        >
            {badge && (
                <span className="font-ui text-[10px] font-semibold uppercase tracking-wide text-accent">{badge}</span>
            )}
            <span className="font-ui text-sm font-medium text-text">{title}</span>
            {subtitle && <span className="font-body text-xs text-text-muted">{subtitle}</span>}
        </button>
    );
}
