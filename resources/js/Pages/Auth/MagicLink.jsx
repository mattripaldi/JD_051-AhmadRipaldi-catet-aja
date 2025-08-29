import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { useState } from 'react';

import InputError from '@/components/common/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

export default function MagicLink({ status }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
    });
    const [feedback, setFeedback] = useState('');

    const submit = (e) => {
        e.preventDefault();
        setFeedback('');

        post(route('magic-link.send'), {
            onSuccess: () => {
                reset('email');
                setFeedback('Magic link berhasil dikirim! Silakan periksa email Anda.');
            },
            onError: () => {
                setFeedback('Gagal mengirim magic link. Silakan coba lagi.');
            }
        });
    };

    return (
        <AuthLayout title="Masuk dengan Magic Link" description="Masukkan alamat email Anda dan kami akan mengirimkan magic link untuk masuk">
            <Head title="Magic Link" />

            <form className="flex flex-col gap-6" onSubmit={submit}>
                <div className="grid gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="email">Alamat Email</Label>
                        <Input
                            id="email"
                            type="email"
                            required
                            autoFocus
                            tabIndex={1}
                            autoComplete="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            placeholder="email@example.com"
                        />
                        <InputError message={errors.email} />
                    </div>

                    <Button type="submit" className="mt-4 w-full" tabIndex={2} disabled={processing}>
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        Kirim Magic Link
                    </Button>
                </div>

                <div className="text-muted-foreground text-center text-sm">
                    <a href={route('login')} className="underline hover:no-underline" tabIndex={3}>
                        Kembali ke login
                    </a>
                </div>
            </form>

            {feedback && (
                <div className={`mb-4 text-center text-sm font-medium text-green-600`}>
                    {feedback}
                </div>
            )}

            {status && <div className="mb-4 text-center text-sm font-medium text-green-600">{status}</div>}
        </AuthLayout>
    );
}
