import { TooltipProvider } from '@/components/ui/tooltip';
import { formatCompactNumber } from '@/utils/formatters';
import { PiggyBankIcon, TrendingDown, TrendingUp } from 'lucide-react';

const calculateSavingsRate = (income, outcome) => {
    // Handle NaN, undefined, or null values
    const safeIncome = (income === null || income === undefined || isNaN(income) || !isFinite(income)) ? 0 : income;
    const safeOutcome = (outcome === null || outcome === undefined || isNaN(outcome) || !isFinite(outcome)) ? 0 : outcome;

    if (safeIncome === 0) return 0;
    const rate = ((safeIncome - safeOutcome) / safeIncome) * 100;

    // Ensure the result is a valid number
    return isNaN(rate) || !isFinite(rate) ? 0 : rate;
};

const AnalyticsOverview = ({ stats, currencyBreakdown, availableCurrencies = [] }) => {
    // Calculate combined totals for analytics (all currencies converted to IDR)
    const combinedTotalIncome = Object.keys(currencyBreakdown || {}).reduce((total, currency) => {
        const income = currencyBreakdown[currency]?.income || 0;
        const safeIncome = (income === null || income === undefined || isNaN(income) || !isFinite(income)) ? 0 : income;

        if (currency === 'IDR') {
            return total + safeIncome;
        }
        const rateKey = currency.toLowerCase() + 'ToIdrRate';
        const exchangeRate = (stats && stats[rateKey]) ? stats[rateKey] : 1;
        const safeExchangeRate = (exchangeRate === null || exchangeRate === undefined || isNaN(exchangeRate) || !isFinite(exchangeRate)) ? 1 : exchangeRate;

        const convertedAmount = safeIncome * safeExchangeRate;
        return total + ((isNaN(convertedAmount) || !isFinite(convertedAmount)) ? 0 : convertedAmount);
    }, 0);

    const combinedTotalOutcome = Object.keys(currencyBreakdown || {}).reduce((total, currency) => {
        const outcome = Math.abs(currencyBreakdown[currency]?.outcome || 0);
        const safeOutcome = (outcome === null || outcome === undefined || isNaN(outcome) || !isFinite(outcome)) ? 0 : outcome;

        if (currency === 'IDR') {
            return total + safeOutcome;
        }
        const rateKey = currency.toLowerCase() + 'ToIdrRate';
        const exchangeRate = (stats && stats[rateKey]) ? stats[rateKey] : 1;
        const safeExchangeRate = (exchangeRate === null || exchangeRate === undefined || isNaN(exchangeRate) || !isFinite(exchangeRate)) ? 1 : exchangeRate;

        const convertedAmount = safeOutcome * safeExchangeRate;
        return total + ((isNaN(convertedAmount) || !isFinite(convertedAmount)) ? 0 : convertedAmount);
    }, 0);

    // Calculate combined analytics metrics
    const combinedSavingsRate = calculateSavingsRate(combinedTotalIncome, combinedTotalOutcome);
    const combinedSpendingPercentage = combinedTotalIncome > 0 ? (combinedTotalOutcome / combinedTotalIncome) * 100 : 0;

    // Ensure spending percentage is a valid number
    const safeCombinedSpendingPercentage = isNaN(combinedSpendingPercentage) || !isFinite(combinedSpendingPercentage) ? 0 : combinedSpendingPercentage;

    return (
        <TooltipProvider>
            <div className="overflow-hidden bg-white">
                <div className="px-3 py-3 pb-2 sm:px-4 sm:py-4 sm:pb-3">
                    <div className="mb-1 flex items-center justify-between">
                        <h3 className="text-sm font-bold text-gray-800 sm:text-base">Analisis Keuangan</h3>
                    </div>
                    <p className="mb-3 text-xs text-gray-500 sm:mb-4">Metrik keuangan detail untuk {stats.currentPeriod}</p>
                </div>

                <div className="space-y-0.5">
                    {/* Target Tabungan Minimum 30% */}
                    <div className="px-3 py-2.5 transition-colors hover:bg-gray-50 sm:px-4 sm:py-3">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center space-x-2.5">
                                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100">
                                    <PiggyBankIcon size={16} className="text-emerald-600" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="text-xs font-medium text-gray-900 sm:text-sm">Target Tabungan Minimum</p>
                                    <p className="text-xs text-gray-500">Target minimal 30% dari pendapatan</p>
                                </div>
                            </div>
                            <div className="ml-2 text-right">
                                <p className={`text-sm font-semibold ${combinedSavingsRate >= 30 ? 'text-emerald-600' : 'text-red-600'}`}>
                                    {combinedSavingsRate.toFixed(0)}%
                                </p>
                                <p className={`text-xs ${combinedSavingsRate >= 30 ? 'text-emerald-600' : 'text-red-600'}`}>
                                    <span
                                        className={`rounded px-1.5 py-0.5 text-xs font-medium ${
                                            combinedSavingsRate >= 30 ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-600'
                                        }`}
                                    >
                                        {combinedSavingsRate >= 30 ? '✓ Target tercapai' : '⚠ Kurang dari target'}
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Max Pengeluaran 70% */}
                    <div className="px-3 py-2.5 transition-colors hover:bg-gray-50 sm:px-4 sm:py-3">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center space-x-2.5">
                                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-orange-100">
                                    <TrendingDown size={16} className="text-orange-600" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="text-xs font-medium text-gray-900 sm:text-sm">Batas Maksimal Pengeluaran</p>
                                    <p className="text-xs text-gray-500">Maksimal 70% dari pendapatan</p>
                                </div>
                            </div>
                            <div className="ml-2 text-right">
                                <p className={`text-sm font-semibold ${safeCombinedSpendingPercentage <= 70 ? 'text-emerald-600' : 'text-red-600'}`}>
                                    {safeCombinedSpendingPercentage.toFixed(0)}%
                                </p>
                                <p className={`text-xs ${safeCombinedSpendingPercentage <= 70 ? 'text-emerald-600' : 'text-red-600'}`}>
                                    <span
                                        className={`rounded px-1.5 py-0.5 text-xs font-medium ${
                                            safeCombinedSpendingPercentage <= 70 ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-600'
                                        }`}
                                    >
                                        {safeCombinedSpendingPercentage <= 70 ? '✓ Dalam batas' : '⚠ Melebihi batas'}
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Rata-rata Pemasukan Harian */}
                    <div className="px-3 py-2.5 transition-colors hover:bg-gray-50 sm:px-4 sm:py-3">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center space-x-2.5">
                                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100">
                                    <TrendingUp size={16} className="text-emerald-600" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="text-xs font-medium text-gray-900 sm:text-sm">Rata-rata Pemasukan Harian</p>
                                    <p className="text-xs text-gray-500">Pemasukan per hari dalam periode ini</p>
                                </div>
                            </div>
                            <div className="ml-2 text-right">
                                <p className="text-sm font-semibold text-emerald-600">{formatCompactNumber(stats.dailyIncomeAverage)}</p>
                                <p className="text-xs text-emerald-600">
                                    <span className="rounded bg-emerald-100 px-1.5 py-0.5 text-xs font-medium text-emerald-600">per hari</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Rata-rata Pengeluaran Harian */}
                    <div className="px-3 py-2.5 transition-colors hover:bg-gray-50 sm:px-4 sm:py-3">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center space-x-2.5">
                                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-red-100">
                                    <TrendingDown size={16} className="text-red-600" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="text-xs font-medium text-gray-900 sm:text-sm">Rata-rata Pengeluaran Harian</p>
                                    <p className="text-xs text-gray-500">Pengeluaran per hari dalam periode ini</p>
                                </div>
                            </div>
                            <div className="ml-2 text-right">
                                <p className="text-sm font-semibold text-red-600">{formatCompactNumber(stats.dailyOutcomeAverage)}</p>
                                <p className="text-xs text-red-600">
                                    <span className="rounded bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-600">per hari</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </TooltipProvider>
    );
};

export default AnalyticsOverview;
