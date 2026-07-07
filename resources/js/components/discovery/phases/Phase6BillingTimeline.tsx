import { useEffect, useState } from 'react';

import { SelectableCard } from '@/components/discovery/SelectableCard';
import { Textarea } from '@/components/ui/textarea';
import { useAutosaveField } from '@/hooks/useAutosaveField';
import { formatAmountInCurrency, formatAmountRangeInCurrency, type DisplayCurrency } from '@/lib/currency';
import { cn } from '@/lib/utils';

const BILLING_KEYS = ['one_time', 'build_support', 'saas_subscription', 'advise_me'] as const;
const TIMELINE_KEYS = ['asap', 'one_month', 'one_to_three_months', 'flexible'] as const;

const BUDGET_MIN = 500;
const BUDGET_MAX = 20000;
const BUDGET_STEP = 500;

type Props = {
    t: (key: string, vars?: Record<string, string>) => string;
    answers: Record<string, unknown>;
    saasEligible: boolean;
    showPricesToBo: boolean;
    approxTotal: { min: number; max: number } | null;
    onValidityChange: (valid: boolean) => void;
};

export function Phase6BillingTimeline({ t, answers, saasEligible, showPricesToBo, approxTotal, onValidityChange }: Props) {
    const billingModel = useAutosaveField<string | null>(
        'phase_6',
        'billing_model',
        typeof answers.billing_model === 'string' ? answers.billing_model : null,
    );
    const budgetMin = useAutosaveField<number>('phase_6', 'budget_min', typeof answers.budget_min === 'number' ? answers.budget_min : 2000);
    const budgetMax = useAutosaveField<number>('phase_6', 'budget_max', typeof answers.budget_max === 'number' ? answers.budget_max : 8000);
    const timelineChoice = useAutosaveField<string | null>(
        'phase_6',
        'timeline_choice',
        typeof answers.timeline_choice === 'string' ? answers.timeline_choice : null,
    );
    const timelineNote = useAutosaveField('phase_6', 'timeline_note', typeof answers.timeline_note === 'string' ? answers.timeline_note : '');

    const [displayCurrency, setDisplayCurrency] = useState<DisplayCurrency>('EUR');

    const isValid = billingModel.value !== null;

    useEffect(() => {
        onValidityChange(isValid);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [isValid]);

    const billingKeys = BILLING_KEYS.filter((key) => key !== 'saas_subscription' || saasEligible);

    const setMin = (value: number) => budgetMin.setValue(Math.min(value, budgetMax.value));
    const setMax = (value: number) => budgetMax.setValue(Math.max(value, budgetMin.value));

    return (
        <div className="flex flex-col gap-6">
            {showPricesToBo && approxTotal && (
                <div className="flex flex-col items-center gap-1 rounded-md border border-line bg-surface-2 p-5 text-center">
                    <p className="font-ui text-xs font-semibold uppercase tracking-wide text-text-muted">{t('phase6.approxTotalLabel')}</p>
                    <p className="font-display text-3xl font-semibold text-text">
                        {formatAmountRangeInCurrency(approxTotal.min, approxTotal.max, displayCurrency)}
                    </p>
                    <p className="font-ui text-[10px] uppercase tracking-wide text-text-faint">{t('phase6.approxTotalCaption')}</p>
                </div>
            )}

            <div className="flex flex-col gap-3">
                <p className="font-ui text-xs font-semibold uppercase tracking-wide text-text-muted">{t('phase6.billingHeading')}</p>
                <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    {billingKeys.map((key) => (
                        <SelectableCard
                            key={key}
                            selected={billingModel.value === key}
                            onSelect={() => billingModel.setValue(key)}
                            title={t(`phase6.billing.${key}.title`)}
                            subtitle={t(`phase6.billing.${key}.description`)}
                        />
                    ))}
                </div>
                {!isValid && <p className="font-body text-xs text-red">{t('phase6.billingRequiredHint')}</p>}
            </div>

            <div className="flex flex-col gap-3">
                <div className="flex items-center justify-between gap-3">
                    <p className="font-ui text-xs font-semibold uppercase tracking-wide text-text-muted">{t('phase6.budgetHeading')}</p>
                    <div className="flex overflow-hidden rounded-md border border-line-strong">
                        {(['EUR', 'BGN'] as const).map((currency) => (
                            <button
                                key={currency}
                                type="button"
                                onClick={() => setDisplayCurrency(currency)}
                                className={cn(
                                    'px-2.5 py-1 font-ui text-xs font-medium transition-colors',
                                    displayCurrency === currency ? 'bg-accent text-[#241305]' : 'text-text-muted hover:text-text',
                                )}
                            >
                                {currency}
                            </button>
                        ))}
                    </div>
                </div>

                <p className="font-body text-sm text-text">
                    {formatAmountRangeInCurrency(budgetMin.value, budgetMax.value, displayCurrency)}
                </p>

                <div className="relative flex h-6 items-center">
                    <div className="absolute h-1 w-full rounded-full bg-line" />
                    <div
                        className="absolute h-1 rounded-full bg-accent"
                        style={{
                            left: `${((budgetMin.value - BUDGET_MIN) / (BUDGET_MAX - BUDGET_MIN)) * 100}%`,
                            right: `${100 - ((budgetMax.value - BUDGET_MIN) / (BUDGET_MAX - BUDGET_MIN)) * 100}%`,
                        }}
                    />
                    <input
                        type="range"
                        min={BUDGET_MIN}
                        max={BUDGET_MAX}
                        step={BUDGET_STEP}
                        value={budgetMin.value}
                        onChange={(e) => setMin(Number(e.target.value))}
                        aria-label={t('phase6.budgetMinLabel')}
                        className="pointer-events-none absolute h-6 w-full appearance-none bg-transparent [&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:h-4 [&::-webkit-slider-thumb]:w-4 [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-accent [&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:h-4 [&::-moz-range-thumb]:w-4 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-0 [&::-moz-range-thumb]:bg-accent"
                    />
                    <input
                        type="range"
                        min={BUDGET_MIN}
                        max={BUDGET_MAX}
                        step={BUDGET_STEP}
                        value={budgetMax.value}
                        onChange={(e) => setMax(Number(e.target.value))}
                        aria-label={t('phase6.budgetMaxLabel')}
                        className="pointer-events-none absolute h-6 w-full appearance-none bg-transparent [&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:h-4 [&::-webkit-slider-thumb]:w-4 [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-accent [&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:h-4 [&::-moz-range-thumb]:w-4 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-0 [&::-moz-range-thumb]:bg-accent"
                    />
                </div>
                <div className="flex justify-between font-ui text-[10px] text-text-faint">
                    <span>{formatAmountInCurrency(BUDGET_MIN, displayCurrency)}</span>
                    <span>{formatAmountInCurrency(BUDGET_MAX, displayCurrency)}+</span>
                </div>
            </div>

            <div className="flex flex-col gap-3">
                <p className="font-ui text-xs font-semibold uppercase tracking-wide text-text-muted">{t('phase6.timelineHeading')}</p>
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    {TIMELINE_KEYS.map((key) => (
                        <SelectableCard
                            key={key}
                            selected={timelineChoice.value === key}
                            onSelect={() => timelineChoice.setValue(timelineChoice.value === key ? null : key)}
                            title={t(`phase6.timeline.${key}`)}
                        />
                    ))}
                </div>

                <div className="flex flex-col gap-1.5">
                    <p className="font-body text-xs text-text-muted">{t('phase6.timelineNoteLabel')}</p>
                    <Textarea
                        value={timelineNote.value}
                        onChange={(e) => timelineNote.setValue(e.target.value)}
                        placeholder={t('phase6.timelineNotePlaceholder')}
                        rows={2}
                    />
                </div>
            </div>
        </div>
    );
}
