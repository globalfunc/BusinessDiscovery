import { AcceptedSuggestionList, type AcceptedSuggestionCard } from '@/components/discovery/AcceptedSuggestionList';
import { SelectableCard } from '@/components/discovery/SelectableCard';
import type { SuggestionCardData } from '@/components/discovery/SuggestionCard';
import { SuggestionPanel } from '@/components/discovery/SuggestionPanel';
import { useAutosaveField } from '@/hooks/useAutosaveField';

const MODULES = [
    { key: 'notifications', optionKeys: ['in_app', 'email', 'sms', 'messaging_app'] },
    {
        key: 'marketing',
        optionKeys: [
            'email_campaigns',
            'newsletters',
            'bulk_discounts',
            'gift_vouchers',
            'review_collection',
            'referral_rewards',
            'winback_campaigns',
        ],
    },
    { key: 'leadgen', optionKeys: ['map_local_search', 'social_groups', 'partnerships', 'directories'] },
    {
        key: 'admin_ops',
        optionKeys: ['staff_scheduling', 'crm_lite', 'invoicing_quotes', 'document_assistant', 'chatbot_assistant', 'dashboards'],
    },
] as const;

const LEADGEN_INTEREST_KEYS = ['interested', 'maybe', 'not_interested'] as const;

type Props = {
    t: (key: string, vars?: Record<string, string>) => string;
    answers: Record<string, unknown>;
};

function toStringArray(value: unknown): string[] {
    return Array.isArray(value) ? (value as string[]) : [];
}

export function Phase5GrowthOperations({ t, answers }: Props) {
    const enabledModules = useAutosaveField<string[]>('phase_5', 'enabled_modules', toStringArray(answers.enabled_modules));
    const notificationOptions = useAutosaveField<string[]>(
        'phase_5',
        'notifications_options',
        toStringArray(answers.notifications_options),
    );
    const marketingOptions = useAutosaveField<string[]>('phase_5', 'marketing_options', toStringArray(answers.marketing_options));
    const leadgenOptions = useAutosaveField<string[]>('phase_5', 'leadgen_options', toStringArray(answers.leadgen_options));
    const leadgenManagedInterest = useAutosaveField<string | null>(
        'phase_5',
        'leadgen_managed_interest',
        typeof answers.leadgen_managed_interest === 'string' ? answers.leadgen_managed_interest : null,
    );
    const adminOpsOptions = useAutosaveField<string[]>('phase_5', 'admin_ops_options', toStringArray(answers.admin_ops_options));

    // Accepted growth ideas persist per module (each ✨ call is module-scoped),
    // as a jsonb `{module}_accepted_suggestions` structured answer with notes (§6.3).
    const toAccepted = (value: unknown): AcceptedSuggestionCard[] =>
        Array.isArray(value) ? (value as AcceptedSuggestionCard[]) : [];
    const notificationsAccepted = useAutosaveField<AcceptedSuggestionCard[]>(
        'phase_5',
        'notifications_accepted_suggestions',
        toAccepted(answers.notifications_accepted_suggestions),
    );
    const marketingAccepted = useAutosaveField<AcceptedSuggestionCard[]>(
        'phase_5',
        'marketing_accepted_suggestions',
        toAccepted(answers.marketing_accepted_suggestions),
    );
    const leadgenAccepted = useAutosaveField<AcceptedSuggestionCard[]>(
        'phase_5',
        'leadgen_accepted_suggestions',
        toAccepted(answers.leadgen_accepted_suggestions),
    );
    const adminOpsAccepted = useAutosaveField<AcceptedSuggestionCard[]>(
        'phase_5',
        'admin_ops_accepted_suggestions',
        toAccepted(answers.admin_ops_accepted_suggestions),
    );

    const optionsByModule: Record<string, ReturnType<typeof useAutosaveField<string[]>>> = {
        notifications: notificationOptions,
        marketing: marketingOptions,
        leadgen: leadgenOptions,
        admin_ops: adminOpsOptions,
    };

    const acceptedByModule: Record<string, ReturnType<typeof useAutosaveField<AcceptedSuggestionCard[]>>> = {
        notifications: notificationsAccepted,
        marketing: marketingAccepted,
        leadgen: leadgenAccepted,
        admin_ops: adminOpsAccepted,
    };

    const acceptSuggestion = (field: ReturnType<typeof useAutosaveField<AcceptedSuggestionCard[]>>, card: SuggestionCardData) => {
        field.setValue([...field.value, { ...card, note: '' }]);
    };

    const updateAcceptedNote = (
        field: ReturnType<typeof useAutosaveField<AcceptedSuggestionCard[]>>,
        index: number,
        note: string,
    ) => {
        field.setValue(field.value.map((c, i) => (i === index ? { ...c, note } : c)));
    };

    const removeAccepted = (field: ReturnType<typeof useAutosaveField<AcceptedSuggestionCard[]>>, index: number) => {
        field.setValue(field.value.filter((_, i) => i !== index));
    };

    const toggleModule = (key: string) => {
        enabledModules.setValue(
            enabledModules.value.includes(key)
                ? enabledModules.value.filter((k) => k !== key)
                : [...enabledModules.value, key],
        );
    };

    const toggleOption = (field: ReturnType<typeof useAutosaveField<string[]>>, key: string) => {
        field.setValue(field.value.includes(key) ? field.value.filter((k) => k !== key) : [...field.value, key]);
    };

    return (
        <div className="flex flex-col gap-6">
            {MODULES.map(({ key: moduleKey, optionKeys }) => {
                const isEnabled = enabledModules.value.includes(moduleKey);
                const optionsField = optionsByModule[moduleKey];
                const acceptedField = acceptedByModule[moduleKey];

                return (
                    <div key={moduleKey} className="flex flex-col gap-3 rounded-md border border-line bg-surface-2 p-4">
                        <SelectableCard
                            selected={isEnabled}
                            onSelect={() => toggleModule(moduleKey)}
                            title={t(`phase5.modules.${moduleKey}.title`)}
                            subtitle={t(`phase5.modules.${moduleKey}.subtitle`)}
                        />

                        {isEnabled && (
                            <div className="flex flex-col gap-3 pl-1">
                                <SuggestionPanel
                                    t={t}
                                    endpoint={route('discovery.suggest.growth', { module: moduleKey })}
                                    ctaLabel={t(`phase5.modules.${moduleKey}.aiSuggestionsCta`)}
                                    onAccept={(card) => acceptSuggestion(acceptedField, card)}
                                />

                                <AcceptedSuggestionList
                                    t={t}
                                    heading={t('phase5.acceptedIdeasHeading')}
                                    cards={acceptedField.value}
                                    onNoteChange={(index, note) => updateAcceptedNote(acceptedField, index, note)}
                                    onRemove={(index) => removeAccepted(acceptedField, index)}
                                />

                                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                    {optionKeys.map((optionKey) => (
                                        <SelectableCard
                                            key={optionKey}
                                            selected={optionsField.value.includes(optionKey)}
                                            onSelect={() => toggleOption(optionsField, optionKey)}
                                            title={t(`phase5.modules.${moduleKey}.options.${optionKey}`)}
                                        />
                                    ))}
                                </div>

                                {moduleKey === 'leadgen' && (
                                    <div className="flex flex-col gap-1.5">
                                        <p className="font-body text-xs text-text-muted">
                                            {t('phase5.modules.leadgen.managedServiceLabel')}
                                        </p>
                                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                            {LEADGEN_INTEREST_KEYS.map((key) => (
                                                <SelectableCard
                                                    key={key}
                                                    selected={leadgenManagedInterest.value === key}
                                                    onSelect={() =>
                                                        leadgenManagedInterest.setValue(
                                                            leadgenManagedInterest.value === key ? null : key,
                                                        )
                                                    }
                                                    title={t(`phase4.interest.${key}`)}
                                                />
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
