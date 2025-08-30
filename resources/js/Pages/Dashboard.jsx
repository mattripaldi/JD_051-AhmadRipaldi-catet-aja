import MobileLayout from "@/layouts/mobile-layout";
import { Head, Link, usePage } from '@inertiajs/react';
import { ModalLink } from '@inertiaui/modal-react';
import { Button } from '@/components/ui/button';

const DashboardHeader = () => {
    const { auth } = usePage().props;

    return (
        <div className="rounded-b-3xl bg-gradient-to-r from-green-500 to-green-600 px-3 py-4 text-white sm:px-4 sm:py-6">
            <div className="flex items-center justify-between">
                <div className="flex items-center space-x-3">
                    <Link
                        href={route('account.dashboard', auth?.account?.id)}
                        className="flex h-12 w-12 items-center justify-center rounded-full bg-white/20 p-2 transition-colors hover:bg-white/30"
                    >
                        <img src="/logo-icon.svg" alt="Catet Dulu Logo" className="h-full w-full object-contain" />
                    </Link>
                    <div>
                        <h1 className="text-lg font-bold sm:text-xl">Catet Dulu</h1>
                        <p className="text-xs text-green-100 sm:text-sm">Teman Keuanganmu</p>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2 md:flex-row">
                    <Link
                        href="/account"
                        className="px-4 py-2 bg-white/20 hover:bg-white/30 text-white rounded-lg transition-colors duration-300 text-sm font-medium"
                    >
                        Change Account
                    </Link>
                </div>
            </div>
        </div>
    );
};

export default function Dashboard({ account }) {
    return (
        <MobileLayout header={<DashboardHeader />}>
            <Head title={`Dashboard`} />
            <div className="flex items-center justify-center min-h-screen">
                <div className="text-center space-y-6">
                    <div>
                        <h1 className="text-4xl font-bold text-gray-800 mb-4">
                            {account ? `Dashboard ${account.name}` : 'Halo Dunia'}
                        </h1>
                        <p className="text-lg text-gray-600">
                            {account?.description || 'Selamat datang'}
                        </p>
                    </div>

                </div>
            </div>
        </MobileLayout>
    );
}
