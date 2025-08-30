import { Link, usePage } from '@inertiajs/react';
import { BalanceCard } from './BalanceCard';
import { PeriodSelector } from './PeriodSelector';

export function DashboardHeader({ stats, filters, onModeChange, onYearChange, onMonthChange, currencyBreakdown, availableCurrencies }) {
    const { auth } = usePage().props;

    return (
        <div className="rounded-b-3xl bg-gradient-to-r from-green-500 to-green-600 px-3 py-4 text-white sm:px-4 sm:py-6">
            <div className="mb-4 flex items-center justify-between sm:mb-6">
                <div className="flex items-center space-x-3">
                    <Link
                        href={`/account/${auth.account.id}/dashboard`}
                        className="flex h-12 w-12 items-center justify-center rounded-full bg-white/20 p-2 transition-colors hover:bg-white/30"
                    >
                        <img src="/logo-icon.svg" alt="Logo" className="h-full w-full object-contain" />
                    </Link>
                    <div>
                        <h1 className="text-lg font-bold sm:text-xl">Dashboard</h1>
                        <p className="text-xs text-green-100 sm:text-sm">{stats.currentPeriod}</p>
                    </div>
                </div>
                <div className="flex items-center gap-2 flex-shrink-0">
                    <Link
                        href="/account"
                        className="px-3 py-2 bg-white/20 hover:bg-white/30 text-white rounded-lg transition-colors duration-300 text-sm font-medium whitespace-nowrap"
                    >
                        Ganti Akun
                    </Link>
                </div>
            </div>

            {/* Period Controls */}
            <div className="mb-4 sm:mb-6">
                <PeriodSelector filters={filters} onModeChange={onModeChange} onYearChange={onYearChange} onMonthChange={onMonthChange} />
            </div>

            {/* Clean Balance Card */}
            <BalanceCard stats={stats} currencyBreakdown={currencyBreakdown} filters={filters} availableCurrencies={availableCurrencies} />
        </div>
    );
}
