import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

import { BottomActionBar } from '@/components/discovery/BottomActionBar';
import { Phase0Intake } from '@/components/discovery/phases/Phase0Intake';
import { Phase1BusinessProfile } from '@/components/discovery/phases/Phase1BusinessProfile';
import { Phase2ServicesSelection } from '@/components/discovery/phases/Phase2ServicesSelection';
import { Phase3Branding } from '@/components/discovery/phases/Phase3Branding';
import { Phase4ContentSocial } from '@/components/discovery/phases/Phase4ContentSocial';
import { Phase5GrowthOperations } from '@/components/discovery/phases/Phase5GrowthOperations';
import { Phase6BillingTimeline } from '@/components/discovery/phases/Phase6BillingTimeline';
import { ReviewPreview, type SpecDocumentPayload } from '@/components/discovery/ReviewPreview';
import type { CatalogService } from '@/components/discovery/CatalogServiceCard';
import type { SelectedServiceRecord } from '@/components/discovery/SelectedServiceCard';
import type { UploadRecord } from '@/components/discovery/UploadZone';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { useAutosaveField } from '@/hooks/useAutosaveField';
import { useTranslation, type Locale } from '@/lib/i18n';
import DiscoveryLayout from '@/Layouts/DiscoveryLayout';

type PhaseItem = { key: string; label: string };
type NicheOption = { id: number; name: { en: string; bg: string } };
type CategoryOption = { id: number; name: { en: string; bg: string }; niches: NicheOption[] };

export type DcpState = {
    status: 'ok' | 'empty';
    detected_niche: { niche_id: number; category_id: number | null; confidence: number | null } | null;
} | null;

type Props = {
    businessOwner: {
        name: string;
        company: string;
        pre_selected_niche_id: number | null;
        pre_selected_category_id: number | null;
        has_logo: boolean;
    };
    session: { status: 'in_progress' | 'submitted'; current_phase: string };
    phase: string;
    phases: PhaseItem[];
    visitedPhaseKeys: string[];
    answers: Record<string, unknown>;
    dcp: DcpState;
    language: string;
    taxonomyCategories: CategoryOption[] | null;
    serviceCatalog: CatalogService[] | null;
    selectedServices: SelectedServiceRecord[] | null;
    showPricesToBo: boolean;
    uploads: UploadRecord[] | null;
    uploadQuota: { used: number; limit: number } | null;
    saasEligible: boolean;
    approxTotal: { min: number; max: number } | null;
    specDocument: SpecDocumentPayload | null;
    specStale: boolean;
};

export default function DiscoveryShow({
    businessOwner,
    session,
    phase,
    phases,
    visitedPhaseKeys,
    answers,
    dcp,
    language,
    taxonomyCategories,
    serviceCatalog,
    selectedServices,
    showPricesToBo,
    uploads,
    uploadQuota,
    saasEligible,
    approxTotal,
    specDocument,
    specStale,
}: Props) {
    const locale = (language === 'bg' ? 'bg' : 'en') as Locale;
    const t = useTranslation(locale);
    const [submitting, setSubmitting] = useState(false);
    const [phase1Valid, setPhase1Valid] = useState(true);
    const [phase6Valid, setPhase6Valid] = useState(true);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [generatingDcp, setGeneratingDcp] = useState(false);

    const phaseIndex = phases.findIndex((p) => p.key === phase);
    const previousPhase = phaseIndex > 0 ? phases[phaseIndex - 1] : null;
    const nextPhase = phaseIndex < phases.length - 1 ? phases[phaseIndex + 1] : null;
    const isReview = phase === 'review';
    const isSubmitted = session.status === 'submitted';
    const isPhase0 = phase === 'phase_0';
    const isPhase1 = phase === 'phase_1';
    const isPhase2 = phase === 'phase_2';
    const isPhase3 = phase === 'phase_3';
    const isPhase4 = phase === 'phase_4';
    const isPhase5 = phase === 'phase_5';
    const isPhase6 = phase === 'phase_6';

    const initialNote = typeof answers.notes === 'string' ? answers.notes : '';
    const { value, setValue, saveState } = useAutosaveField(phase, 'notes', initialNote);

    const goBack = () => {
        if (!previousPhase) return;
        router.visit(route('discovery.show', { phase: previousPhase.key }));
    };

    const submitDiscovery = () => {
        setSubmitting(true);
        router.post(
            route('discovery.submit'),
            {},
            {
                onFinish: () => {
                    setSubmitting(false);
                    setConfirmOpen(false);
                },
            },
        );
    };

    const goContinue = () => {
        if (isReview) {
            setConfirmOpen(true);
            return;
        }
        if (isPhase0) {
            // Phase 0 continue triggers the dcp.generate call server-side
            // before advancing; failure still advances (empty DCP + retry
            // offer on Phase 1), so this never blocks the flow.
            setGeneratingDcp(true);
            router.post(route('discovery.intake.store'), {}, { onFinish: () => setGeneratingDcp(false) });
            return;
        }
        if (!nextPhase) return;
        router.post(route('discovery.navigate'), { to: nextPhase.key });
    };

    // Skip bypasses the Phase 0 AI call — skipping intake means no DCP.
    const goSkip = () => {
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
                        continueLabel={
                            isReview ? t('submit.cta') : generatingDcp ? t('phase0.analyzing') : t('common.continue')
                        }
                        savingLabel={t('common.saving')}
                        savedLabel={t('common.saved')}
                        saveState={saveState}
                        backDisabled={!previousPhase}
                        continueDisabled={
                            submitting || generatingDcp || (isPhase1 && !phase1Valid) || (isPhase6 && !phase6Valid)
                        }
                    />
                )
            }
        >
            <Head title={t(`phases.${phase}.title`)} />

            <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('submit.confirmTitle')}</DialogTitle>
                        <DialogDescription>{t('submit.confirmBody')}</DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="ghost" onClick={() => setConfirmOpen(false)} disabled={submitting}>
                            {t('common.cancel')}
                        </Button>
                        <Button onClick={submitDiscovery} disabled={submitting}>
                            {t('submit.confirmCta')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {isSubmitted ? (
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

                    {isPhase0 ? (
                        <Phase0Intake t={t} answers={answers} />
                    ) : isPhase1 ? (
                        <Phase1BusinessProfile
                            locale={locale}
                            t={t}
                            businessOwner={businessOwner}
                            answers={answers}
                            taxonomyCategories={taxonomyCategories ?? []}
                            dcp={dcp}
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
                    ) : isPhase3 ? (
                        <Phase3Branding
                            t={t}
                            answers={answers}
                            hasLogo={businessOwner.has_logo}
                            initialUploads={uploads ?? []}
                            initialQuota={uploadQuota ?? { used: 0, limit: 200 * 1024 * 1024 }}
                        />
                    ) : isPhase4 ? (
                        <Phase4ContentSocial t={t} answers={answers} />
                    ) : isPhase5 ? (
                        <Phase5GrowthOperations t={t} answers={answers} />
                    ) : isPhase6 ? (
                        <Phase6BillingTimeline
                            t={t}
                            answers={answers}
                            saasEligible={saasEligible}
                            showPricesToBo={showPricesToBo}
                            approxTotal={approxTotal}
                            onValidityChange={setPhase6Valid}
                        />
                    ) : isReview ? (
                        <ReviewPreview t={t} initialDocument={specDocument} stale={specStale} />
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

                    {!isReview && !isPhase1 && !isPhase2 && !isPhase6 && (
                        <div>
                            <Button variant="ghost" size="sm" onClick={goSkip} disabled={submitting || generatingDcp}>
                                {t('common.skip')}
                            </Button>
                        </div>
                    )}
                </div>
            )}
        </DiscoveryLayout>
    );
}
