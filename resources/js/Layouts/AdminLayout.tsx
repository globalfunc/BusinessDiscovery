import { Link, router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    FileSignature,
    FileText,
    Kanban,
    LayoutDashboard,
    Layers,
    LogOut,
    Settings2,
    ShieldAlert,
    Users,
} from 'lucide-react';
import { type ReactNode, useEffect, useRef } from 'react';

import { Toaster } from '@/components/ui/toaster';
import { useToast } from '@/components/ui/use-toast';
import { cn } from '@/lib/utils';

type NavItem = {
    label: string;
    icon: typeof LayoutDashboard;
    routeName?: string;
    href?: string;
};

const navItems: NavItem[] = [
    { label: 'Dashboard', icon: LayoutDashboard, routeName: 'admin.dashboard', href: '/admin/dashboard' },
    { label: 'Business Owners', icon: Users, routeName: 'admin.business-owners.index', href: '/admin/business-owners' },
    { label: 'Pipeline', icon: Kanban, routeName: 'admin.pipeline.index', href: '/admin/pipeline' },
    { label: 'Specs', icon: FileText },
    { label: 'Proposals', icon: FileSignature },
    { label: 'Content', icon: Layers, routeName: 'admin.content.index', href: '/admin/content' },
    { label: 'Vendor blocklist', icon: ShieldAlert, routeName: 'admin.vendor-blocklist.index', href: '/admin/vendor-blocklist' },
    { label: 'AI Settings', icon: Settings2 },
    { label: 'Usage', icon: BarChart3 },
];

export default function AdminLayout({ children }: { children: ReactNode }) {
    const { url, props } = usePage<{ flash?: { success?: string | null } }>();
    const { toast } = useToast();
    const lastFlash = useRef<string | null>(null);

    useEffect(() => {
        const message = props.flash?.success;
        if (message && message !== lastFlash.current) {
            lastFlash.current = message;
            toast({ title: message });
        }
    }, [props.flash?.success, toast]);

    return (
        <div className="flex min-h-screen bg-bg">
            <aside className="flex w-60 shrink-0 flex-col border-r border-line bg-bg-elevated">
                <div className="flex h-16 items-center gap-2 px-6">
                    <div className="h-2 w-2 rounded-full bg-gradient-to-r from-accent to-accent-2" />
                    <span className="font-ui text-sm font-semibold tracking-tight text-text">
                        BusinessDiscovery
                    </span>
                </div>

                <nav className="flex flex-1 flex-col gap-1 px-3 py-2">
                    {navItems.map((item) => {
                        const active = Boolean(item.href) && url.startsWith(item.href!);
                        const Icon = item.icon;

                        if (!item.href) {
                            return (
                                <span
                                    key={item.label}
                                    aria-disabled="true"
                                    title="Coming soon"
                                    className="flex cursor-not-allowed items-center gap-3 rounded-md px-3 py-2 font-ui text-sm text-text-faint/60"
                                >
                                    <Icon className="h-4 w-4" />
                                    {item.label}
                                </span>
                            );
                        }

                        return (
                            <Link
                                key={item.label}
                                href={item.href}
                                className={cn(
                                    'flex items-center gap-3 rounded-md px-3 py-2 font-ui text-sm transition-colors',
                                    active
                                        ? 'bg-surface-2 text-text before:content-none'
                                        : 'text-text-muted hover:bg-surface hover:text-text',
                                )}
                                style={active ? { boxShadow: 'inset 2px 0 0 var(--lb-blue)' } : undefined}
                            >
                                <Icon className={cn('h-4 w-4', active && 'text-blue')} />
                                {item.label}
                            </Link>
                        );
                    })}
                </nav>

                <div className="border-t border-line px-3 py-3">
                    <button
                        type="button"
                        onClick={() => router.post(route('logout'))}
                        className="flex w-full items-center gap-3 rounded-md px-3 py-2 font-ui text-sm text-text-muted transition-colors hover:bg-surface hover:text-text"
                    >
                        <LogOut className="h-4 w-4" />
                        Log out
                    </button>
                </div>
            </aside>

            <main className="flex-1 overflow-y-auto px-8 py-8">{children}</main>
            <Toaster />
        </div>
    );
}
