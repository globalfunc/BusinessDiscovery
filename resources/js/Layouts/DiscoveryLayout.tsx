import { type ReactNode } from 'react';

import { LanguageToggle } from '@/components/discovery/LanguageToggle';
import { PhaseRail, type PhaseItem } from '@/components/discovery/PhaseRail';
import type { Locale } from '@/lib/i18n';

export default function DiscoveryLayout({
    company,
    phases,
    currentPhase,
    visitedPhaseKeys,
    locale,
    children,
    bottomBar,
}: {
    company: string;
    phases: PhaseItem[];
    currentPhase: string;
    visitedPhaseKeys: string[];
    locale: Locale;
    children: ReactNode;
    bottomBar: ReactNode;
}) {
    return (
        <div className="flex min-h-screen flex-col bg-bg">
            <header className="sticky top-0 z-20 border-b border-line bg-surface-glass px-4 py-3 backdrop-blur-2xl">
                <div className="mx-auto flex max-w-[640px] flex-col gap-2">
                    <div className="flex items-center justify-between gap-3">
                        <span className="font-ui text-sm font-semibold tracking-tight text-text">{company}</span>
                        <LanguageToggle locale={locale} />
                    </div>
                    <PhaseRail phases={phases} currentPhase={currentPhase} visitedPhaseKeys={visitedPhaseKeys} />
                </div>
            </header>

            <main className="mx-auto w-full max-w-[640px] flex-1 px-4 py-8">
                <div key={currentPhase} className="animate-rise">
                    {children}
                </div>
            </main>

            {bottomBar}
        </div>
    );
}
