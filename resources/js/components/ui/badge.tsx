import * as React from 'react';

import { cn } from '@/lib/utils';

export type BadgeProps = React.HTMLAttributes<HTMLSpanElement> & {
    variant?: 'default' | 'accent' | 'blue' | 'muted' | 'red';
};

const variantClasses: Record<NonNullable<BadgeProps['variant']>, string> = {
    default: 'border-line-strong text-text-muted',
    accent: 'border-accent/40 text-accent',
    blue: 'border-blue/40 text-blue',
    muted: 'border-line text-text-faint',
    red: 'border-red/40 text-red',
};

const Badge = React.forwardRef<HTMLSpanElement, BadgeProps>(({ className, variant = 'default', ...props }, ref) => (
    <span
        ref={ref}
        className={cn(
            'inline-flex items-center rounded-full border px-2.5 py-0.5 font-ui text-xs font-medium',
            variantClasses[variant],
            className,
        )}
        {...props}
    />
));
Badge.displayName = 'Badge';

export { Badge };
