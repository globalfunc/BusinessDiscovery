import { X } from 'lucide-react';
import { useState } from 'react';

import { ColorPreferences } from '@/components/discovery/ColorPreferences';
import { ReferenceLinksWithNotes, type ReferenceLinkNote } from '@/components/discovery/ReferenceLinksWithNotes';
import { SelectableCard } from '@/components/discovery/SelectableCard';
import type { SuggestionCardData } from '@/components/discovery/SuggestionCard';
import { SuggestionPanel } from '@/components/discovery/SuggestionPanel';
import { UploadZone, type UploadRecord } from '@/components/discovery/UploadZone';
import { Badge } from '@/components/ui/badge';
import { Textarea } from '@/components/ui/textarea';
import { useAutosaveField } from '@/hooks/useAutosaveField';

/** An accepted brand-direction card, persisted with the BO's own note (§6.3). */
type AcceptedBrandingCard = SuggestionCardData & { note: string };

const STYLE_CHIP_KEYS = [
    'modern',
    'minimal',
    'classic',
    'bold',
    'warm',
    'luxury',
    'playful',
    'industrial',
    'natural',
] as const;

const MAX_FILE_BYTES = 15 * 1024 * 1024;

type Props = {
    t: (key: string, vars?: Record<string, string>) => string;
    answers: Record<string, unknown>;
    hasLogo: boolean;
    initialUploads: UploadRecord[];
    initialQuota: { used: number; limit: number };
};

export function Phase3Branding({ t, answers, hasLogo, initialUploads, initialQuota }: Props) {
    const initialStyleChips = Array.isArray(answers.style_chips) ? (answers.style_chips as string[]) : [];
    const initialColorPreset = typeof answers.color_preset === 'string' ? answers.color_preset : null;
    const initialColorCustomHex = typeof answers.color_custom_hex === 'string' ? answers.color_custom_hex : null;
    const initialReferenceLinks = Array.isArray(answers.reference_links) ? (answers.reference_links as ReferenceLinkNote[]) : [];
    const initialAcceptedSuggestions = Array.isArray(answers.accepted_suggestions)
        ? (answers.accepted_suggestions as AcceptedBrandingCard[])
        : [];

    const styleChips = useAutosaveField<string[]>('phase_3', 'style_chips', initialStyleChips);
    const colorPreset = useAutosaveField<string | null>('phase_3', 'color_preset', initialColorPreset);
    const colorCustomHex = useAutosaveField<string | null>('phase_3', 'color_custom_hex', initialColorCustomHex);
    const referenceLinks = useAutosaveField<ReferenceLinkNote[]>('phase_3', 'reference_links', initialReferenceLinks);
    // Accepted brand directions persist as a structured answer (no dedicated
    // table — brand directions aren't catalog services); each keeps its own
    // free-text note (§6.3), autosaved as a jsonb array.
    const acceptedSuggestions = useAutosaveField<AcceptedBrandingCard[]>('phase_3', 'accepted_suggestions', initialAcceptedSuggestions);

    const acceptSuggestion = (card: SuggestionCardData) => {
        acceptedSuggestions.setValue([...acceptedSuggestions.value, { ...card, note: '' }]);
    };

    const updateAcceptedNote = (index: number, note: string) => {
        acceptedSuggestions.setValue(acceptedSuggestions.value.map((c, i) => (i === index ? { ...c, note } : c)));
    };

    const removeAccepted = (index: number) => {
        acceptedSuggestions.setValue(acceptedSuggestions.value.filter((_, i) => i !== index));
    };

    const [uploads, setUploads] = useState<UploadRecord[]>(initialUploads);
    const [quota, setQuota] = useState(initialQuota);
    const [uploadError, setUploadError] = useState<string | null>(null);

    const [logoColors, setLogoColors] = useState<string[]>([]);
    const [extractingLogoColors, setExtractingLogoColors] = useState(false);
    const [logoColorsError, setLogoColorsError] = useState<string | null>(null);

    const toggleChip = (key: string) => {
        styleChips.setValue(
            styleChips.value.includes(key) ? styleChips.value.filter((c) => c !== key) : [...styleChips.value, key],
        );
    };

    const selectPreset = (key: string | null) => {
        colorPreset.setValue(key);
        if (key !== null) colorCustomHex.setValue(null);
    };

    const setCustomHex = (hex: string | null) => {
        colorCustomHex.setValue(hex);
        if (hex !== null) colorPreset.setValue(null);
    };

    const extractLogoColors = () => {
        setExtractingLogoColors(true);
        setLogoColorsError(null);
        window.axios
            .get(route('discovery.branding.logo-colors'))
            .then(({ data }) => setLogoColors(data.colors))
            .catch(({ response }) => setLogoColorsError(response?.data?.message ?? t('phase3.useLogoColorsError')))
            .finally(() => setExtractingLogoColors(false));
    };

    const uploadFile = (file: File) => {
        setUploadError(null);
        if (file.size > MAX_FILE_BYTES) {
            setUploadError(t('phase3.uploadTooLarge', { name: file.name }));
            return;
        }

        const formData = new FormData();
        formData.append('file', file);

        window.axios
            .post(route('discovery.uploads.store'), formData, { headers: { 'Content-Type': 'multipart/form-data' } })
            .then(({ data }) => {
                setUploads((prev) => [...prev, data.upload]);
                setQuota(data.quota);
            })
            .catch(({ response }) => {
                setUploadError(response?.data?.message ?? t('phase3.uploadFailed', { name: file.name }));
            });
    };

    const removeUpload = (id: number) => {
        setUploads((prev) => prev.filter((u) => u.id !== id));
        window.axios.delete(route('discovery.uploads.destroy', { upload: id })).then(({ data }) => {
            setQuota(data.quota);
        });
    };

    return (
        <div className="flex flex-col gap-6">
            <p className="font-ui text-xs font-semibold uppercase tracking-wide text-text-muted">{t('phase3.styleHeading')}</p>

            <SuggestionPanel
                t={t}
                endpoint={route('discovery.suggest.branding')}
                ctaLabel={t('phase3.aiSuggestionsCta')}
                onAccept={acceptSuggestion}
            />

            {acceptedSuggestions.value.length > 0 && (
                <div className="flex flex-col gap-3">
                    <p className="font-ui text-xs font-semibold uppercase tracking-wide text-text-muted">
                        {t('phase3.acceptedDirectionsHeading')}
                    </p>
                    {acceptedSuggestions.value.map((card, index) => (
                        <div key={index} className="flex flex-col gap-2 rounded-bo border border-transparent bg-surface p-4 shadow-[0_0_0_1.5px_var(--lb-accent-glow)]">
                            <div className="flex items-start justify-between gap-2">
                                <div className="flex flex-col gap-0.5">
                                    <span className="font-ui text-sm font-semibold text-text">{card.title}</span>
                                    {card.summary && <span className="font-body text-xs text-text-muted">{card.summary}</span>}
                                </div>
                                <div className="flex items-center gap-2">
                                    {card.tags.slice(0, 2).map((tag) => (
                                        <Badge key={tag} variant="muted">
                                            {tag.replace(/_/g, ' ')}
                                        </Badge>
                                    ))}
                                    <button
                                        type="button"
                                        onClick={() => removeAccepted(index)}
                                        aria-label={t('phase2.remove')}
                                        className="flex h-8 w-8 items-center justify-center rounded-md border border-line-strong text-text-faint hover:border-red/40 hover:text-red"
                                    >
                                        <X className="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                            {card.features.length > 0 && (
                                <ul className="flex flex-col gap-1 font-body text-xs text-text-muted">
                                    {card.features.map((feature, i) => (
                                        <li key={i}>• {feature}</li>
                                    ))}
                                </ul>
                            )}
                            <Textarea
                                value={card.note}
                                onChange={(e) => updateAcceptedNote(index, e.target.value)}
                                placeholder={t('suggestions.notePlaceholder')}
                                rows={2}
                                className="text-xs"
                            />
                        </div>
                    ))}
                </div>
            )}

            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                {STYLE_CHIP_KEYS.map((key) => (
                    <SelectableCard
                        key={key}
                        selected={styleChips.value.includes(key)}
                        onSelect={() => toggleChip(key)}
                        title={t(`phase3.styleChips.${key}`)}
                    />
                ))}
            </div>

            <div className="flex flex-col gap-3">
                <p className="font-ui text-xs font-semibold uppercase tracking-wide text-text-muted">{t('phase3.colorHeading')}</p>
                <ColorPreferences
                    t={t}
                    hasLogo={hasLogo}
                    preset={colorPreset.value}
                    customHex={colorCustomHex.value}
                    logoColors={logoColors}
                    onSelectPreset={selectPreset}
                    onCustomHexChange={setCustomHex}
                    onExtractLogoColors={extractLogoColors}
                    extractingLogoColors={extractingLogoColors}
                    logoColorsError={logoColorsError}
                />
            </div>

            <div className="flex flex-col gap-3">
                <p className="font-ui text-xs font-semibold uppercase tracking-wide text-text-muted">{t('phase3.uploadsHeading')}</p>
                <UploadZone
                    uploads={uploads}
                    quotaUsed={quota.used}
                    quotaLimit={quota.limit}
                    onUpload={uploadFile}
                    onRemove={removeUpload}
                    dropLabel={t('phase3.dropLabel')}
                    browseLabel={t('phase3.browseLabel')}
                    quotaLabel={(used, limit) => t('phase3.quotaLabel', { used, limit })}
                    removeLabel={t('phase3.removeUpload')}
                    error={uploadError}
                />
            </div>

            <div className="flex flex-col gap-3">
                <p className="font-ui text-xs font-semibold uppercase tracking-wide text-text-muted">{t('phase3.referenceLinksHeading')}</p>
                <p className="-mt-2 font-body text-xs text-text-faint">{t('phase3.referenceLinksSubheading')}</p>
                <ReferenceLinksWithNotes
                    values={referenceLinks.value}
                    onChange={(values) => referenceLinks.setValue(values)}
                    urlPlaceholder={t('phase3.referenceLinkPlaceholder')}
                    notePlaceholder={t('phase3.referenceLinkNotePlaceholder')}
                    addLabel={t('common.add')}
                />
            </div>
        </div>
    );
}
