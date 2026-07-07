import { Head, useForm } from '@inertiajs/react';
import { type FormEventHandler } from 'react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login.store'));
    };

    return (
        <>
            <Head title="Admin Login" />
            <div className="flex min-h-screen items-center justify-center bg-bg px-4">
                <div className="w-full max-w-sm animate-rise">
                    <div className="mb-6 flex items-center justify-center gap-2">
                        <div className="h-2 w-2 rounded-full bg-gradient-to-r from-accent to-accent-2" />
                        <span className="font-ui text-sm font-semibold tracking-tight text-text">
                            BusinessDiscovery
                        </span>
                    </div>

                    <Card className="border-line-strong bg-surface-glass backdrop-blur-2xl">
                        <CardHeader>
                            <p className="eyebrow text-accent">Admin</p>
                            <CardTitle className="font-display text-2xl font-semibold">Sign in</CardTitle>
                            <CardDescription>Operator access only — no self-registration.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="flex flex-col gap-4">
                                <div className="flex flex-col gap-1.5">
                                    <label htmlFor="email" className="font-ui text-xs text-text-muted">
                                        Email
                                    </label>
                                    <Input
                                        id="email"
                                        type="email"
                                        autoComplete="username"
                                        autoFocus
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                    />
                                    {errors.email && <p className="font-body text-xs text-red">{errors.email}</p>}
                                </div>

                                <div className="flex flex-col gap-1.5">
                                    <label htmlFor="password" className="font-ui text-xs text-text-muted">
                                        Password
                                    </label>
                                    <Input
                                        id="password"
                                        type="password"
                                        autoComplete="current-password"
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                    />
                                    {errors.password && (
                                        <p className="font-body text-xs text-red">{errors.password}</p>
                                    )}
                                </div>

                                <Button type="submit" disabled={processing} className="mt-2">
                                    Sign in
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
