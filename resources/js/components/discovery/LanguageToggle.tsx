import { router } from '@inertiajs/react';

import { cn } from '@/lib/utils';
import type { Locale } from '@/lib/i18n';

export function LanguageToggle({ locale }: { locale: Locale }) {
    const setLocale = (next: Locale) => {
        if (next === locale) return;
        router.post(route('language.set'), { lang: next }, { preserveScroll: true });
    };

    return (
        <div className="flex items-center rounded-full border border-line-strong bg-surface p-0.5 font-ui text-xs">
            {(['bg', 'en'] as const).map((option) => (
                <button
                    key={option}
                    type="button"
                    onClick={() => setLocale(option)}
                    className={cn(
                        'rounded-full px-2.5 py-1 uppercase tracking-wide transition-colors',
                        locale === option ? 'bg-gradient-to-r from-accent to-accent-2 text-[#241305]' : 'text-text-muted hover:text-text',
                    )}
                    aria-pressed={locale === option}
                >
                    {option}
                </button>
            ))}
        </div>
    );
}
