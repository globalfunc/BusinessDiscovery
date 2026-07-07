/**
 * Phase 2 price tags are EUR-only (indicative, admin-set). The BGN toggle
 * described in design.md §6.2b is scoped to Phase 6's "Approx. total" card;
 * see S2.3 handoff notes for the follow-up to revisit here in S2.6.
 */
export function formatPriceRange(min: number | null, max: number | null): string | null {
    if (min === null && max === null) return null;
    const fmt = (n: number) => `€${n.toLocaleString('en-US')}`;
    if (min !== null && max !== null && min !== max) return `${fmt(min)}–${fmt(max)}`;
    return fmt(min ?? max ?? 0);
}
