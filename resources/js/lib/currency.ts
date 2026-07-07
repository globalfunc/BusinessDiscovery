/**
 * Phase 2 price tags are EUR-only (indicative, admin-set). The BGN toggle
 * described in design.md §6.2b is scoped to Phase 6's "Approx. total" card
 * and budget slider — see formatAmountInCurrency below.
 */
export function formatPriceRange(min: number | null, max: number | null): string | null {
    if (min === null && max === null) return null;
    const fmt = (n: number) => `€${n.toLocaleString('en-US')}`;
    if (min !== null && max !== null && min !== max) return `${fmt(min)}–${fmt(max)}`;
    return fmt(min ?? max ?? 0);
}

export type DisplayCurrency = 'EUR' | 'BGN';

/** Bulgaria's fixed currency-board peg (1 EUR = 1.95583 BGN) — display-format conversion only. */
const EUR_TO_BGN = 1.95583;

/** All amounts are stored canonically in EUR; this only reformats for display. */
export function convertEurAmount(amountEur: number, currency: DisplayCurrency): number {
    return currency === 'BGN' ? amountEur * EUR_TO_BGN : amountEur;
}

export function formatAmountInCurrency(amountEur: number, currency: DisplayCurrency): string {
    const converted = Math.round(convertEurAmount(amountEur, currency));
    const symbol = currency === 'BGN' ? 'лв' : '€';
    return currency === 'BGN' ? `${converted.toLocaleString('en-US')} ${symbol}` : `${symbol}${converted.toLocaleString('en-US')}`;
}

export function formatAmountRangeInCurrency(minEur: number, maxEur: number, currency: DisplayCurrency): string {
    if (minEur === maxEur) return formatAmountInCurrency(minEur, currency);
    return `${formatAmountInCurrency(minEur, currency)} – ${formatAmountInCurrency(maxEur, currency)}`;
}
