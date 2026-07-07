import { Sparkles } from 'lucide-react';

import { ChipInput } from '@/components/discovery/ChipInput';
import { SelectableCard } from '@/components/discovery/SelectableCard';
import { Button } from '@/components/ui/button';
import { useAutosaveField } from '@/hooks/useAutosaveField';

const CONTENT_NEED_KEYS = ['copywriting', 'photo_video', 'menu_pricelist', 'multilingual', 'blog_news'] as const;
const PLATFORM_KEYS = ['facebook', 'instagram', 'tiktok', 'linkedin', 'youtube', 'google_business'] as const;
const CADENCE_KEYS = ['none', 'weekly', 'few_times_month', 'monthly', 'unsure'] as const;
const INTEREST_KEYS = ['interested', 'maybe', 'not_interested'] as const;

type Props = {
    t: (key: string, vars?: Record<string, string>) => string;
    answers: Record<string, unknown>;
};

function toStringArray(value: unknown): string[] {
    return Array.isArray(value) ? (value as string[]) : [];
}

export function Phase4ContentSocial({ t, answers }: Props) {
    const contentNeeds = useAutosaveField<string[]>('phase_4', 'content_needs', toStringArray(answers.content_needs));
    const socialPlatforms = useAutosaveField<string[]>('phase_4', 'social_platforms', toStringArray(answers.social_platforms));
    const otherPlatforms = useAutosaveField<string[]>('phase_4', 'other_platforms', toStringArray(answers.other_platforms));
    const postingCadence = useAutosaveField<string | null>(
        'phase_4',
        'posting_cadence',
        typeof answers.posting_cadence === 'string' ? answers.posting_cadence : null,
    );
    const contentAssistInterest = useAutosaveField<string | null>(
        'phase_4',
        'content_assist_interest',
        typeof answers.content_assist_interest === 'string' ? answers.content_assist_interest : null,
    );

    const toggle = (field: ReturnType<typeof useAutosaveField<string[]>>, key: string) => {
        field.setValue(field.value.includes(key) ? field.value.filter((k) => k !== key) : [...field.value, key]);
    };

    return (
        <div className="flex flex-col gap-6">
            <div className="flex items-start justify-between gap-3">
                <p className="font-ui text-xs font-semibold uppercase tracking-wide text-text-muted">{t('phase4.contentHeading')}</p>
                <Button type="button" variant="secondary" size="sm" disabled className="shrink-0 gap-1.5 border-accent/40 text-accent">
                    <Sparkles className="h-3.5 w-3.5" />
                    {t('phase4.aiSuggestionsCta')}
                </Button>
            </div>
            <p className="-mt-4 font-body text-xs text-text-faint">{t('phase4.aiSuggestionsComingSoon')}</p>

            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                {CONTENT_NEED_KEYS.map((key) => (
                    <SelectableCard
                        key={key}
                        selected={contentNeeds.value.includes(key)}
                        onSelect={() => toggle(contentNeeds, key)}
                        title={t(`phase4.contentNeeds.${key}`)}
                    />
                ))}
            </div>

            <div className="flex flex-col gap-3">
                <p className="font-ui text-xs font-semibold uppercase tracking-wide text-text-muted">{t('phase4.socialHeading')}</p>

                <p className="font-body text-xs text-text-muted">{t('phase4.socialPlatformsLabel')}</p>
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    {PLATFORM_KEYS.map((key) => (
                        <SelectableCard
                            key={key}
                            selected={socialPlatforms.value.includes(key)}
                            onSelect={() => toggle(socialPlatforms, key)}
                            title={t(`phase4.platforms.${key}`)}
                        />
                    ))}
                </div>

                <div className="flex flex-col gap-1.5">
                    <p className="font-body text-xs text-text-muted">{t('phase4.otherPlatformsLabel')}</p>
                    <ChipInput
                        values={otherPlatforms.value}
                        onChange={(values) => otherPlatforms.setValue(values)}
                        placeholder={t('phase4.otherPlatformsPlaceholder')}
                    />
                </div>

                <div className="flex flex-col gap-1.5">
                    <p className="font-body text-xs text-text-muted">{t('phase4.cadenceLabel')}</p>
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        {CADENCE_KEYS.map((key) => (
                            <SelectableCard
                                key={key}
                                selected={postingCadence.value === key}
                                onSelect={() => postingCadence.setValue(postingCadence.value === key ? null : key)}
                                title={t(`phase4.cadence.${key}`)}
                            />
                        ))}
                    </div>
                </div>

                <div className="flex flex-col gap-1.5">
                    <p className="font-body text-xs text-text-muted">{t('phase4.contentAssistLabel')}</p>
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        {INTEREST_KEYS.map((key) => (
                            <SelectableCard
                                key={key}
                                selected={contentAssistInterest.value === key}
                                onSelect={() =>
                                    contentAssistInterest.setValue(contentAssistInterest.value === key ? null : key)
                                }
                                title={t(`phase4.interest.${key}`)}
                            />
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}
