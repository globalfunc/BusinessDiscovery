export type DiffLine = {
    type: 'added' | 'removed' | 'unchanged';
    text: string;
};

/**
 * Minimal LCS-based line diff for the admin spec version-compare view.
 * Spec markdown documents are short (tens of lines), so an O(n*m) table is
 * cheap — no need to pull in a diff library for this one screen.
 */
export function lineDiff(before: string, after: string): DiffLine[] {
    const a = before.split('\n');
    const b = after.split('\n');
    const n = a.length;
    const m = b.length;

    const lcs: number[][] = Array.from({ length: n + 1 }, () => new Array(m + 1).fill(0));
    for (let i = n - 1; i >= 0; i--) {
        for (let j = m - 1; j >= 0; j--) {
            lcs[i][j] = a[i] === b[j] ? lcs[i + 1][j + 1] + 1 : Math.max(lcs[i + 1][j], lcs[i][j + 1]);
        }
    }

    const result: DiffLine[] = [];
    let i = 0;
    let j = 0;
    while (i < n && j < m) {
        if (a[i] === b[j]) {
            result.push({ type: 'unchanged', text: a[i] });
            i++;
            j++;
        } else if (lcs[i + 1][j] >= lcs[i][j + 1]) {
            result.push({ type: 'removed', text: a[i] });
            i++;
        } else {
            result.push({ type: 'added', text: b[j] });
            j++;
        }
    }
    while (i < n) {
        result.push({ type: 'removed', text: a[i] });
        i++;
    }
    while (j < m) {
        result.push({ type: 'added', text: b[j] });
        j++;
    }

    return result;
}
