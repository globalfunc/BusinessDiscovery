import { FileText, Upload as UploadIcon, X } from 'lucide-react';
import { useRef, useState } from 'react';

import { cn } from '@/lib/utils';

export type UploadRecord = {
    id: number;
    original_name: string;
    mime: string;
    size: number;
    kind: 'logo' | 'image' | 'document';
    url: string;
    thumbnail_url: string | null;
};

function formatBytes(bytes: number): string {
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function UploadZone({
    uploads,
    quotaUsed,
    quotaLimit,
    onUpload,
    onRemove,
    dropLabel,
    browseLabel,
    quotaLabel,
    removeLabel,
    error,
}: {
    uploads: UploadRecord[];
    quotaUsed: number;
    quotaLimit: number;
    onUpload: (file: File) => void;
    onRemove: (id: number) => void;
    dropLabel: string;
    browseLabel: string;
    quotaLabel: (used: string, limit: string) => string;
    removeLabel: string;
    error: string | null;
}) {
    const [dragOver, setDragOver] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

    const quotaRatio = quotaLimit > 0 ? quotaUsed / quotaLimit : 0;
    const isNearLimit = quotaRatio > 0.8;

    const handleFiles = (files: FileList | null) => {
        if (!files || files.length === 0) return;
        Array.from(files).forEach((file) => onUpload(file));
        if (inputRef.current) inputRef.current.value = '';
    };

    return (
        <div className="flex flex-col gap-3">
            <div
                role="button"
                tabIndex={0}
                onClick={() => inputRef.current?.click()}
                onKeyDown={(e) => {
                    if (e.key === 'Enter' || e.key === ' ') inputRef.current?.click();
                }}
                onDragOver={(e) => {
                    e.preventDefault();
                    setDragOver(true);
                }}
                onDragLeave={() => setDragOver(false)}
                onDrop={(e) => {
                    e.preventDefault();
                    setDragOver(false);
                    handleFiles(e.dataTransfer.files);
                }}
                className={cn(
                    'flex cursor-pointer flex-col items-center justify-center gap-2 rounded-md border-2 border-dashed p-8 text-center transition-colors',
                    dragOver ? 'border-accent bg-accent/5 shadow-[0_0_0_3px_var(--lb-accent-glow)]' : 'border-line-strong hover:border-line-accent',
                )}
            >
                <UploadIcon className="h-6 w-6 text-text-faint" aria-hidden />
                <p className="font-ui text-sm text-text-muted">{dropLabel}</p>
                <p className="font-ui text-xs font-medium text-accent underline-offset-2 hover:underline">{browseLabel}</p>
                <input
                    ref={inputRef}
                    type="file"
                    className="hidden"
                    multiple
                    accept=".csv,.xlsx,.pdf,.docx,.txt,.png,.jpg,.jpeg,.webp,.svg"
                    onChange={(e) => handleFiles(e.target.files)}
                />
            </div>

            {error && <p className="font-body text-xs text-red">{error}</p>}

            <div className="flex flex-col gap-1.5">
                <div className="h-1.5 w-full overflow-hidden rounded-full bg-surface-2">
                    <div
                        className={cn('h-full rounded-full transition-all', isNearLimit ? 'bg-red' : 'bg-text-faint')}
                        style={{ width: `${Math.min(100, quotaRatio * 100)}%` }}
                    />
                </div>
                <p className={cn('font-ui text-xs', isNearLimit ? 'text-red' : 'text-text-faint')}>
                    {quotaLabel(formatBytes(quotaUsed), formatBytes(quotaLimit))}
                </p>
            </div>

            {uploads.length > 0 && (
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
                    {uploads.map((upload) => (
                        <div key={upload.id} className="group relative flex flex-col gap-1 rounded-md border border-line bg-surface p-2">
                            <button
                                type="button"
                                onClick={() => onRemove(upload.id)}
                                aria-label={`${removeLabel} ${upload.original_name}`}
                                className="absolute right-1 top-1 flex h-6 w-6 items-center justify-center rounded-full bg-surface-2 text-text-faint opacity-0 transition-opacity hover:text-red group-hover:opacity-100"
                            >
                                <X className="h-3.5 w-3.5" />
                            </button>
                            <div className="flex aspect-square items-center justify-center overflow-hidden rounded bg-surface-2">
                                {upload.thumbnail_url || upload.kind === 'image' ? (
                                    <img
                                        src={upload.thumbnail_url ?? upload.url}
                                        alt={upload.original_name}
                                        className="h-full w-full object-cover"
                                    />
                                ) : (
                                    <FileText className="h-6 w-6 text-text-faint" aria-hidden />
                                )}
                            </div>
                            <p className="truncate font-ui text-[11px] text-text-muted" title={upload.original_name}>
                                {upload.original_name}
                            </p>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
