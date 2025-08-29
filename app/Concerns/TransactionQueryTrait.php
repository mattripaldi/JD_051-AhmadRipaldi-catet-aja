<?php

namespace App\Concerns;

use App\Models\Income;
use App\Models\Outcome;
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
     * @param string $currency
     * @param string $search
     * @param string $category
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function buildTransactionQuery($type, $year, $month, $mode, $currency, $search = '', $category = null)
    {
        $model = $type === 'income' ? Income::class : Outcome::class;
        $query = $model::query()
            ->with('category') // Eager load category to prevent N+1 queries
            ->where('user_id', Auth::id())
            ->whereYear('transaction_date', $year);
            
        // Add month filtering only if mode is 'month'
        if ($mode === 'month') {
            $query->whereMonth('transaction_date', $month);
        }
        
        $query->orderBy('transaction_date', 'desc')
              ->orderBy('id', 'desc');
            
        // Apply currency filtering
        $this->applyCurrencyFilter($query, $currency);
            
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
     * @param string $currency
     * @return array
     */
    protected function getOptimizedTransactionTotals($type, $year, $month, $mode, $currency)
    {
        $model = $type === 'income' ? Income::class : Outcome::class;
        $query = $model::query()
            ->selectRaw('SUM(amount) as total, COUNT(*) as count')
            ->where('user_id', Auth::id())
            ->whereYear('transaction_date', $year);
            
        // Add month filtering only if mode is 'month'
        if ($mode === 'month') {
            $query->whereMonth('transaction_date', $month);
        }
            
        // Apply currency filtering for stats calculation
        $this->applyCurrencyFilter($query, $currency);
        
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
     * @param string $currency
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getAllTransactionsForCalculations($type, $year, $month, $mode, $currency)
    {
        $model = $type === 'income' ? Income::class : Outcome::class;
        $query = $model::query()
            ->select(['amount', 'currency', 'transaction_date']) // Only select needed columns
            ->where('user_id', Auth::id())
            ->whereYear('transaction_date', $year);
            
        // Add month filtering only if mode is 'month'
        if ($mode === 'month') {
            $query->whereMonth('transaction_date', $month);
        }
            
        // Apply currency filtering for stats calculation
        $this->applyCurrencyFilter($query, $currency);
        
        return $query->get();
    }
    
    /**
     * Get optimized previous period totals for comparison
     * 
     * @param int $year
     * @param int $month
     * @param string $mode
     * @param string $currency
     * @return array
     */
    protected function getOptimizedPreviousPeriodTotals($year, $month, $mode, $currency, $type)
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
            ->whereYear('transaction_date', $previousYear);
            
        // Add month filtering only if mode is 'month'
        if ($mode === 'month') {
            $query->whereMonth('transaction_date', $previousMonth);
        }
            
        // Apply same currency filtering for previous period
        $this->applyCurrencyFilter($query, $currency);
        
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
     * @param string $currency
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getPreviousPeriodTransactions($year, $month, $mode, $currency, $type)
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
            ->select(['amount', 'currency', 'transaction_date']) // Only select needed columns
            ->where('user_id', Auth::id())
            ->whereYear('transaction_date', $previousYear);
            
        // Add month filtering only if mode is 'month'
        if ($mode === 'month') {
            $query->whereMonth('transaction_date', $previousMonth);
        }
            
        // Apply same currency filtering for previous period
        $this->applyCurrencyFilter($query, $currency);
        
        return $query->get();
    }
    
    /**
     * Apply currency filtering to query
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $currency
     * @return void
     */
    protected function applyCurrencyFilter($query, $currency)
    {
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
    }
    
    /**
     * Calculate totals from transactions collection
     *
     * @param \Illuminate\Database\Eloquent\Collection $transactions
     * @param string $currency
     * @param string $type
     * @return array
     */
    protected function calculateTotalsFromTransactions($transactions, $currency, $type = 'income')
    {
        $totalRevenue = 0;
        $totalOutcome = 0;
        
        foreach ($transactions as $transaction) {
            $amount = $transaction->amount;

            // Only convert SGD to IDR if we're viewing IDR and transaction is SGD
            if ($currency === 'IDR' && $transaction->currency === 'SGD') {
                $amount = $this->currencyService->convertSgdToIdrForDate($amount, $transaction->transaction_date);
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
 