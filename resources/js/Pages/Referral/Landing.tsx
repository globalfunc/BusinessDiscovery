import { Head, useForm } from '@inertiajs/react';
import { type FormEventHandler } from 'react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type BusinessOwner = {
    name: string;
    company: string;
    logo_path: string | null;
    greeting_override: string | null;
    language: string;
};

const copy = {
    bg: {
        confirmTitle: 'Потвърдете фирмата си',
        confirmBody: 'Преди да продължим, потвърдете, че това е вашата фирма.',
        confirmCta: 'Да, това е моята фирма',
        greetingFallback: (name: string, company: string) =>
            `Здравейте, ${name}! Подготвили сме кратко интерактивно интервю, което ще ни помогне да разберем ${company} и да предложим точното онлайн решение за вашия бизнес.`,
        stubNote: 'Интервюто за откриване стартира скоро — засега вашата връзка е потвърдена.',
    },
    en: {
        confirmTitle: 'Confirm your business',
        confirmBody: 'Before we continue, please confirm this is your business.',
        confirmCta: 'Yes, this is my business',
        greetingFallback: (name: string, company: string) =>
            `Hi ${name}! We've prepared a short interactive interview to understand ${company} and shape the right online solution for your business.`,
        stubNote: 'The discovery interview lands soon — for now your link is confirmed and working.',
    },
};

export default function ReferralLanding({
    token,
    confirmed,
    businessOwner,
}: {
    token: string;
    confirmed: boolean;
    businessOwner: BusinessOwner;
}) {
    const lang = businessOwner.language === 'bg' ? 'bg' : 'en';
    const t = copy[lang];

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
                <div className="mb-6 flex flex-col items-center gap-3 text-center">
                    {businessOwner.logo_path && (
                        <img
                            src={businessOwner.logo_path}
                            alt={businessOwner.company}
                            className="h-14 w-14 rounded-md border border-line object-cover"
                        />
                    )}
                    <span className="font-ui text-sm font-semibold tracking-tight text-text">
                        {businessOwner.company}
                    </span>
                </div>

                <Card className="border-line-strong bg-surface-glass backdrop-blur-2xl">
                    {!confirmed ? (
                        <>
                            <CardHeader>
                                <p className="eyebrow text-accent">BusinessDiscovery</p>
                                <CardTitle className="font-display text-2xl font-semibold">
                                    {t.confirmTitle}
                                </CardTitle>
                                <CardDescription>{t.confirmBody}</CardDescription>
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
                                        {t.confirmCta}
                                    </Button>
                                </form>
                            </CardContent>
                        </>
                    ) : (
                        <>
                            <CardHeader>
                                <p className="eyebrow text-accent">BusinessDiscovery</p>
                                <CardTitle className="font-display text-2xl font-semibold">
                                    {businessOwner.greeting_override ||
                                        t.greetingFallback(businessOwner.name, businessOwner.company)}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="font-body text-sm text-text-muted">{t.stubNote}</p>
                            </CardContent>
                        </>
                    )}
                </Card>
            </div>
        </div>
    );
}
