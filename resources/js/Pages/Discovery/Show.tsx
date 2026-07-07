import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

import { BottomActionBar } from '@/components/discovery/BottomActionBar';
import { Phase1BusinessProfile } from '@/components/discovery/phases/Phase1BusinessProfile';
import { Phase2ServicesSelection } from '@/components/discovery/phases/Phase2ServicesSelection';
import type { CatalogService } from '@/components/discovery/CatalogServiceCard';
import type { SelectedServiceRecord } from '@/components/discovery/SelectedServiceCard';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { useAutosaveField } from '@/hooks/useAutosaveField';
import { useTranslation, type Locale } from '@/lib/i18n';
import DiscoveryLayout from '@/Layouts/DiscoveryLayout';

type PhaseItem = { key: string; label: string };
type NicheOption = { id: number; name: { en: string; bg: string } };
type CategoryOption = { id: number; name: { en: string; bg: string }; niches: NicheOption[] };

type Props = {
    businessOwner: {
        name: string;
        company: string;
        pre_selected_niche_id: number | null;
        pre_selected_category_id: number | null;
    };
    session: { status: 'in_progress' | 'submitted'; current_phase: string };
    phase: string;
    phases: PhaseItem[];
    visitedPhaseKeys: string[];
    answers: Record<string, unknown>;
    language: string;
    taxonomyCategories: CategoryOption[] | null;
    serviceCatalog: CatalogService[] | null;
    selectedServices: SelectedServiceRecord[] | null;
    showPricesToBo: boolean;
};

export default function DiscoveryShow({
    businessOwner,
    session,
    phase,
    phases,
    visitedPhaseKeys,
    answers,
    language,
    taxonomyCategories,
    serviceCatalog,
    selectedServices,
    showPricesToBo,
}: Props) {
    const locale = (language === 'bg' ? 'bg' : 'en') as Locale;
    const t = useTranslation(locale);
    const [submitting, setSubmitting] = useState(false);
    const [phase1Valid, setPhase1Valid] = useState(true);

    const phaseIndex = phases.findIndex((p) => p.key === phase);
    const previousPhase = phaseIndex > 0 ? phases[phaseIndex - 1] : null;
    const nextPhase = phaseIndex < phases.length - 1 ? phases[phaseIndex + 1] : null;
    const isReview = phase === 'review';
    const isSubmitted = session.status === 'submitted';
    const isPhase1 = phase === 'phase_1';
    const isPhase2 = phase === 'phase_2';

    const initialNote = typeof answers.notes === 'string' ? answers.notes : '';
    const { value, setValue, saveState } = useAutosaveField(phase, 'notes', initialNote);

    const goBack = () => {
        if (!previousPhase) return;
        router.visit(route('discovery.show', { phase: previousPhase.key }));
    };

    const goContinue = () => {
        if (isReview) {
            setSubmitting(true);
            router.post(route('discovery.submit'), {}, { onFinish: () => setSubmitting(false) });
            return;
        }
        if (!nextPhase) return;
        router.post(route('discovery.navigate'), { to: nextPhase.key });
    };

    return (
        <DiscoveryLayout
            company={businessOwner.company}
            phases={phases}
            currentPhase={phase}
            visitedPhaseKeys={visitedPhaseKeys}
            locale={locale}
            bottomBar={
                !isSubmitted && (
                    <BottomActionBar
                        onBack={goBack}
                        onContinue={goContinue}
                        backLabel={t('common.back')}
                        continueLabel={isReview ? t('submit.cta') : t('common.continue')}
                        savingLabel={t('common.saving')}
                        savedLabel={t('common.saved')}
                        saveState={saveState}
                        backDisabled={!previousPhase}
                        continueDisabled={submitting || (isPhase1 && !phase1Valid)}
                    />
                )
            }
        >
            <Head title={t(`phases.${phase}.title`)} />

            {isReview && isSubmitted ? (
                <div className="flex flex-col gap-3 rounded-bo border border-line bg-surface p-6 text-center">
                    <p className="eyebrow text-teal">{t('common.review')}</p>
                    <h1 className="font-display text-2xl font-semibold text-text">
                        {t('submit.closingTitle', { name: businessOwner.name })}
                    </h1>
                    <p className="font-body text-sm text-text-muted">{t('submit.closingBody')}</p>
                </div>
            ) : (
                <div className="flex flex-col gap-4 rounded-bo border border-line bg-surface p-6">
                    <div>
                        <p className="eyebrow text-accent">
                            {phaseIndex + 1} / {phases.length}
                        </p>
                        <h1 className="mt-1 font-display text-2xl font-semibold text-text">{t(`phases.${phase}.title`)}</h1>
                        <p className="mt-1 font-body text-sm text-text-muted">{t(`phases.${phase}.helper`)}</p>
                    </div>

                    {isPhase1 ? (
                        <Phase1BusinessProfile
                            locale={locale}
                            t={t}
                            businessOwner={businessOwner}
                            answers={answers}
                            taxonomyCategories={taxonomyCategories ?? []}
                            onValidityChange={setPhase1Valid}
                        />
                    ) : isPhase2 ? (
                        <Phase2ServicesSelection
                            locale={locale}
                            t={t}
                            serviceCatalog={serviceCatalog ?? []}
                            initialSelectedServices={selectedServices ?? []}
                            showPricesToBo={showPricesToBo}
                        />
                    ) : (
                        <>
                            <div className="rounded-md border border-dashed border-line-strong bg-surface-2 p-4 font-body text-sm text-text-faint">
                                {t(`phases.${phase}.placeholder`)}
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <label htmlFor="notes" className="font-ui text-xs font-medium text-text-muted">
                                    Notes
                                </label>
                                <Textarea
                                    id="notes"
                                    value={value}
                                    onChange={(e) => setValue(e.target.value)}
                                    placeholder={t(`phases.${phase}.placeholder`)}
                                    rows={4}
                                />
                            </div>
                        </>
                    )}

                    {!isReview && !isPhase1 && !isPhase2 && (
                        <div>
                            <Button variant="ghost" size="sm" onClick={goContinue} disabled={submitting}>
                                {t('common.skip')}
                            </Button>
                        </div>
                    )}
                </div>
            )}
        </DiscoveryLayout>
    );
}
