import { useEffect, useRef, useState } from 'react';

import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useAutosaveField } from '@/hooks/useAutosaveField';
import { cn } from '@/lib/utils';

type Props = {
    t: (key: string, vars?: Record<string, string>) => string;
    answers: Record<string, unknown>;
};

type IntakeMode = 'free' | 'guided';

const GUIDED_FIELDS = ['guided_offer', 'guided_channels', 'guided_frustrations', 'guided_success'] as const;
const PLACEHOLDER_KEYS = ['phase0.placeholder1', 'phase0.placeholder2', 'phase0.placeholder3'] as const;
const HINT_KEYS = ['phase0.hint1', 'phase0.hint2', 'phase0.hint3'] as const;

/**
 * Phase 0 intake (design.md §6.5): segmented free-prompt / guided-interview
 * toggle. Both modes autosave to their own discovery_answers fields, so
 * switching tabs mid-way never loses input; the DCP assembler reads
 * whatever is present from either mode.
 */
export function Phase0Intake({ t, answers }: Props) {
    const initialMode: IntakeMode = answers.intake_mode === 'guided' ? 'guided' : 'free';
    const mode = useAutosaveField<IntakeMode>('phase_0', 'intake_mode', initialMode);

    const freePrompt = useAutosaveField(
        'phase_0',
        'free_prompt',
        typeof answers.free_prompt === 'string' ? answers.free_prompt : '',
    );
    const guidedOffer = useAutosaveField(
        'phase_0',
        'guided_offer',
        typeof answers.guided_offer === 'string' ? answers.guided_offer : '',
    );
    const guidedChannels = useAutosaveField(
        'phase_0',
        'guided_channels',
        typeof answers.guided_channels === 'string' ? answers.guided_channels : '',
    );
    const guidedFrustrations = useAutosaveField(
        'phase_0',
        'guided_frustrations',
        typeof answers.guided_frustrations === 'string' ? answers.guided_frustrations : '',
    );
    const guidedSuccess = useAutosaveField(
        'phase_0',
        'guided_success',
        typeof answers.guided_success === 'string' ? answers.guided_success : '',
    );
    const website = useAutosaveField(
        'phase_0',
        'website_url',
        typeof answers.website_url === 'string' ? answers.website_url : '',
    );
    const socialLinks = useAutosaveField(
        'phase_0',
        'social_links',
        typeof answers.social_links === 'string' ? answers.social_links : '',
    );

    const guidedFields = [guidedOffer, guidedChannels, guidedFrustrations, guidedSuccess];

    // Rotating placeholder: fade-cycle every 4s, paused while focused or once
    // the BO has typed anything (design.md §6.5).
    const [placeholderIndex, setPlaceholderIndex] = useState(0);
    const [placeholderVisible, setPlaceholderVisible] = useState(true);
    const [focused, setFocused] = useState(false);
    const textareaRef = useRef<HTMLTextAreaElement | null>(null);

    useEffect(() => {
        if (focused || freePrompt.value !== '') return;
        const interval = setInterval(() => {
            setPlaceholderVisible(false);
            setTimeout(() => {
                setPlaceholderIndex((i) => (i + 1) % PLACEHOLDER_KEYS.length);
                setPlaceholderVisible(true);
            }, 300);
        }, 4000);
        return () => clearInterval(interval);
    }, [focused, freePrompt.value]);

    const autoGrow = () => {
        const el = textareaRef.current;
        if (!el) return;
        el.style.height = 'auto';
        el.style.height = `${el.scrollHeight}px`;
    };

    const insertHint = (hint: string) => {
        const next = freePrompt.value === '' ? hint : `${freePrompt.value.trimEnd()}\n${hint}`;
        freePrompt.setValue(next);
        textareaRef.current?.focus();
    };

    return (
        <div className="flex flex-col gap-6">
            <div
                role="tablist"
                aria-label={t('phase0.modeToggleLabel')}
                className="grid grid-cols-2 gap-1 rounded-md border border-line bg-surface-2 p-1"
            >
                {(['free', 'guided'] as const).map((m) => (
                    <button
                        key={m}
                        type="button"
                        role="tab"
                        aria-selected={mode.value === m}
                        onClick={() => mode.setValue(m)}
                        className={cn(
                            'rounded px-3 py-2 font-ui text-sm font-medium transition-colors',
                            mode.value === m
                                ? 'bg-surface text-text shadow-[0_0_0_1px_var(--lb-line-strong)]'
                                : 'text-text-muted hover:text-text',
                        )}
                    >
                        {m === 'free' ? t('phase0.modeFree') : t('phase0.modeGuided')}
                    </button>
                ))}
            </div>

            {mode.value === 'free' ? (
                <div className="flex flex-col gap-3">
                    <Textarea
                        ref={textareaRef}
                        value={freePrompt.value}
                        onChange={(e) => {
                            freePrompt.setValue(e.target.value);
                            autoGrow();
                        }}
                        onFocus={() => setFocused(true)}
                        onBlur={() => setFocused(false)}
                        placeholder={t(PLACEHOLDER_KEYS[placeholderIndex])}
                        rows={6}
                        className={cn(
                            'min-h-36 resize-none transition-opacity duration-300',
                            !placeholderVisible && freePrompt.value === '' && 'placeholder:opacity-0',
                            'placeholder:transition-opacity placeholder:duration-300',
                        )}
                    />

                    <div className="flex flex-wrap gap-2">
                        {HINT_KEYS.map((key) => (
                            <button
                                key={key}
                                type="button"
                                onClick={() => insertHint(t(key))}
                                className="rounded-full border border-line px-3 py-1 font-body text-xs text-text-muted transition-colors hover:border-line-strong hover:text-text"
                            >
                                {t(key)}
                            </button>
                        ))}
                    </div>
                </div>
            ) : (
                <div className="flex flex-col gap-3">
                    {GUIDED_FIELDS.map((key, index) => (
                        <div key={key} className="flex flex-col gap-1.5 rounded-md border border-line bg-surface-2 p-4">
                            <Label htmlFor={key}>
                                <span className="mr-1.5 text-accent">{index + 1}.</span>
                                {t(`phase0.${key}`)}
                            </Label>
                            <Textarea
                                id={key}
                                value={guidedFields[index].value}
                                onChange={(e) => guidedFields[index].setValue(e.target.value)}
                                rows={2}
                            />
                        </div>
                    ))}
                </div>
            )}

            <div className="flex flex-col gap-3">
                <p className="font-ui text-xs font-semibold uppercase tracking-wide text-text-muted">
                    {t('phase0.optionalHeading')}
                </p>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="website_url">{t('phase0.websiteLabel')}</Label>
                        <Input
                            id="website_url"
                            placeholder="https://"
                            value={website.value}
                            onChange={(e) => website.setValue(e.target.value)}
                        />
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="social_links">{t('phase0.socialLabel')}</Label>
                        <Input
                            id="social_links"
                            placeholder={t('phase0.socialPlaceholder')}
                            value={socialLinks.value}
                            onChange={(e) => socialLinks.setValue(e.target.value)}
                        />
                    </div>
                </div>
            </div>
        </div>
    );
}
