import { AcceptedSuggestionList, type AcceptedSuggestionCard } from '@/components/discovery/AcceptedSuggestionList';
import { ChipInput } from '@/components/discovery/ChipInput';
import { SelectableCard } from '@/components/discovery/SelectableCard';
import type { SuggestionCardData } from '@/components/discovery/SuggestionCard';
import { SuggestionPanel } from '@/components/discovery/SuggestionPanel';
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
    // Accepted content/social plays persist as a structured answer (no catalog
    // link — they aren't selected services); each keeps its own note (§6.3).
    const acceptedSuggestions = useAutosaveField<AcceptedSuggestionCard[]>(
        'phase_4',
        'accepted_suggestions',
        Array.isArray(answers.accepted_suggestions) ? (answers.accepted_suggestions as AcceptedSuggestionCard[]) : [],
    );

    const acceptSuggestion = (card: SuggestionCardData) => {
        acceptedSuggestions.setValue([...acceptedSuggestions.value, { ...card, note: '' }]);
    };

    const updateAcceptedNote = (index: number, note: string) => {
        acceptedSuggestions.setValue(acceptedSuggestions.value.map((c, i) => (i === index ? { ...c, note } : c)));
    };

    const removeAccepted = (index: number) => {
        acceptedSuggestions.setValue(acceptedSuggestions.value.filter((_, i) => i !== index));
    };

    const toggle = (field: ReturnType<typeof useAutosaveField<string[]>>, key: string) => {
        field.setValue(field.value.includes(key) ? field.value.filter((k) => k !== key) : [...field.value, key]);
    };

    return (
        <div className="flex flex-col gap-6">
            <p className="font-ui text-xs font-semibold uppercase tracking-wide text-text-muted">{t('phase4.contentHeading')}</p>

            <SuggestionPanel
                t={t}
                endpoint={route('discovery.suggest.content_social')}
                ctaLabel={t('phase4.aiSuggestionsCta')}
                onAccept={acceptSuggestion}
            />

            <AcceptedSuggestionList
                t={t}
                heading={t('phase4.acceptedIdeasHeading')}
                cards={acceptedSuggestions.value}
                onNoteChange={updateAcceptedNote}
                onRemove={removeAccepted}
            />

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
