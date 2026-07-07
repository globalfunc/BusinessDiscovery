import { Plus, X } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

export type ReferenceLinkNote = { url: string; note: string };

/**
 * Like ChipInput, but each entry carries its own free-text note ("what do
 * you like about it?") — Phase 3's reference links aren't flat strings, so a
 * plain ChipInput isn't enough here.
 */
export function ReferenceLinksWithNotes({
    values,
    onChange,
    urlPlaceholder,
    notePlaceholder,
    addLabel,
}: {
    values: ReferenceLinkNote[];
    onChange: (values: ReferenceLinkNote[]) => void;
    urlPlaceholder?: string;
    notePlaceholder?: string;
    addLabel: string;
}) {
    const [draftUrl, setDraftUrl] = useState('');

    const add = () => {
        const trimmed = draftUrl.trim();
        if (trimmed === '') return;
        onChange([...values, { url: trimmed, note: '' }]);
        setDraftUrl('');
    };

    const updateNote = (index: number, note: string) => {
        onChange(values.map((entry, i) => (i === index ? { ...entry, note } : entry)));
    };

    const remove = (index: number) => {
        onChange(values.filter((_, i) => i !== index));
    };

    return (
        <div className="flex flex-col gap-3">
            {values.map((entry, index) => (
                <div key={`${entry.url}-${index}`} className="flex flex-col gap-1.5 rounded-md border border-line bg-surface p-3">
                    <div className="flex items-center justify-between gap-2">
                        <a
                            href={entry.url}
                            target="_blank"
                            rel="noreferrer"
                            className="truncate font-ui text-xs font-medium text-accent hover:underline"
                        >
                            {entry.url}
                        </a>
                        <button
                            type="button"
                            onClick={() => remove(index)}
                            className="shrink-0 text-text-faint hover:text-red"
                            aria-label={`Remove ${entry.url}`}
                        >
                            <X className="h-3.5 w-3.5" />
                        </button>
                    </div>
                    <Textarea
                        value={entry.note}
                        onChange={(e) => updateNote(index, e.target.value)}
                        placeholder={notePlaceholder}
                        rows={2}
                        className="text-xs"
                    />
                </div>
            ))}

            <div className="flex gap-2">
                <Input
                    value={draftUrl}
                    placeholder={urlPlaceholder}
                    onChange={(e) => setDraftUrl(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            add();
                        }
                    }}
                    className="h-8 text-xs"
                />
                <Button type="button" variant="secondary" size="sm" onClick={add} className="shrink-0 gap-1">
                    <Plus className="h-3.5 w-3.5" />
                    {addLabel}
                </Button>
            </div>
        </div>
    );
}
