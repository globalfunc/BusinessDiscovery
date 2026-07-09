import { usePage } from '@inertiajs/react';

import bg from '@/lang/bg.json';
import en from '@/lang/en.json';

export type Locale = 'bg' | 'en';

/**
 * §6.6 phase-copy overrides, shared on every Inertia page by
 * HandleInertiaRequests. Keyed by language, then by phase (or the
 * "greeting" pseudo-phase); only fields with an admin-set override appear.
 */
export type PhaseCopyOverrides = Partial<
    Record<Locale, Record<string, { title?: string; helper?: string; body?: string }>>
>;

const dictionaries: Record<Locale, typeof en> = { bg, en };

function resolveKey(dict: unknown, key: string): unknown {
    return key.split('.').reduce<unknown>((acc, part) => {
        if (acc && typeof acc === 'object' && part in (acc as Record<string, unknown>)) {
            return (acc as Record<string, unknown>)[part];
        }
        return undefined;
    }, dict);
}

function interpolate(template: string, vars?: Record<string, string>): string {
    if (!vars) return template;
    return template.replace(/\{(\w+)\}/g, (match, name) => (name in vars ? vars[name] : match));
}

/**
 * Maps a translation key to the (phase, field) an admin override would live
 * under, for the two shapes phase-copy overrides support: `phases.X.title`
 * / `phases.X.helper`, and `greeting.title` / `greeting.body`.
 */
function resolveOverride(overrides: PhaseCopyOverrides | undefined, locale: Locale, key: string): string | undefined {
    const parts = key.split('.');
    let phase: string | undefined;
    let field: string | undefined;

    if (parts[0] === 'phases' && parts.length === 3 && (parts[2] === 'title' || parts[2] === 'helper')) {
        [, phase, field] = parts;
    } else if (parts[0] === 'greeting' && (parts[1] === 'title' || parts[1] === 'body')) {
        phase = 'greeting';
        field = parts[1];
    } else {
        return undefined;
    }

    const value = overrides?.[locale]?.[phase]?.[field as 'title' | 'helper' | 'body'];
    return typeof value === 'string' ? value : undefined;
}

export function translate(
    locale: Locale,
    key: string,
    vars?: Record<string, string>,
    overrides?: PhaseCopyOverrides,
): string {
    const overridden = resolveOverride(overrides, locale, key);
    if (overridden !== undefined) return interpolate(overridden, vars);

    const dict = dictionaries[locale] ?? dictionaries.en;
    const value = resolveKey(dict, key) ?? resolveKey(dictionaries.en, key);
    if (typeof value !== 'string') return key;
    return interpolate(value, vars);
}

export function useTranslation(locale: Locale) {
    const { props } = usePage<{ phaseCopyOverrides?: PhaseCopyOverrides }>();
    const overrides = props.phaseCopyOverrides;
    return (key: string, vars?: Record<string, string>) => translate(locale, key, vars, overrides);
}
