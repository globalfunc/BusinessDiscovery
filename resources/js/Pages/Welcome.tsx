import { Head } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Toaster } from '@/components/ui/toaster';
import { useToast } from '@/components/ui/use-toast';

const tokenSwatches = [
    { name: 'bg', className: 'bg-bg' },
    { name: 'surface', className: 'bg-surface' },
    { name: 'surface-2', className: 'bg-surface-2' },
    { name: 'accent', className: 'bg-gradient-to-r from-accent to-accent-2' },
    { name: 'blue', className: 'bg-blue' },
    { name: 'teal', className: 'bg-teal' },
    { name: 'red', className: 'bg-red' },
];

export default function Welcome() {
    const { toast } = useToast();

    return (
        <>
            <Head title="Welcome" />
            <main className="min-h-screen bg-bg px-4 py-16 sm:px-8">
                <div className="mx-auto max-w-3xl">
                    <div className="animate-rise">
                        <p className="eyebrow text-accent">Stage 0 — Bootstrap</p>
                        <h1 className="mt-3 font-display text-5xl font-semibold text-text">
                            BusinessDiscovery
                        </h1>
                        <p className="mt-4 max-w-xl font-body text-lg text-text-muted">
                            Dark charcoal-deep-blue base, golden-orange accent, Fraunces / Space
                            Grotesk / Inter loaded, reset applied, reduced-motion respected.
                        </p>
                    </div>

                    <Card className="mt-10 animate-rise border-line-strong bg-surface-glass backdrop-blur-2xl">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Sparkles className="h-4 w-4 text-accent" />
                                Token & component check
                            </CardTitle>
                            <CardDescription>
                                Color tokens, typography roles, and shadcn-style primitives
                                restyled to the dark palette.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-6">
                            <div className="flex flex-wrap gap-3">
                                {tokenSwatches.map((swatch) => (
                                    <div key={swatch.name} className="flex flex-col items-center gap-2">
                                        <div
                                            className={`h-12 w-12 rounded-md border border-line ${swatch.className}`}
                                        />
                                        <span className="font-ui text-xs text-text-faint">
                                            {swatch.name}
                                        </span>
                                    </div>
                                ))}
                            </div>

                            <div className="flex flex-wrap items-center gap-3">
                                <Button
                                    onClick={() =>
                                        toast({
                                            title: 'Saved',
                                            description: 'Autosave pill pattern, teal-confirmation only.',
                                        })
                                    }
                                >
                                    Primary CTA
                                </Button>
                                <Button variant="secondary">Secondary</Button>
                                <Button variant="ghost">Ghost</Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </main>
            <Toaster />
        </>
    );
}
