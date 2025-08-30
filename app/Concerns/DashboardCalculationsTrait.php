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
     * @param string $currency
     * @param string $mode
     * @return float
     */
    protected function calculateTotalWithCurrencyConversion($year, $month, $type, $currency = 'IDR', $mode = 'month')
    {
        $model = $type === 'income' ? Income::class : Outcome::class;
        $query = $model::query()
            ->selectRaw('SUM(amount) as total')
            ->where('user_id', Auth::id());
            
        // Apply date filters based on mode
        if ($mode === 'month') {
            $query->whereYear('transaction_date', $year)
                  ->whereMonth('transaction_date', $month);
        } elseif ($mode === 'year') {
            $query->whereYear('transaction_date', $year);
        }
        // For 'all' mode, no date filter is applied
        
        // Apply currency filtering to match income/outcome pages behavior
        if ($currency && $currency !== 'IDR') {
            $query->where('currency', $currency);
        } else {
            // For 'IDR', include both null and 'Rp' currency transactions (legacy support)
            $query->where(function($q) {
                $q->where('currency', 'Rp')
                  ->orWhere('currency', 'IDR')
                  ->orWhereNull('currency');
            });
        }
        
        $result = $query->first();
        return (float) ($result->total ?? 0);
    }
    
    /**
     * Calculate monthly statistics with optimized queries
     * 
     * @param int $year
     * @param int $month
     * @param string $currency
     * @return array
     */
    protected function calculateMonthlyStats($year, $month, $currency)
    {
        // Calculate current and previous month data in a single optimized query
        $previousMonth = $month == 1 ? 12 : $month - 1;
        $previousYear = $month == 1 ? $year - 1 : $year;
        
        $currentData = $this->getOptimizedPeriodStats($year, $month, $currency, 'month');
        $previousData = $this->getOptimizedPeriodStats($previousYear, $previousMonth, $currency, 'month');
        
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
        
        // Get the month names for display
        $currentMonthName = date('F', mktime(0, 0, 0, $month, 1));
        $previousMonthName = date('F', mktime(0, 0, 0, $previousMonth, 1));
        
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
     * @param string $currency
     * @return array
     */
    protected function calculateYearlyStats($year, $currency)
    {
        // Calculate current and previous year data in optimized queries
        $previousYear = $year - 1;
        
        $currentData = $this->getOptimizedPeriodStats($year, 0, $currency, 'year');
        $previousData = $this->getOptimizedPeriodStats($previousYear, 0, $currency, 'year');
        
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
     * @param string $currency
     * @return array
     */
    protected function calculateAllTimeStats($currency)
    {
        $data = $this->getOptimizedPeriodStats(0, 0, $currency, 'all');
        
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
     * @param string $currency
     * @param string $mode
     * @return array
     */
    protected function getOptimizedPeriodStats($year, $month, $currency, $mode)
    {
        // Query income and outcome separately
        $incomeQuery = Income::query()
            ->selectRaw('SUM(amount) as total')
            ->where('user_id', Auth::id());

        $outcomeQuery = Outcome::query()
            ->selectRaw('SUM(amount) as total')
            ->where('user_id', Auth::id());
            
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
        if ($currency && $currency !== 'IDR') {
            $incomeQuery->where('currency', $currency);
            $outcomeQuery->where('currency', $currency);
        } else {
            $incomeQuery->where(function($q) {
                $q->where('currency', 'Rp')
                  ->orWhere('currency', 'IDR')
                  ->orWhereNull('currency');
            });
            $outcomeQuery->where(function($q) {
                $q->where('currency', 'Rp')
                  ->orWhere('currency', 'IDR')
                  ->orWhereNull('currency');
            });
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
        $change = 0;
        if ($previous > 0) {
            $change = (($current - $previous) / $previous) * 100;
        } elseif ($current > 0 && $previous == 0) {
            // If previous was 0 but current is not, that's a 100% increase
            $change = 100;
        } elseif ($current == 0 && $previous > 0) {
            // If previous was something but current is 0, that's a 100% decrease
            $change = -100;
        }
        
        return $change;
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
        $balanceChange = 0;
        if ($previousBalance != 0) {
            $balanceChange = (($balance - $previousBalance) / abs($previousBalance)) * 100;
        } elseif ($balance != 0 && $previousBalance == 0) {
            // If previous was 0 but current is not, that's a 100% increase or decrease
            $balanceChange = $balance > 0 ? 100 : -100;
        }
        
        return $balanceChange;
    }
    
    /**
     * Get monthly chart data for dashboard with optimized query
     * 
     * @param int $year
     * @param string $currency
     * @return array
     */
    protected function getMonthlyData($year, $currency = 'IDR')
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
            
        // Apply currency filtering
        if ($currency && $currency !== 'IDR') {
            $incomeQuery->where('currency', $currency);
            $outcomeQuery->where('currency', $currency);
        } else {
            $incomeQuery->where(function($q) {
                $q->where('currency', 'Rp')
                  ->orWhere('currency', 'IDR')
                  ->orWhereNull('currency');
            });
            $outcomeQuery->where(function($q) {
                $q->where('currency', 'Rp')
                  ->orWhere('currency', 'IDR')
                  ->orWhereNull('currency');
            });
        }

        $incomeResults = $incomeQuery->get()->keyBy('month');
        $outcomeResults = $outcomeQuery->get()->keyBy('month');
        
        $monthlyData = [];
        for ($month = 1; $month <= 12; $month++) {
            $income = (float) ($incomeResults->get($month)->total ?? 0);
            $outcome = abs((float) ($outcomeResults->get($month)->total ?? 0));
            
            $monthName = date('M', mktime(0, 0, 0, $month, 1));
            
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
     * @param string $currency
     * @return array
     */
    protected function getYearlyData($currency = 'IDR')
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
            
        // Apply currency filtering
        if ($currency && $currency !== 'IDR') {
            $incomeQuery->where('currency', $currency);
            $outcomeQuery->where('currency', $currency);
        } else {
            $incomeQuery->where(function($q) {
                $q->where('currency', 'Rp')
                  ->orWhere('currency', 'IDR')
                  ->orWhereNull('currency');
            });
            $outcomeQuery->where(function($q) {
                $q->where('currency', 'Rp')
                  ->orWhere('currency', 'IDR')
                  ->orWhereNull('currency');
            });
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
     * @return array
     */
    protected function getCurrencyBreakdown($year, $month, $mode)
    {
        $userCurrencies = $this->getUserCurrencies();
        $breakdown = [];
        
        foreach ($userCurrencies as $currency) {
            $currencyData = $this->getOptimizedPeriodStats($year, $month, $currency, $mode);
            $breakdown[$currency] = [
                'income' => $currencyData['income'],
                'outcome' => abs($currencyData['outcome']),
                'balance' => $currencyData['income'] - abs($currencyData['outcome']),
            ];
        }
        
        // Fallback to IDR if no currencies found
        if (empty($breakdown)) {
            $idrData = $this->getOptimizedPeriodStats($year, $month, 'IDR', $mode);
            $breakdown['IDR'] = [
                'income' => $idrData['income'],
                'outcome' => abs($idrData['outcome']),
                'balance' => $idrData['income'] - abs($idrData['outcome']),
            ];
        }
        
        return $breakdown;
    }

    /**
     * Get distinct currencies used by the user in their transactions
     * 
     * @return array
     */
    protected function getUserCurrencies()
    {
        $incomeCurrencies = Income::query()
            ->select('currency')
            ->where('user_id', Auth::id())
            ->whereNotNull('currency')
            ->distinct()
            ->pluck('currency')
            ->filter()
            ->toArray();

        $outcomeCurrencies = Outcome::query()
            ->select('currency')
            ->where('user_id', Auth::id())
            ->whereNotNull('currency')
            ->distinct()
            ->pluck('currency')
            ->filter()
            ->toArray();

        // Merge and get unique currencies
        $allCurrencies = array_unique(array_merge($incomeCurrencies, $outcomeCurrencies));
        
        // Normalize legacy currency values and remove duplicates
        $normalizedCurrencies = [];
        foreach ($allCurrencies as $currency) {
            // Convert legacy 'Rp' to 'IDR'
            $normalized = ($currency === 'Rp') ? 'IDR' : $currency;
            if (!in_array($normalized, $normalizedCurrencies)) {
                $normalizedCurrencies[] = $normalized;
            }
        }
        
        // Sort currencies, with IDR first if it exists
        sort($normalizedCurrencies);
        if (in_array('IDR', $normalizedCurrencies)) {
            $normalizedCurrencies = array_merge(
                ['IDR'], 
                array_filter($normalizedCurrencies, fn($c) => $c !== 'IDR')
            );
        }
        
        return $normalizedCurrencies;
    }

    /**
     * Calculate daily averages for income and outcome based on mode and time period
     * 
     * @param int $year
     * @param int $month
     * @param string $mode
     * @param string $currency
     * @return array
     */
    protected function calculateDailyAverages($year, $month, $mode, $currency)
    {
        $data = $this->getOptimizedPeriodStats($year, $month, $currency, $mode);
        $income = $data['income'];
        $outcome = abs($data['outcome']);

        $dailyIncomeAverage = 0;
        $dailyOutcomeAverage = 0;

        if ($mode === 'month') {
            // Calculate days in the specified month
            $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
            
            // For current month, only count elapsed days
            $currentDate = Carbon::now();
            $isCurrentMonth = $currentDate->year === $year && $currentDate->month === $month;
            $daysToUse = $isCurrentMonth ? min($currentDate->day, $daysInMonth) : $daysInMonth;
            
            if ($daysToUse > 0) {
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
            
            if ($daysToUse > 0) {
                $dailyIncomeAverage = $income / $daysToUse;
                $dailyOutcomeAverage = $outcome / $daysToUse;
            }
        } elseif ($mode === 'all') {
            // For all time, calculate based on actual transaction date range
            $firstIncome = Income::query()
                ->where('user_id', Auth::id())
                ->orderBy('transaction_date', 'asc')
                ->first();

            $firstOutcome = Outcome::query()
                ->where('user_id', Auth::id())
                ->orderBy('transaction_date', 'asc')
                ->first();

            $lastIncome = Income::query()
                ->where('user_id', Auth::id())
                ->orderBy('transaction_date', 'desc')
                ->first();

            $lastOutcome = Outcome::query()
                ->where('user_id', Auth::id())
                ->orderBy('transaction_date', 'desc')
                ->first();

            // Find the earliest and latest dates across both models
            $firstDates = array_filter([$firstIncome?->transaction_date, $firstOutcome?->transaction_date]);
            $lastDates = array_filter([$lastIncome?->transaction_date, $lastOutcome?->transaction_date]);

            $firstTransaction = !empty($firstDates) ? min($firstDates) : null;
            $lastTransaction = !empty($lastDates) ? max($lastDates) : null;
                
            if ($firstTransaction && $lastTransaction) {
                $firstDate = Carbon::parse($firstTransaction);
                $lastDate = Carbon::parse($lastTransaction);
                $daysDiff = $firstDate->diffInDays($lastDate) + 1; // +1 to include both first and last day
                
                if ($daysDiff > 0) {
                    $dailyIncomeAverage = $income / $daysDiff;
                    $dailyOutcomeAverage = $outcome / $daysDiff;
                }
            }
        }

        return [
            'dailyIncomeAverage' => round($dailyIncomeAverage, 2),
            'dailyOutcomeAverage' => round($dailyOutcomeAverage, 2),
        ];
    }
}
 