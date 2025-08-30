import { Link, router, usePage } from '@inertiajs/react';
import { IncomeBalanceCard } from './IncomeBalanceCard';
import { PeriodSelector } from '../dashboard/PeriodSelector';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Check } from 'lucide-react';

export function IncomeHeader({ stats, filters, onModeChange, onYearChange, onMonthChange, currencyBreakdown, availableCurrencies, currencies }) {
    const { auth } = usePage().props;

    return (
        <div className="rounded-b-3xl bg-gradient-to-r from-green-500 to-green-600 px-3 py-4 text-white sm:px-4 sm:py-6">
            <div className="mb-4 flex items-center justify-between sm:mb-6">
                <div className="flex items-center space-x-3">
                    <Link
                        href={`/account/${auth.account.id}/income`}
                        className="flex h-12 w-12 items-center justify-center rounded-full bg-white/20 p-2 transition-colors hover:bg-white/30"
                    >
                        <img src="/logo-icon.svg" alt="Logo" className="h-full w-full object-contain" />
                    </Link>
                    <div>
                        <h1 className="text-lg font-bold sm:text-xl">Pemasukan</h1>
                        <p className="text-xs text-green-100 sm:text-sm">{stats.currentPeriod}</p>
                    </div>
                </div>
                <div className="flex items-center gap-2 flex-shrink-0">
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="px-3 py-2 bg-white/20 hover:bg-white/30 text-white rounded-lg transition-colors duration-300 text-sm font-medium"
                            >
                                {(() => {
                                    if (currencies && currencies.length > 0) {
                                        const selectedCurrency = filters.currency_id ?
                                            currencies.find(c => c.id === Number(filters.currency_id)) :
                                            (currencies.find(c => c.name === 'IDR') || currencies[0]);
                                        return selectedCurrency ? selectedCurrency.name : 'IDR';
                                    }
                                    return 'IDR';
                                })()} ▼
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-32">
                            {currencies && currencies.length > 0 ? (
                                currencies.map((currency) => {
                                    const isSelected = filters.currency_id ?
                                        currency.id === Number(filters.currency_id) :
                                        (currency.name === 'IDR' || currencies[0]?.id === currency.id);
                                    return (
                                        <DropdownMenuItem
                                            key={currency.id}
                                            className="cursor-pointer flex items-center justify-between"
                                            onClick={() => {
                                                const currentParams = new URLSearchParams(window.location.search);
                                                currentParams.set('currency_id', currency.id);
                                                router.get(window.location.pathname, Object.fromEntries(currentParams), {
                                                    preserveState: true,
                                                    replace: true,
                                                });
                                            }}
                                        >
                                            <span>{currency.name}</span>
                                            {isSelected && <Check size={16} className="text-blue-600" />}
                                        </DropdownMenuItem>
                                    );
                                })
                            ) : (
                                // Fallback to default currency if no user currencies exist
                                <DropdownMenuItem
                                    key="IDR"
                                    className="cursor-pointer flex items-center justify-between"
                                    onClick={() => {
                                        const currentParams = new URLSearchParams(window.location.search);
                                        currentParams.delete('currency_id');
                                        router.get(window.location.pathname, Object.fromEntries(currentParams), {
                                            preserveState: true,
                                            replace: true,
                                        });
                                    }}
                                >
                                    <span>IDR</span>
                                    {!filters.currency_id && <Check size={16} className="text-blue-600" />}
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>

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

            {/* Income Balance Card */}
            <IncomeBalanceCard stats={stats} currencyBreakdown={currencyBreakdown} filters={filters} availableCurrencies={availableCurrencies} />
        </div>
    );
}
