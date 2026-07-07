import { ChevronDown } from 'lucide-react';
import * as React from 'react';

import { cn } from '@/lib/utils';

export type SelectProps = React.SelectHTMLAttributes<HTMLSelectElement>;

const Select = React.forwardRef<HTMLSelectElement, SelectProps>(({ className, children, ...props }, ref) => {
    return (
        <div className="relative">
            <select
                className={cn(
                    'flex h-10 w-full appearance-none rounded-md border border-line-strong bg-surface-2 px-3 py-2 pr-9 font-body text-sm text-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/50 disabled:cursor-not-allowed disabled:opacity-50',
                    className,
                )}
                ref={ref}
                {...props}
            >
                {children}
            </select>
            <ChevronDown className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-faint" />
        </div>
    );
});
Select.displayName = 'Select';

export { Select };
