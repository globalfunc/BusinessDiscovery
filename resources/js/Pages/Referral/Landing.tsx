import { Head, useForm } from '@inertiajs/react';
import { type FormEventHandler } from 'react';

import { LanguageToggle } from '@/components/discovery/LanguageToggle';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation, type Locale } from '@/lib/i18n';

type BusinessOwner = {
    name: string;
    company: string;
    logo_path: string | null;
    greeting_override: string | null;
};

export default function ReferralLanding({
    token,
    confirmed,
    hasStartedDiscovery,
    businessOwner,
    language,
}: {
    token: string;
    confirmed: boolean;
    hasStartedDiscovery: boolean;
    businessOwner: BusinessOwner;
    language: string;
}) {
    const locale = (language === 'bg' ? 'bg' : 'en') as Locale;
    const t = useTranslation(locale);

    const { data, setData, post, processing } = useForm({
        company: businessOwner.company,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('referral.confirm', token));
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-bg px-4">
            <Head title={businessOwner.company} />
            <div className="w-full max-w-md animate-rise">
                <div className="mb-6 flex items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        {businessOwner.logo_path && (
                            <img
                                src={businessOwner.logo_path}
                                alt={businessOwner.company}
                                className="h-10 w-10 rounded-md border border-line object-cover"
                            />
                        )}
                        <span className="font-ui text-sm font-semibold tracking-tight text-text">
                            {businessOwner.company}
                        </span>
                    </div>
                    <LanguageToggle locale={locale} />
                </div>

                <Card className="border-line-strong bg-surface-glass backdrop-blur-2xl">
                    {!confirmed ? (
                        <>
                            <CardHeader>
                                <p className="eyebrow text-accent">BusinessDiscovery</p>
                                <CardTitle className="font-display text-2xl font-semibold">
                                    {t('greeting.confirmTitle')}
                                </CardTitle>
                                <CardDescription>{t('greeting.confirmBody')}</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={submit} className="flex flex-col gap-4">
                                    <div className="flex flex-col gap-1.5">
                                        <Label htmlFor="company">Company</Label>
                                        <Input
                                            id="company"
                                            value={data.company}
                                            onChange={(e) => setData('company', e.target.value)}
                                        />
                                    </div>
                                    <Button type="submit" disabled={processing}>
                                        {t('greeting.confirmCta')}
                                    </Button>
                                </form>
                            </CardContent>
                        </>
                    ) : (
                        <>
                            <CardHeader className="items-center text-center">
                                {businessOwner.logo_path && (
                                    <img
                                        src={businessOwner.logo_path}
                                        alt={businessOwner.company}
                                        className="mb-2 h-16 w-16 rounded-lg border border-line-strong object-cover"
                                    />
                                )}
                                <p className="eyebrow text-accent">BusinessDiscovery</p>
                                <CardTitle className="font-display text-2xl font-semibold">
                                    {businessOwner.greeting_override || t('greeting.title', { name: businessOwner.name })}
                                </CardTitle>
                                {!businessOwner.greeting_override && (
                                    <CardDescription>{t('greeting.body', { company: businessOwner.company })}</CardDescription>
                                )}
                            </CardHeader>
                            <CardContent className="flex justify-center">
                                <Button asChild>
                                    <a href={route('discovery.show')}>
                                        {hasStartedDiscovery ? t('common.resumeDiscovery') : t('common.startDiscovery')}
                                    </a>
                                </Button>
                            </CardContent>
                        </>
                    )}
                </Card>
            </div>
        </div>
    );
}
