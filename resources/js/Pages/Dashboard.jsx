import AnalyticsOverview from '@/components/dashboard/AnalyticsOverview';
import { DashboardHeader } from '@/components/dashboard/DashboardHeader';
import MobileLayout from '@/layouts/mobile-layout';
import { ChatBubble } from '@/components/ai/ChatBubble';
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

            <div className="fixed bottom-36 right-1/2 transform translate-x-1/2 max-w-[480px] w-full">
                <div className="absolute right-4">
                    <ChatBubble
                        context="dashboard"
                        contextData={{
                            accountId: account.id,
                            account,
                            stats,
                            chartData,
                            recentTransactions,
                            filters,
                            currencyBreakdown,
                            availableCurrencies
                        }}
                    />
                </div>
            </div>
        </MobileLayout>
    );
}
