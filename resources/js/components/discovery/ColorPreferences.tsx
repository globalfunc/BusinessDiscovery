import { Sparkles } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const COLOR_PRESETS = [
    { key: 'ocean', colors: ['#0f4c81', '#4fb0c6', '#eaf6f6'] },
    { key: 'sunset', colors: ['#e0592a', '#f2a541', '#fff3e0'] },
    { key: 'forest', colors: ['#1f4d3a', '#5c8c6b', '#e9f2ea'] },
    { key: 'monochrome', colors: ['#1a1a1a', '#6b6b6b', '#f2f2f2'] },
    { key: 'earth', colors: ['#5a3e2b', '#a97c50', '#f1e6d8'] },
    { key: 'berry', colors: ['#5b1a4a', '#b23a72', '#fbe4ef'] },
] as const;

function Swatches({ colors }: { colors: string[] }) {
    return (
        <div className="flex overflow-hidden rounded-sm">
            {colors.map((color, i) => (
                <span key={i} className="h-6 w-6" style={{ backgroundColor: color }} />
            ))}
        </div>
    );
}

export function ColorPreferences({
    t,
    hasLogo,
    preset,
    customHex,
    logoColors,
    onSelectPreset,
    onCustomHexChange,
    onExtractLogoColors,
    extractingLogoColors,
    logoColorsError,
}: {
    t: (key: string, vars?: Record<string, string>) => string;
    hasLogo: boolean;
    preset: string | null;
    customHex: string | null;
    logoColors: string[];
    onSelectPreset: (key: string | null) => void;
    onCustomHexChange: (hex: string | null) => void;
    onExtractLogoColors: () => void;
    extractingLogoColors: boolean;
    logoColorsError: string | null;
}) {
    const [showCustom, setShowCustom] = useState(customHex !== null);

    return (
        <div className="flex flex-col gap-3">
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                {COLOR_PRESETS.map((p) => (
                    <button
                        key={p.key}
                        type="button"
                        onClick={() => onSelectPreset(preset === p.key ? null : p.key)}
                        aria-pressed={preset === p.key}
                        className={cn(
                            'flex items-center gap-2 rounded-md border bg-surface p-2 text-left transition-colors',
                            preset === p.key
                                ? 'border-transparent shadow-[0_0_0_1.5px_var(--lb-accent-glow)]'
                                : 'border-line hover:border-line-strong',
                        )}
                    >
                        <Swatches colors={[...p.colors]} />
                        <span className="font-ui text-xs font-medium text-text">{t(`phase3.palette.${p.key}`)}</span>
                    </button>
                ))}
            </div>

            <div className="flex flex-col gap-2 rounded-md border border-line bg-surface p-3">
                <div className="flex items-center justify-between gap-3">
                    <span className="font-ui text-xs font-medium text-text">{t('phase3.customColorLabel')}</span>
                    <input
                        type="color"
                        value={customHex ?? '#888888'}
                        onChange={(e) => {
                            setShowCustom(true);
                            onCustomHexChange(e.target.value);
                        }}
                        className="h-8 w-12 cursor-pointer rounded border border-line-strong bg-transparent"
                        aria-label={t('phase3.customColorLabel')}
                    />
                </div>
                {showCustom && customHex && (
                    <button
                        type="button"
                        onClick={() => {
                            setShowCustom(false);
                            onCustomHexChange(null);
                        }}
                        className="self-start font-ui text-xs text-text-muted hover:text-text"
                    >
                        {t('phase3.clearCustomColor')}
                    </button>
                )}
            </div>

            <div className="flex flex-col gap-2">
                <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    disabled={!hasLogo || extractingLogoColors}
                    onClick={onExtractLogoColors}
                    className="w-fit gap-1.5"
                    title={!hasLogo ? t('phase3.useLogoColorsNoLogo') : undefined}
                >
                    <Sparkles className="h-3.5 w-3.5" />
                    {extractingLogoColors ? t('phase3.useLogoColorsLoading') : t('phase3.useLogoColorsCta')}
                </Button>
                {!hasLogo && <p className="font-body text-xs text-text-faint">{t('phase3.useLogoColorsNoLogo')}</p>}
                {logoColorsError && <p className="font-body text-xs text-red">{logoColorsError}</p>}
                {logoColors.length > 0 && (
                    <div className="flex flex-wrap gap-2">
                        {logoColors.map((hex) => (
                            <button
                                key={hex}
                                type="button"
                                onClick={() => onCustomHexChange(hex)}
                                aria-pressed={customHex === hex}
                                className={cn(
                                    'flex h-8 w-8 items-center justify-center rounded-full border-2 transition-transform hover:scale-105',
                                    customHex === hex ? 'border-accent' : 'border-line-strong',
                                )}
                                style={{ backgroundColor: hex }}
                                title={hex}
                            />
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
