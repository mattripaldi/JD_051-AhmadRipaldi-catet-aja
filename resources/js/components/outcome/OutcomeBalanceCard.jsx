import { Button } from '@/components/ui/button';
import { formatCompactNumber } from '@/utils/formatters';
import { Eye, EyeOff, TrendingUp, TrendingDown } from 'lucide-react';
import { useState } from 'react';
import { Dot } from 'lucide-react';

export function OutcomeBalanceCard({ stats, currencyBreakdown, filters, availableCurrencies }) {
    const [showBalance, setShowBalance] = useState(true);

    return (
        <div className="rounded-3xl bg-white/15 p-3 backdrop-blur-sm sm:p-5">
            <div className="mb-3 flex items-center justify-between">
                <span className="text-sm font-medium text-white">Total Pengeluaran</span>
                <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => setShowBalance(!showBalance)}
                    className="h-7 w-7 rounded-full text-white hover:bg-white/10 hover:text-white"
                >
                    {showBalance ? <Eye size={14} /> : <EyeOff size={14} />}
                </Button>
            </div>

            {/* Currency Breakdown */}
            {showBalance ? (
                <div className="mb-4 space-y-3">
                    {availableCurrencies?.map((currency) => {
                        const currencyName = currency;
                        const outcomeData = currencyBreakdown[currencyName] || {};
                        const outcomeAmount = outcomeData.balance || 0;
                        const rateKey = currencyName.toLowerCase() + 'ToIdrRate';
                        const exchangeRate = stats[rateKey] || 1;
                        const outcomeInIdr = outcomeAmount * exchangeRate;

                        return (
                            <div key={currencyName} className="flex items-center justify-between">
                                <div className="flex items-center space-x-3">
                                    <span className="text-white/90">{currencyName}</span>
                                </div>
                                <div className="text-right">
                                    <span className="text-sm font-semibold text-white sm:text-lg">
                                        {formatCompactNumber(outcomeAmount, currencyName)}
                                    </span>
                                    {currencyName !== 'IDR' && (
                                        <p className="text-xs font-medium text-white/75">
                                            = {formatCompactNumber(outcomeInIdr)}
                                        </p>
                                    )}
                                </div>
                            </div>
                        );
                    })}

                    <div className="flex items-center justify-between border-t border-white/20 pt-3">
                        <span className="text-sm font-medium text-white/90">Total (IDR)</span>
                        <span className="text-base font-bold text-white sm:text-xl">
                            {formatCompactNumber(
                                availableCurrencies?.reduce((total, currency) => {
                                    const currencyName = currency;
                                    const outcomeData = currencyBreakdown[currencyName] || {};
                                    const outcomeAmount = outcomeData.balance || 0;
                                    const rateKey = currencyName.toLowerCase() + 'ToIdrRate';
                                    const exchangeRate = stats[rateKey] || 1;
                                    return total + (outcomeAmount * exchangeRate);
                                }, 0)
                            )}
                        </span>
                    </div>

                    {/* Comparison to previous period */}
                    {filters.mode !== 'all' && (
                        <div className="flex items-center justify-center space-x-1 pt-2">
                            {stats.outcomeChange >= 0 ? (
                                <TrendingUp className="text-red-200" size={14} />
                            ) : (
                                <TrendingDown className="text-green-200" size={14} />
                            )}
                            <span className="text-xs font-medium text-white/85 sm:text-sm">
                                {stats.outcomeChange >= 0 ? '+' : ''}
                                {stats.outcomeChange.toFixed(1)}% dari {stats.previousPeriod}
                            </span>
                        </div>
                    )}
                </div>
            ) : (
                <div className="mb-4">
                    <p className="flex items-center justify-center">
                        <Dot size={28} strokeWidth={4} />
                        <Dot size={28} strokeWidth={4} />
                        <Dot size={28} strokeWidth={4} />   
                        <Dot size={28} strokeWidth={4} />
                        <Dot size={28} strokeWidth={4} />
                        <Dot size={28} strokeWidth={4} />
                    </p>
                </div>
            )}
        </div>
    );
}
