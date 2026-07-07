import { useState } from 'react';
import { Sparkles } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';

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
 * Renders the deterministic markdown produced by DiscoverySpecRenderer
 * (§7.5 skeleton): `## ` headings, `- `/`  - ` bullets (one nesting level),
 * everything else as a paragraph. Intentionally a minimal hand-rolled parser
 * rather than a markdown library dependency — the shape it needs to support
 * is fixed and small (see the renderer's own doc comment).
 */
function MarkdownPreview({ markdown }: { markdown: string }) {
    const lines = markdown.split('\n');

    return (
        <div className="flex flex-col gap-2">
            {lines.map((line, index) => {
                const key = `line-${index}`;
                if (line.startsWith('## ')) {
                    return (
                        <h3 key={key} className="mt-3 font-display text-lg font-semibold text-text first:mt-0">
                            {line.slice(3)}
                        </h3>
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

export function ReviewPreview({
    t,
    markdown,
}: {
    t: (key: string, vars?: Record<string, string>) => string;
    markdown: string;
}) {
    const [amendInstruction, setAmendInstruction] = useState('');

    return (
        <div className="flex flex-col gap-6">
            <div className="rounded-md border border-line bg-surface-2 p-4">
                <MarkdownPreview markdown={markdown} />
            </div>

            <div className="flex flex-col gap-2 rounded-md border border-line bg-surface p-4">
                <p className="font-ui text-xs font-semibold uppercase tracking-wide text-text-muted">{t('review.amendHeading')}</p>
                <Textarea
                    value={amendInstruction}
                    onChange={(e) => setAmendInstruction(e.target.value)}
                    placeholder={t('review.amendPlaceholder')}
                    rows={3}
                />
                <div className="flex items-center justify-between gap-3">
                    <p className="font-body text-xs text-text-faint">{t('review.amendComingSoon')}</p>
                    <Button type="button" variant="secondary" size="sm" disabled className="shrink-0 gap-1.5 border-accent/40 text-accent">
                        <Sparkles className="h-3.5 w-3.5" />
                        {t('review.amendCta')}
                    </Button>
                </div>
            </div>
        </div>
    );
}
