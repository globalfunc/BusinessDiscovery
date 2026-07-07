import { Sparkles } from 'lucide-react';

import { SelectableCard } from '@/components/discovery/SelectableCard';
import { Button } from '@/components/ui/button';
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

    const optionsByModule: Record<string, ReturnType<typeof useAutosaveField<string[]>>> = {
        notifications: notificationOptions,
        marketing: marketingOptions,
        leadgen: leadgenOptions,
        admin_ops: adminOpsOptions,
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
                                <div className="flex items-start justify-between gap-3">
                                    <p className="font-ui text-xs font-semibold uppercase tracking-wide text-text-muted">
                                        {t('phase5.enableToggleLabel')}
                                    </p>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        size="sm"
                                        disabled
                                        className="shrink-0 gap-1.5 border-accent/40 text-accent"
                                    >
                                        <Sparkles className="h-3.5 w-3.5" />
                                        {t(`phase5.modules.${moduleKey}.aiSuggestionsCta`)}
                                    </Button>
                                </div>
                                <p className="-mt-2 font-body text-xs text-text-faint">
                                    {t(`phase5.modules.${moduleKey}.aiSuggestionsComingSoon`)}
                                </p>

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
