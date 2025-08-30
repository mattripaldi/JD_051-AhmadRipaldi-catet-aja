import { Link, usePage } from '@inertiajs/react';

export function AccountHeader() {
    return (
        <div className="rounded-b-3xl bg-gradient-to-r from-green-500 to-green-600 px-3 py-4 text-white sm:px-4 sm:py-6">
            <div className="flex items-center space-x-3">
                <Link
                    href="/dashboard"
                    className="flex h-12 w-12 items-center justify-center rounded-full bg-white/20 p-2 transition-colors hover:bg-white/30"
                >
                    <img src="/logo-icon.svg" alt="Logo" className="h-full w-full object-contain" />
                </Link>
                <div>
                    <h1 className="text-lg font-bold sm:text-xl">Dashboard</h1>
                    <p className="text-xs text-green-100 sm:text-sm">Teman Keuanganmu</p>
                </div>
            </div>
        </div>
    );
}

export default function AccountLayout({ children, showHeader = true }) {
    return (
        <div className="min-h-screen bg-gray-100">
            <div className="max-w-[480px] mx-auto bg-white min-h-screen relative overflow-x-hidden">
                {showHeader && <AccountHeader />}
                {children}
            </div>
        </div>
    );
}
