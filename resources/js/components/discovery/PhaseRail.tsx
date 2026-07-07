import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';

import { cn } from '@/lib/utils';

export type PhaseItem = {
    key: string;
    label: string;
};

export function PhaseRail({
    phases,
    currentPhase,
    visitedPhaseKeys,
}: {
    phases: PhaseItem[];
    currentPhase: string;
    visitedPhaseKeys: string[];
}) {
    return (
        <div className="flex items-center gap-1.5 overflow-x-auto py-1" role="tablist" aria-label="Discovery phases">
            {phases.map((phase) => {
                const isActive = phase.key === currentPhase;
                const isVisited = visitedPhaseKeys.includes(phase.key) && !isActive;
                const isReachable = visitedPhaseKeys.includes(phase.key);

                const dot = (
                    <span
                        className={cn(
                            'flex h-6 w-6 shrink-0 items-center justify-center rounded-full border font-ui text-[11px] font-semibold transition-colors',
                            isActive && 'border-transparent bg-gradient-to-r from-accent to-accent-2 text-[#241305] shadow-[0_0_0_3px_var(--lb-accent-glow)]',
                            isVisited && 'border-teal/40 bg-teal/15 text-teal',
                            !isActive && !isVisited && 'border-line-strong text-text-faint',
                        )}
                    >
                        {isVisited ? <Check className="h-3.5 w-3.5" /> : phases.findIndex((p) => p.key === phase.key) + 1}
                    </span>
                );

                if (!isReachable) {
                    return (
                        <span
                            key={phase.key}
                            aria-disabled="true"
                            title={phase.label}
                            className="hidden shrink-0 items-center gap-1.5 rounded-full px-1 text-text-faint sm:flex"
                        >
                            {dot}
                        </span>
                    );
                }

                return (
                    <Link
                        key={phase.key}
                        href={route('discovery.show', { phase: phase.key })}
                        role="tab"
                        aria-selected={isActive}
                        title={phase.label}
                        className="flex shrink-0 items-center gap-1.5 rounded-full px-1 hover:opacity-90"
                    >
                        {dot}
                        <span
                            className={cn(
                                'hidden font-ui text-xs md:inline',
                                isActive ? 'font-bold text-text' : 'text-text-muted',
                            )}
                        >
                            {phase.label}
                        </span>
                    </Link>
                );
            })}
        </div>
    );
}
