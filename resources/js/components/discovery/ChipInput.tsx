import { Plus, X } from 'lucide-react';
import { useState } from 'react';

import { Input } from '@/components/ui/input';

/**
 * Small chip-list editor (add on Enter, remove via ×). Used for editable
 * feature lists and reference-link lists across Phase 2's service cards.
 */
export function ChipInput({
    values,
    onChange,
    placeholder,
    className,
}: {
    values: string[];
    onChange: (values: string[]) => void;
    placeholder?: string;
    className?: string;
}) {
    const [draft, setDraft] = useState('');

    const add = () => {
        const trimmed = draft.trim();
        if (trimmed && !values.includes(trimmed)) {
            onChange([...values, trimmed]);
        }
        setDraft('');
    };

    return (
        <div className={className}>
            <div className="flex flex-wrap gap-1.5">
                {values.map((value, index) => (
                    <span
                        key={`${value}-${index}`}
                        className="inline-flex items-center gap-1 rounded-full border border-line-strong bg-surface-2 px-2.5 py-1 font-body text-xs text-text"
                    >
                        {value}
                        <button
                            type="button"
                            onClick={() => onChange(values.filter((_, i) => i !== index))}
                            className="text-text-faint hover:text-red"
                            aria-label={`Remove ${value}`}
                        >
                            <X className="h-3 w-3" />
                        </button>
                    </span>
                ))}
            </div>
            <div className="mt-1.5 flex gap-2">
                <Input
                    value={draft}
                    placeholder={placeholder}
                    onChange={(e) => setDraft(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            add();
                        }
                    }}
                    className="h-8 text-xs"
                />
                <button
                    type="button"
                    onClick={add}
                    className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md border border-line-strong text-text-muted hover:border-line-accent hover:text-text"
                    aria-label="Add"
                >
                    <Plus className="h-3.5 w-3.5" />
                </button>
            </div>
        </div>
    );
}
