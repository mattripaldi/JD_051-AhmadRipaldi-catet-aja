<?php

namespace App\Concerns;

use App\Models\Income;
use App\Models\Outcome;
use App\Models\Currency;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

trait TransactionQueryTrait
{
    /**
     * Build base transaction query with filters and eager loading
     *
     * @param string $type
     * @param int $year
     * @param int $month
     * @param string $mode
     * @param int|null $currencyId
     * @param string $search
     * @param string $category
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function buildTransactionQuery($type, $year, $month, $mode, $currencyId, $search = '', $category = null, $accountId = null)
    {
        $model = $type === 'income' ? Income::class : Outcome::class;
        $query = $model::query()
            ->with(['category', 'currency']) // Eager load category and currency to prevent N+1 queries
            ->where('user_id', Auth::id())
            ->where('account_id', $accountId)
            ->whereYear('transaction_date', $year);

        // Add month filtering only if mode is 'month'
        if ($mode === 'month') {
            $query->whereMonth('transaction_date', $month);
        }

        $query->orderBy('transaction_date', 'desc')
              ->orderBy('id', 'desc');

        // Apply currency filtering
        $this->applyCurrencyIdFilter($query, $currencyId);
            
        // Add search functionality
        if (!empty($search)) {
            $query->where('description', 'like', "%{$search}%");
        }
        
        // Add category filtering
        if (!empty($category)) {
            $query->whereHas('category', function($q) use ($category) {
                $q->where('name', $category);
            });
        }
        
        return $query;
    }
    
    /**
     * Get optimized transaction totals for calculations (using aggregation)
     *
     * @param string $type
     * @param int $year
     * @param int $month
     * @param string $mode
     * @param int|null $currencyId
     * @return array
     */
    protected function getOptimizedTransactionTotals($type, $year, $month, $mode, $currencyId, $accountId = null)
    {
        $model = $type === 'income' ? Income::class : Outcome::class;
        $query = $model::query()
            ->selectRaw('SUM(amount) as total, COUNT(*) as count')
            ->where('user_id', Auth::id())
            ->where('account_id', $accountId)
            ->whereYear('transaction_date', $year);
            
        // Add month filtering only if mode is 'month'
        if ($mode === 'month') {
            $query->whereMonth('transaction_date', $month);
        }
            
        // Apply currency filtering for stats calculation
        $this->applyCurrencyIdFilter($query, $currencyId);
        
        $result = $query->first();

        return [
            'total' => (float) ($result->total ?? 0),
            'count' => (int) ($result->count ?? 0),
        ];
    }
    
    /**
     * Get all transactions for calculations (without pagination) - kept for backward compatibility
     *
     * @param string $type
     * @param int $year
     * @param int $month
     * @param string $mode
     * @param int|null $currencyId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getAllTransactionsForCalculations($type, $year, $month, $mode, $currencyId, $accountId = null)
    {
        $model = $type === 'income' ? Income::class : Outcome::class;
        $query = $model::query()
            ->select(['amount', 'currency_id', 'transaction_date']) // Only select needed columns
            ->where('user_id', Auth::id())
            ->where('account_id', $accountId)
            ->whereYear('transaction_date', $year);
            
        // Add month filtering only if mode is 'month'
        if ($mode === 'month') {
            $query->whereMonth('transaction_date', $month);
        }
            
        // Apply currency filtering for stats calculation
        $this->applyCurrencyIdFilter($query, $currencyId);
        
        return $query->get();
    }
    
    /**
     * Get optimized previous period totals for comparison
     *
     * @param int $year
     * @param int $month
     * @param string $mode
     * @param int|null $currencyId
     * @return array
     */
    protected function getOptimizedPreviousPeriodTotals($year, $month, $mode, $currencyId, $type, $accountId = null)
    {
        if ($mode === 'month') {
            $previousMonth = $month == 1 ? 12 : $month - 1;
            $previousYear = $month == 1 ? $year - 1 : $year;
        } else {
            // For year mode, compare with previous year
            $previousMonth = $month;
            $previousYear = $year - 1;
        }

        $model = $type === 'income' ? Income::class : Outcome::class;
        $query = $model::query()
            ->selectRaw('SUM(amount) as total')
            ->where('user_id', Auth::id())
            ->where('account_id', $accountId)
            ->whereYear('transaction_date', $previousYear);
            
        // Add month filtering only if mode is 'month'
        if ($mode === 'month') {
            $query->whereMonth('transaction_date', $previousMonth);
        }

        // Apply same currency filtering for previous period
        $this->applyCurrencyIdFilter($query, $currencyId);

        $result = $query->first();

        if ($type === 'income') {
            return [
                'totalRevenue' => (float) ($result->total ?? 0),
                'totalOutcome' => 0,
            ];
        } else {
            return [
                'totalRevenue' => 0,
                'totalOutcome' => (float) ($result->total ?? 0),
            ];
        }
    }
    
    /**
     * Get previous period transactions for comparison - kept for backward compatibility
     *
     * @param int $year
     * @param int $month
     * @param string $mode
     * @param int|null $currencyId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getPreviousPeriodTransactions($year, $month, $mode, $currencyId, $type, $accountId = null)
    {
        if ($mode === 'month') {
            $previousMonth = $month == 1 ? 12 : $month - 1;
            $previousYear = $month == 1 ? $year - 1 : $year;
        } else {
            // For year mode, compare with previous year
            $previousMonth = $month;
            $previousYear = $year - 1;
        }

        $model = $type === 'income' ? Income::class : Outcome::class;
        $query = $model::query()
            ->select(['amount', 'currency_id', 'transaction_date']) // Only select needed columns
            ->where('user_id', Auth::id())
            ->where('account_id', $accountId)
            ->whereYear('transaction_date', $previousYear);
            
        // Add month filtering only if mode is 'month'
        if ($mode === 'month') {
            $query->whereMonth('transaction_date', $previousMonth);
        }

        // Apply same currency filtering for previous period
        $this->applyCurrencyIdFilter($query, $currencyId);

        return $query->get();
    }
    
    /**
     * Apply currency filtering to query
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int|null $currencyId
     * @return void
     */
    protected function applyCurrencyIdFilter($query, $currencyId)
    {
        if ($currencyId) {
            $query->where('currency_id', $currencyId);
        } else {
            // If no currency_id specified, include all transactions (for backward compatibility)
            // You might want to add logic here to filter by default currency if needed
        }
    }
    
    /**
     * Calculate totals from transactions collection with currency conversion
     * Converts foreign currencies to IDR when viewing in IDR mode
     *
     * @param \Illuminate\Database\Eloquent\Collection $transactions
     * @param int|null $currencyId
     * @param string $type
     * @return array
     */
    protected function calculateTotalsFromTransactions($transactions, $currencyId, $type = 'income')
    {
        $totalRevenue = 0;
        $totalOutcome = 0;
        
        foreach ($transactions as $transaction) {
            $amount = $transaction->amount;

            // Convert any foreign currency to IDR if we're viewing IDR and transaction is in foreign currency
            if ($currencyId && $transaction->currency && $transaction->currency->name !== 'IDR') {
                // Get the IDR currency ID for the user
                $idrCurrencyId = Currency::where('user_id', Auth::id())
                    ->where('name', 'IDR')
                    ->value('id');

                if ($idrCurrencyId) {
                    $amount = $this->currencyService->convertCurrency(
                        $amount,
                        $transaction->currency_id,
                        $idrCurrencyId,
                        Auth::id()
                    );
                }
            }

            // Since this method is called with transactions of a specific type,
            // we can determine the type from the calling context
            if ($type === 'income') {
                $totalRevenue += $amount;
            } else if ($type === 'outcome') {
                $totalOutcome += abs($amount);
            }
        }
        
        return [
            'totalRevenue' => $totalRevenue,
            'totalOutcome' => $totalOutcome,
        ];
    }
    
    /**
     * Calculate percentage changes with UI-friendly limits
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
            // Cap at 100% for UI purposes
            $change = max(min($change, 100), -100);
        }
        
        return $change;
    }
}
 