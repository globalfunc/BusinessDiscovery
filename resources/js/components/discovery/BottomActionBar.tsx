import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type SaveState = 'idle' | 'saving' | 'saved';

export function BottomActionBar({
    onBack,
    onContinue,
    backLabel,
    continueLabel,
    savingLabel,
    savedLabel,
    saveState,
    backDisabled,
    continueDisabled,
}: {
    onBack?: () => void;
    onContinue: () => void;
    backLabel: string;
    continueLabel: string;
    savingLabel: string;
    savedLabel: string;
    saveState: SaveState;
    backDisabled?: boolean;
    continueDisabled?: boolean;
}) {
    return (
        <div className="sticky bottom-0 z-20 border-t border-line bg-surface-glass px-4 py-3 backdrop-blur-2xl">
            <div className="mx-auto flex max-w-[640px] items-center justify-between gap-3">
                <Button variant="ghost" onClick={onBack} disabled={backDisabled}>
                    {backLabel}
                </Button>

                <span
                    className={cn(
                        'font-ui text-xs font-medium text-teal transition-opacity duration-200',
                        saveState === 'idle' ? 'opacity-0' : 'opacity-100',
                    )}
                    role="status"
                    aria-live="polite"
                >
                    {saveState === 'saving' ? savingLabel : saveState === 'saved' ? savedLabel : ''}
                </span>

                <Button onClick={onContinue} disabled={continueDisabled}>
                    {continueLabel}
                </Button>
            </div>
        </div>
    );
}
