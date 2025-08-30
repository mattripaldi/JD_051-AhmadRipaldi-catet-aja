<?php

namespace App\Concerns;

use App\Models\Income;
use App\Models\Outcome;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

trait DashboardCalculationsTrait
{
    /**
     * Calculate total amount with currency filtering and conversion
     *
     * @param int $year
     * @param int $month
     * @param string $type
     * @param int|null $currencyId
     * @param string $mode
     * @param int|null $accountId
     * @return float
     */
    protected function calculateTotalWithCurrencyConversion($year, $month, $type, $currencyId = null, $mode = 'month', $accountId = null)
    {
        $model = $type === 'income' ? Income::class : Outcome::class;
        $query = $model::query()
            ->selectRaw('SUM(amount) as total')
            ->where('user_id', Auth::id());

        // Apply account filter if provided
        if ($accountId) {
            // Handle both model instances and IDs
            $accountIdValue = is_object($accountId) ? $accountId->id : $accountId;
            $query->where('account_id', $accountIdValue);
        }

        // Apply date filters based on mode
        if ($mode === 'month') {
            $query->whereYear('transaction_date', $year)
                  ->whereMonth('transaction_date', $month);
        } elseif ($mode === 'year') {
            $query->whereYear('transaction_date', $year);
        }
        // For 'all' mode, no date filter is applied

        // Apply currency filtering to match income/outcome pages behavior
        if ($currencyId) {
            $query->where('currency_id', $currencyId);
        } else {
            // If no currency_id specified, include all transactions (for backward compatibility)
            // You might want to add logic here to filter by default currency if needed
        }

        $result = $query->first();
        return (float) ($result->total ?? 0);
    }
    
    /**
     * Calculate monthly statistics with optimized queries
     *
     * @param int $year
     * @param int $month
     * @param int|null $currencyId
     * @param int|null $accountId
     * @return array
     */
    protected function calculateMonthlyStats($year, $month, $currencyId, $accountId = null)
    {
        // Calculate current and previous month data in a single optimized query
        $previousMonth = $month == 1 ? 12 : $month - 1;
        $previousYear = $month == 1 ? $year - 1 : $year;

        $currentData = $this->getOptimizedPeriodStats($year, $month, $currencyId, 'month', $accountId);
        $previousData = $this->getOptimizedPeriodStats($previousYear, $previousMonth, $currencyId, 'month', $accountId);

        $totalRevenue = $currentData['income'];
        $totalOutcome = abs($currentData['outcome']);
        $balance = $totalRevenue - $totalOutcome;

        $previousMonthRevenue = $previousData['income'];
        $previousMonthOutcome = abs($previousData['outcome']);
        $previousBalance = $previousMonthRevenue - $previousMonthOutcome;

        // Calculate percentage changes
        $revenueChange = $this->calculatePercentageChange($totalRevenue, $previousMonthRevenue);
        $outcomeChange = $this->calculatePercentageChange($totalOutcome, $previousMonthOutcome);
        $balanceChange = $this->calculateBalanceChange($balance, $previousBalance);

        // Get the month names for display in Indonesian
        $currentMonthName = Carbon::create($year, $month, 1)->locale('id')->isoFormat('MMMM');
        $previousMonthName = Carbon::create($previousYear, $previousMonth, 1)->locale('id')->isoFormat('MMMM');

        $currentPeriod = "$currentMonthName $year";
        $previousPeriod = "$previousMonthName $previousYear";

        return [
            'totalRevenue' => $totalRevenue,
            'totalOutcome' => $totalOutcome,
            'balance' => $balance,
            'revenueChange' => round($revenueChange, 1),
            'outcomeChange' => round($outcomeChange, 1),
            'balanceChange' => round($balanceChange, 1),
            'currentPeriod' => $currentPeriod,
            'previousPeriod' => $previousPeriod,
            'previousPeriodRevenue' => $previousMonthRevenue,
            'previousPeriodOutcome' => $previousMonthOutcome,
            'previousPeriodBalance' => $previousBalance,
        ];
    }
    
    /**
     * Calculate yearly statistics with optimized queries
     *
     * @param int $year
     * @param int|null $currencyId
     * @param int|null $accountId
     * @return array
     */
    protected function calculateYearlyStats($year, $currencyId, $accountId = null)
    {
        // Calculate current and previous year data in optimized queries
        $previousYear = $year - 1;

        $currentData = $this->getOptimizedPeriodStats($year, 0, $currencyId, 'year', $accountId);
        $previousData = $this->getOptimizedPeriodStats($previousYear, 0, $currencyId, 'year', $accountId);

        $totalRevenue = $currentData['income'];
        $totalOutcome = abs($currentData['outcome']);
        $balance = $totalRevenue - $totalOutcome;

        $previousYearRevenue = $previousData['income'];
        $previousYearOutcome = abs($previousData['outcome']);
        $previousBalance = $previousYearRevenue - $previousYearOutcome;

        // Calculate percentage changes
        $revenueChange = $this->calculatePercentageChange($totalRevenue, $previousYearRevenue);
        $outcomeChange = $this->calculatePercentageChange($totalOutcome, $previousYearOutcome);
        $balanceChange = $this->calculateBalanceChange($balance, $previousBalance);

        $currentPeriod = "$year";
        $previousPeriod = "$previousYear";

        return [
            'totalRevenue' => $totalRevenue,
            'totalOutcome' => $totalOutcome,
            'balance' => $balance,
            'revenueChange' => round($revenueChange, 1),
            'outcomeChange' => round($outcomeChange, 1),
            'balanceChange' => round($balanceChange, 1),
            'currentPeriod' => $currentPeriod,
            'previousPeriod' => $previousPeriod,
            'previousPeriodRevenue' => $previousYearRevenue,
            'previousPeriodOutcome' => $previousYearOutcome,
            'previousPeriodBalance' => $previousBalance,
        ];
    }
    
    /**
     * Calculate all time statistics with optimized queries
     *
     * @param int|null $currencyId
     * @param int|null $accountId
     * @return array
     */
    protected function calculateAllTimeStats($currencyId, $accountId = null)
    {
        $data = $this->getOptimizedPeriodStats(0, 0, $currencyId, 'all', $accountId);

        $totalRevenue = $data['income'];
        $totalOutcome = abs($data['outcome']);
        $balance = $totalRevenue - $totalOutcome;

        // For all time, we don't have meaningful comparison period
        $revenueChange = 0;
        $outcomeChange = 0;
        $balanceChange = 0;

        $currentPeriod = "All Time";
        $previousPeriod = "";

        return [
            'totalRevenue' => $totalRevenue,
            'totalOutcome' => $totalOutcome,
            'balance' => $balance,
            'revenueChange' => round($revenueChange, 1),
            'outcomeChange' => round($outcomeChange, 1),
            'balanceChange' => round($balanceChange, 1),
            'currentPeriod' => $currentPeriod,
            'previousPeriod' => $previousPeriod,
            'previousPeriodRevenue' => 0,
            'previousPeriodOutcome' => 0,
            'previousPeriodBalance' => 0,
        ];
    }
    
    /**
     * Get optimized period statistics with a single query
     *
     * @param int|null $year
     * @param int|null $month
     * @param int|null $currencyId
     * @param string $mode
     * @param int|null $accountId
     * @return array
     */
    protected function getOptimizedPeriodStats($year, $month, $currencyId, $mode, $accountId = null)
    {
        // Query income and outcome separately
        $incomeQuery = Income::query()
            ->selectRaw('SUM(amount) as total')
            ->where('user_id', Auth::id());

        $outcomeQuery = Outcome::query()
            ->selectRaw('SUM(amount) as total')
            ->where('user_id', Auth::id());

        // Apply account filter if provided
        if ($accountId) {
            // Handle both model instances and IDs
            $accountIdValue = is_object($accountId) ? $accountId->id : $accountId;
            $incomeQuery->where('account_id', $accountIdValue);
            $outcomeQuery->where('account_id', $accountIdValue);
        }

        // Apply date filters based on mode
        if ($mode === 'month' && $year && $month) {
            $incomeQuery->whereYear('transaction_date', $year)
                  ->whereMonth('transaction_date', $month);
            $outcomeQuery->whereYear('transaction_date', $year)
                  ->whereMonth('transaction_date', $month);
        } elseif ($mode === 'year' && $year) {
            $incomeQuery->whereYear('transaction_date', $year);
            $outcomeQuery->whereYear('transaction_date', $year);
        }
        // For 'all' mode, no date filter is applied

        // Apply currency filtering
        if ($currencyId) {
            $incomeQuery->where('currency_id', $currencyId);
            $outcomeQuery->where('currency_id', $currencyId);
        } else {
            // If no currency_id specified, include all transactions (for backward compatibility)
            // You might want to add logic here to filter by default currency if needed
        }

        $incomeResult = $incomeQuery->first();
        $outcomeResult = $outcomeQuery->first();

        return [
            'income' => (float) ($incomeResult->total ?? 0),
            'outcome' => (float) ($outcomeResult->total ?? 0),
        ];
    }
    
    /**
     * Calculate percentage change between current and previous values
     *
     * @param float $current
     * @param float $previous
     * @return float
     */
    protected function calculatePercentageChange($current, $previous)
    {
        // Ensure we have valid numbers
        $current = (float) $current;
        $previous = (float) $previous;

        // Handle edge cases to prevent NaN
        if ($previous > 0) {
            $change = (($current - $previous) / $previous) * 100;
        } elseif ($current > 0 && $previous == 0) {
            // If previous was 0 but current is not, that's a 100% increase
            $change = 100;
        } elseif ($current == 0 && $previous > 0) {
            // If previous was something but current is 0, that's a 100% decrease
            $change = -100;
        } elseif ($current == 0 && $previous == 0) {
            // Both are 0, no change
            $change = 0;
        } else {
            // Fallback for any other edge cases
            $change = 0;
        }

        // Ensure the result is a valid number
        return is_nan($change) || !is_finite($change) ? 0 : $change;
    }
    
    /**
     * Calculate balance change with proper handling of negative values
     *
     * @param float $balance
     * @param float $previousBalance
     * @return float
     */
    protected function calculateBalanceChange($balance, $previousBalance)
    {
        // Ensure we have valid numbers
        $balance = (float) $balance;
        $previousBalance = (float) $previousBalance;

        $balanceChange = 0;
        if ($previousBalance != 0) {
            $balanceChange = (($balance - $previousBalance) / abs($previousBalance)) * 100;
        } elseif ($balance != 0 && $previousBalance == 0) {
            // If previous was 0 but current is not, that's a 100% increase or decrease
            $balanceChange = $balance > 0 ? 100 : -100;
        } elseif ($balance == 0 && $previousBalance == 0) {
            // Both are 0, no change
            $balanceChange = 0;
        }

        // Ensure the result is a valid number
        return is_nan($balanceChange) || !is_finite($balanceChange) ? 0 : $balanceChange;
    }
    
    /**
     * Get monthly chart data for dashboard with optimized query
     *
     * @param int $year
     * @param int|null $currencyId
     * @param int|null $accountId
     * @return array
     */
    protected function getMonthlyData($year, $currencyId = null, $accountId = null)
    {
        $incomeQuery = Income::query()
            ->selectRaw('MONTH(transaction_date) as month, SUM(amount) as total')
            ->where('user_id', Auth::id())
            ->whereYear('transaction_date', $year)
            ->groupBy('month')
            ->orderBy('month');

        $outcomeQuery = Outcome::query()
            ->selectRaw('MONTH(transaction_date) as month, SUM(amount) as total')
            ->where('user_id', Auth::id())
            ->whereYear('transaction_date', $year)
            ->groupBy('month')
            ->orderBy('month');

        // Apply account filter if provided
        if ($accountId) {
            // Handle both model instances and IDs
            $accountIdValue = is_object($accountId) ? $accountId->id : $accountId;
            $incomeQuery->where('account_id', $accountIdValue);
            $outcomeQuery->where('account_id', $accountIdValue);
        }

        // Apply currency filtering
        if ($currencyId) {
            $incomeQuery->where('currency_id', $currencyId);
            $outcomeQuery->where('currency_id', $currencyId);
        } else {
            // If no currency_id specified, include all transactions (for backward compatibility)
            // You might want to add logic here to filter by default currency if needed
        }

        $incomeResults = $incomeQuery->get()->keyBy('month');
        $outcomeResults = $outcomeQuery->get()->keyBy('month');

        $monthlyData = [];
        for ($month = 1; $month <= 12; $month++) {
            $income = (float) ($incomeResults->get($month)->total ?? 0);
            $outcome = abs((float) ($outcomeResults->get($month)->total ?? 0));

            $monthName = Carbon::create($year, $month, 1)->locale('id')->isoFormat('MMM');

            $monthlyData[] = [
                'name' => $monthName,
                'income' => $income,
                'outcome' => $outcome,
            ];
        }

        return $monthlyData;
    }
    
    /**
     * Get yearly chart data for dashboard with optimized query
     *
     * @param int|null $currencyId
     * @param int|null $accountId
     * @return array
     */
    protected function getYearlyData($currencyId = null, $accountId = null)
    {
        $currentYear = Carbon::now()->year;
        $startYear = $currentYear - 4;

        $incomeQuery = Income::query()
            ->selectRaw('YEAR(transaction_date) as year, SUM(amount) as total')
            ->where('user_id', Auth::id())
            ->whereBetween('transaction_date', [
                Carbon::create($startYear, 1, 1),
                Carbon::create($currentYear, 12, 31)
            ])
            ->groupBy('year')
            ->orderBy('year');

        $outcomeQuery = Outcome::query()
            ->selectRaw('YEAR(transaction_date) as year, SUM(amount) as total')
            ->where('user_id', Auth::id())
            ->whereBetween('transaction_date', [
                Carbon::create($startYear, 1, 1),
                Carbon::create($currentYear, 12, 31)
            ])
            ->groupBy('year')
            ->orderBy('year');

        // Apply account filter if provided
        if ($accountId) {
            // Handle both model instances and IDs
            $accountIdValue = is_object($accountId) ? $accountId->id : $accountId;
            $incomeQuery->where('account_id', $accountIdValue);
            $outcomeQuery->where('account_id', $accountIdValue);
        }

        // Apply currency filtering
        if ($currencyId) {
            $incomeQuery->where('currency_id', $currencyId);
            $outcomeQuery->where('currency_id', $currencyId);
        } else {
            // If no currency_id specified, include all transactions (for backward compatibility)
            // You might want to add logic here to filter by default currency if needed
        }

        $incomeResults = $incomeQuery->get()->keyBy('year');
        $outcomeResults = $outcomeQuery->get()->keyBy('year');

        $yearlyData = [];
        for ($i = 0; $i < 5; $i++) {
            $year = $currentYear - $i;

            $income = (float) ($incomeResults->get($year)->total ?? 0);
            $outcome = abs((float) ($outcomeResults->get($year)->total ?? 0));

            $yearlyData[] = [
                'name' => (string) $year,
                'income' => $income,
                'outcome' => $outcome,
            ];
        }

        // Sort by year ascending
        usort($yearlyData, function($a, $b) {
            return $a['name'] <=> $b['name'];
        });

        return $yearlyData;
    }

    /**
     * Calculate currency breakdown data based on user's actual currencies with optimized queries
     *
     * @param int $year
     * @param int $month
     * @param string $mode
     * @param int|null $accountId
     * @return array
     */
    protected function getCurrencyBreakdown($year, $month, $mode, $accountId = null)
    {
        $userCurrencies = $this->getUserCurrencies($accountId);
        $breakdown = [];

        foreach ($userCurrencies as $currencyName) {
            // Get currency ID from name
            $currencyId = $this->getCurrencyIdFromName($currencyName, $accountId);

            if ($currencyId) {
                $currencyData = $this->getOptimizedPeriodStats($year, $month, $currencyId, $mode, $accountId);
                $breakdown[$currencyName] = [
                    'income' => $currencyData['income'],
                    'outcome' => abs($currencyData['outcome']),
                    'balance' => $currencyData['income'] - abs($currencyData['outcome']),
                ];
            }
        }

        // Fallback to IDR if no currencies found
        if (empty($breakdown)) {
            $idrId = $this->getCurrencyIdFromName('IDR', $accountId);
            if ($idrId) {
                $idrData = $this->getOptimizedPeriodStats($year, $month, $idrId, $mode, $accountId);
                $breakdown['IDR'] = [
                    'income' => $idrData['income'],
                    'outcome' => abs($idrData['outcome']),
                    'balance' => $idrData['income'] - abs($idrData['outcome']),
                ];
            }
        }

        return $breakdown;
    }

    /**
     * Get currency ID from currency name for the user
     *
     * @param string $currencyName
     * @param int|null $accountId
     * @return int|null
     */
    protected function getCurrencyIdFromName($currencyName, $accountId = null)
    {
        $query = \App\Models\Currency::where('user_id', Auth::id())
            ->where('name', $currencyName);

        if ($accountId) {
            // Handle both model instances and IDs
            $accountIdValue = is_object($accountId) ? $accountId->id : $accountId;
            $query->where(function ($q) use ($accountIdValue) {
                $q->where('account_id', $accountIdValue)
                  ->orWhereNull('account_id');
            });
        } else {
            $query->whereNull('account_id');
        }

        return $query->value('id');
    }

    /**
     * Get distinct currencies used by the user in their transactions
     *
     * @param int|null $accountId
     * @return array
     */
    protected function getUserCurrencies($accountId = null)
    {
        $incomeQuery = Income::query()
            ->select('currency_id')
            ->with('currency')
            ->where('user_id', Auth::id())
            ->whereNotNull('currency_id')
            ->distinct();

        $outcomeQuery = Outcome::query()
            ->select('currency_id')
            ->with('currency')
            ->where('user_id', Auth::id())
            ->whereNotNull('currency_id')
            ->distinct();

        // Apply account filter if provided
        if ($accountId) {
            // Handle both model instances and IDs
            $accountIdValue = is_object($accountId) ? $accountId->id : $accountId;
            $incomeQuery->where('account_id', $accountIdValue);
            $outcomeQuery->where('account_id', $accountIdValue);
        }

        $incomeCurrencies = $incomeQuery->get()->pluck('currency.name')->filter()->toArray();
        $outcomeCurrencies = $outcomeQuery->get()->pluck('currency.name')->filter()->toArray();

        // Merge and get unique currencies
        $allCurrencies = array_unique(array_merge($incomeCurrencies, $outcomeCurrencies));

        // Sort currencies, with IDR first if it exists
        sort($allCurrencies);
        if (in_array('IDR', $allCurrencies)) {
            $allCurrencies = array_merge(
                ['IDR'],
                array_filter($allCurrencies, fn($c) => $c !== 'IDR')
            );
        }

        return $allCurrencies;
    }

    /**
     * Calculate daily averages for income and outcome based on mode and time period
     *
     * @param int $year
     * @param int $month
     * @param string $mode
     * @param int|null $currencyId
     * @param int|null $accountId
     * @return array
     */
    protected function calculateDailyAverages($year, $month, $mode, $currencyId, $accountId = null)
    {
        $data = $this->getOptimizedPeriodStats($year, $month, $currencyId, $mode, $accountId);
        $income = (float) $data['income'];
        $outcome = abs((float) $data['outcome']);

        $dailyIncomeAverage = 0;
        $dailyOutcomeAverage = 0;

        if ($mode === 'month') {
            // Calculate days in the specified month
            $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;

            // For current month, only count elapsed days
            $currentDate = Carbon::now();
            $isCurrentMonth = $currentDate->year === $year && $currentDate->month === $month;
            $daysToUse = $isCurrentMonth ? min($currentDate->day, $daysInMonth) : $daysInMonth;

            // Ensure daysToUse is a valid positive number
            $daysToUse = (int) $daysToUse;
            if ($daysToUse > 0 && is_finite($daysToUse)) {
                $dailyIncomeAverage = $income / $daysToUse;
                $dailyOutcomeAverage = $outcome / $daysToUse;
            }
        } elseif ($mode === 'year') {
            // Calculate days in the specified year
            $daysInYear = Carbon::create($year, 1, 1)->isLeapYear() ? 366 : 365;

            // For current year, only count elapsed days
            $currentDate = Carbon::now();
            $isCurrentYear = $currentDate->year === $year;

            if ($isCurrentYear) {
                $startOfYear = Carbon::create($year, 1, 1);
                $daysElapsed = $startOfYear->diffInDays($currentDate) + 1;
                $daysToUse = min($daysElapsed, $daysInYear);
            } else {
                $daysToUse = $daysInYear;
            }

            // Ensure daysToUse is a valid positive number
            $daysToUse = (int) $daysToUse;
            if ($daysToUse > 0 && is_finite($daysToUse)) {
                $dailyIncomeAverage = $income / $daysToUse;
                $dailyOutcomeAverage = $outcome / $daysToUse;
            }
        } elseif ($mode === 'all') {
            // For all time, calculate based on actual transaction date range
            $firstIncomeQuery = Income::query()
                ->where('user_id', Auth::id())
                ->orderBy('transaction_date', 'asc');

            $firstOutcomeQuery = Outcome::query()
                ->where('user_id', Auth::id())
                ->orderBy('transaction_date', 'asc');

            $lastIncomeQuery = Income::query()
                ->where('user_id', Auth::id())
                ->orderBy('transaction_date', 'desc');

            $lastOutcomeQuery = Outcome::query()
                ->where('user_id', Auth::id())
                ->orderBy('transaction_date', 'desc');

            // Apply account filter if provided
            if ($accountId) {
                // Handle both model instances and IDs
                $accountIdValue = is_object($accountId) ? $accountId->id : $accountId;
                $firstIncomeQuery->where('account_id', $accountIdValue);
                $firstOutcomeQuery->where('account_id', $accountIdValue);
                $lastIncomeQuery->where('account_id', $accountIdValue);
                $lastOutcomeQuery->where('account_id', $accountIdValue);
            }

            $firstIncome = $firstIncomeQuery->first();
            $firstOutcome = $firstOutcomeQuery->first();
            $lastIncome = $lastIncomeQuery->first();
            $lastOutcome = $lastOutcomeQuery->first();

            // Find the earliest and latest dates across both models
            $firstDates = array_filter([$firstIncome?->transaction_date, $firstOutcome?->transaction_date]);
            $lastDates = array_filter([$lastIncome?->transaction_date, $lastOutcome?->transaction_date]);

            $firstTransaction = !empty($firstDates) ? min($firstDates) : null;
            $lastTransaction = !empty($lastDates) ? max($lastDates) : null;

            if ($firstTransaction && $lastTransaction) {
                try {
                    $firstDate = Carbon::parse($firstTransaction);
                    $lastDate = Carbon::parse($lastTransaction);
                    $daysDiff = $firstDate->diffInDays($lastDate) + 1; // +1 to include both first and last day

                    // Ensure daysDiff is a valid positive number
                    $daysDiff = (int) $daysDiff;
                    if ($daysDiff > 0 && is_finite($daysDiff)) {
                        $dailyIncomeAverage = $income / $daysDiff;
                        $dailyOutcomeAverage = $outcome / $daysDiff;
                    }
                } catch (\Exception $e) {
                    // If date parsing fails, keep defaults as 0
                    $dailyIncomeAverage = 0;
                    $dailyOutcomeAverage = 0;
                }
            }
        }

        // Ensure the results are valid numbers
        $dailyIncomeAverage = is_nan($dailyIncomeAverage) || !is_finite($dailyIncomeAverage) ? 0 : $dailyIncomeAverage;
        $dailyOutcomeAverage = is_nan($dailyOutcomeAverage) || !is_finite($dailyOutcomeAverage) ? 0 : $dailyOutcomeAverage;

        return [
            'dailyIncomeAverage' => round($dailyIncomeAverage, 2),
            'dailyOutcomeAverage' => round($dailyOutcomeAverage, 2),
        ];
    }
}
 