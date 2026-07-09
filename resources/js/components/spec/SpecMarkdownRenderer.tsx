function renderInlineBold(text: string, keyPrefix: string) {
    const parts = text.split(/(\*\*[^*]+\*\*)/g);
    return parts.map((part, index) => {
        if (part.startsWith('**') && part.endsWith('**')) {
            return <strong key={`${keyPrefix}-${index}`}>{part.slice(2, -2)}</strong>;
        }
        return <span key={`${keyPrefix}-${index}`}>{part}</span>;
    });
}

/**
 * Renders the §7.5 spec markdown: `##`/`###` headings, `- `/`  - ` bullets
 * (one nesting level), everything else as a paragraph. Still a minimal
 * hand-rolled parser rather than a markdown library — spec.compile's task
 * instruction pins the AI output to exactly this shape, the same one the
 * deterministic fallback renderer produces. Shared by the BO-side Review
 * screen (§3.8) and the admin Spec review page (§6.4 — "same renderer").
 */
export function SpecMarkdownRenderer({ markdown }: { markdown: string }) {
    const lines = markdown.split('\n');

    return (
        <div className="flex flex-col gap-2">
            {lines.map((line, index) => {
                const key = `line-${index}`;
                if (line.startsWith('### ')) {
                    return (
                        <h4 key={key} className="mt-2 font-display text-base font-semibold text-text">
                            {line.slice(4)}
                        </h4>
                    );
                }
                if (line.startsWith('## ')) {
                    return (
                        <h3 key={key} className="mt-3 font-display text-lg font-semibold text-text first:mt-0">
                            {line.slice(3)}
                        </h3>
                    );
                }
                if (line.startsWith('# ')) {
                    return (
                        <h2 key={key} className="mt-3 font-display text-xl font-semibold text-text first:mt-0">
                            {line.slice(2)}
                        </h2>
                    );
                }
                if (line.startsWith('  - ')) {
                    return (
                        <p key={key} className="ml-6 font-body text-xs text-text-muted">
                            {renderInlineBold(line.slice(4), key)}
                        </p>
                    );
                }
                if (line.startsWith('- ')) {
                    return (
                        <p key={key} className="font-body text-sm text-text">
                            {renderInlineBold(line.slice(2), key)}
                        </p>
                    );
                }
                if (line.trim() === '') {
                    return null;
                }
                return (
                    <p key={key} className="font-body text-sm text-text-muted">
                        {renderInlineBold(line, key)}
                    </p>
                );
            })}
        </div>
    );
}
