import AnalyticsOverview from '@/components/dashboard/AnalyticsOverview';
import { DashboardHeader } from '@/components/dashboard/DashboardHeader';
import MobileLayout from '@/layouts/mobile-layout';
import { Head, router } from '@inertiajs/react';

export default function Dashboard({
    account,
    stats,
    chartData,
    recentTransactions,
    filters,
    currencyBreakdown,
    availableCurrencies
}) {
    // Handle filter changes
    const handleYearChange = (year) => {
        router.get(`/account/${account.id}/dashboard?year=${year}&month=${filters.month}&mode=${filters.mode}`, {}, { preserveState: true });
    };

    const handleMonthChange = (month) => {
        router.get(`/account/${account.id}/dashboard?year=${filters.year}&month=${month}&mode=${filters.mode}`, {}, { preserveState: true });
    };

    const handleModeChange = (mode) => {
        router.get(`/account/${account.id}/dashboard?year=${filters.year}&month=${filters.month}&mode=${mode}`, {}, { preserveState: true });
    };

    return (
        <MobileLayout>
            <Head title={`${account.name} - Dashboard`} />
            <div className="relative">
                <DashboardHeader
                    stats={stats}
                    filters={filters}
                    onModeChange={handleModeChange}
                    onYearChange={handleYearChange}
                    onMonthChange={handleMonthChange}
                    currencyBreakdown={currencyBreakdown}
                    availableCurrencies={availableCurrencies}
                />

                {/* Analytics Content */}
                <div className="-mt-4 py-6">
                    <AnalyticsOverview
                        stats={stats}
                        chartData={chartData}
                        recentTransactions={recentTransactions}
                        currencyBreakdown={currencyBreakdown}
                        availableCurrencies={availableCurrencies}
                    />
                </div>
            </div>
        </MobileLayout>
    );
}
