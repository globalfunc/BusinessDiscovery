import bg from '@/lang/bg.json';
import en from '@/lang/en.json';

export type Locale = 'bg' | 'en';

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

export function translate(locale: Locale, key: string, vars?: Record<string, string>): string {
    const dict = dictionaries[locale] ?? dictionaries.en;
    const value = resolveKey(dict, key) ?? resolveKey(dictionaries.en, key);
    if (typeof value !== 'string') return key;
    return interpolate(value, vars);
}

export function useTranslation(locale: Locale) {
    return (key: string, vars?: Record<string, string>) => translate(locale, key, vars);
}
