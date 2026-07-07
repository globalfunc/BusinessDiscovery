import { Sparkles } from 'lucide-react';
import { useState } from 'react';

import { ColorPreferences } from '@/components/discovery/ColorPreferences';
import { ReferenceLinksWithNotes, type ReferenceLinkNote } from '@/components/discovery/ReferenceLinksWithNotes';
import { SelectableCard } from '@/components/discovery/SelectableCard';
import { UploadZone, type UploadRecord } from '@/components/discovery/UploadZone';
import { Button } from '@/components/ui/button';
import { useAutosaveField } from '@/hooks/useAutosaveField';

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

    const styleChips = useAutosaveField<string[]>('phase_3', 'style_chips', initialStyleChips);
    const colorPreset = useAutosaveField<string | null>('phase_3', 'color_preset', initialColorPreset);
    const colorCustomHex = useAutosaveField<string | null>('phase_3', 'color_custom_hex', initialColorCustomHex);
    const referenceLinks = useAutosaveField<ReferenceLinkNote[]>('phase_3', 'reference_links', initialReferenceLinks);

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
            <div className="flex items-start justify-between gap-3">
                <p className="font-ui text-xs font-semibold uppercase tracking-wide text-text-muted">{t('phase3.styleHeading')}</p>
                <Button type="button" variant="secondary" size="sm" disabled className="shrink-0 gap-1.5 border-accent/40 text-accent">
                    <Sparkles className="h-3.5 w-3.5" />
                    {t('phase3.aiSuggestionsCta')}
                </Button>
            </div>
            <p className="-mt-4 font-body text-xs text-text-faint">{t('phase3.aiSuggestionsComingSoon')}</p>

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
