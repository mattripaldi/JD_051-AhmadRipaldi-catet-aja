<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\Income;
use App\Models\Outcome;
use Illuminate\Http\Request;
use App\Services\CurrencyService;
use App\Jobs\CategorizeTransactionJob;
use Illuminate\Support\Facades\Auth;
use App\Concerns\TransactionQueryTrait;
use App\Concerns\DashboardCalculationsTrait;

class IncomeController extends Controller
{
    use TransactionQueryTrait, DashboardCalculationsTrait {
        DashboardCalculationsTrait::calculatePercentageChange insteadof TransactionQueryTrait;
    }

    protected $currencyService;

    public function __construct(CurrencyService $currencyService)
    {
        $this->currencyService = $currencyService;
    }

    public function index(Request $request)
    {
        $year = $request->query('year', Carbon::now()->year);
        $month = $request->query('month', Carbon::now()->month);
        $mode = $request->query('mode', 'month');
        $currency = $request->query('currency', 'IDR');
        $search = $request->query('search', '');
        $category = $request->query('category', null);
        
        $query = $this->buildTransactionQuery('income', $year, $month, $mode, $currency, $search, $category)
            ->select(['id', 'user_id', 'description', 'type', 'amount', 'transaction_date', 'currency', 'category_id', 'categorization_status']);
        $perPage = 10;
        $data = $query->paginate($perPage)->withQueryString();
        
        // Get all transactions for calculations
        $allTransactions = $this->getAllTransactionsForCalculations('income', $year, $month, $mode, $currency);
        $previousPeriodTransactions = $this->getPreviousPeriodTransactions($year, $month, $mode, $currency, 'income');
        
        // Calculate totals using trait
        $currentTotals = $this->calculateTotalsFromTransactions($allTransactions, $currency, 'income');
        $previousTotals = $this->calculateTotalsFromTransactions($previousPeriodTransactions, $currency, 'income');
        
        // Calculate percentage changes using trait
        $revenueChange = $this->calculatePercentageChange($currentTotals['totalRevenue'], $previousTotals['totalRevenue']);
        $outcomeChange = $this->calculatePercentageChange($currentTotals['totalOutcome'], $previousTotals['totalOutcome']);

        // Transform the data for the frontend
        $transactions = [
            'data' => collect($data->items())->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'description' => $transaction->description,
                    'amount' => (float) $transaction->amount,
                    'type' => $transaction->type,
                    'date' => $transaction->transaction_date,
                    'transaction_date' => $transaction->transaction_date,
                    'currency' => $transaction->currency ?? 'IDR',
                    'category' => $transaction->category ? $transaction->category->name : null,
                    'category_icon' => $transaction->category ? $transaction->category->icon : 'dollar-sign',
                    'categorization_status' => $transaction->categorization_status ?? 'completed',
                    'created_at' => $transaction->created_at,
                ];
            }),
            'current_page' => $data->currentPage(),
            'last_page' => $data->lastPage(),
            'links' => $data->links()->elements[0] ?? [],
            'meta' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
            ],
        ];

        // Calculate currency breakdown using the same method as dashboard
        $currencyBreakdown = [
            'IDR' => ['balance' => $this->calculateTotalWithCurrencyConversion($year, $month, 'income', 'IDR', $mode)],
            'SGD' => ['balance' => $this->calculateTotalWithCurrencyConversion($year, $month, 'income', 'SGD', $mode)]
        ];

        // Determine period names for display
        if ($mode === 'month') {
            $currentPeriodName = date('F Y', mktime(0, 0, 0, $month, 1, $year));
            $previousMonth = $month == 1 ? 12 : $month - 1;
            $previousYear = $month == 1 ? $year - 1 : $year;
            $previousPeriodName = date('F Y', mktime(0, 0, 0, $previousMonth, 1, $previousYear));
        } else {
            $currentPeriodName = (string) $year;
            $previousPeriodName = (string) ($year - 1);
        }

        // Get the SGD to IDR currency rate for the current month
        $sgdToIdrRate = $this->currencyService->getSgdToIdrRateForMonth($year, $month);

        return Inertia::render('incomes/index', [
            'transactions' => $transactions,
            'filters' => [
                'year' => (int) $year,
                'month' => (int) $month,
                'mode' => $mode,
                'search' => $search,
                'category' => $category,
                'currency' => $currency,
            ],
            'stats' => [
                'totalRevenue' => $currentTotals['totalRevenue'],
                'totalOutcome' => $currentTotals['totalOutcome'],
                'revenueChange' => round($revenueChange, 1),
                'outcomeChange' => round($outcomeChange, 1),
                'currentPeriod' => $currentPeriodName,
                'previousPeriod' => $previousPeriodName,
                'sgdToIdrRate' => $sgdToIdrRate,
                'showCurrencyTabs' => ($year > 2024 || ($year == 2024 && $month >= 4)),
            ],
            'currencyBreakdown' => $currencyBreakdown,
        ]);
    }

    public function store(Request $request) 
    {
        $request->validate([
            'description' => 'required|min:3',            
            'amount' => 'required|min:3',            
            'date' => 'required',
            'currency' => 'required'
        ]);        

        $transaction = Income::create([
            'user_id' => Auth::id(),
            'amount' => $request->amount,
            'description' => $request->description,
            'transaction_date' => $request->date,
            'currency' => $request->currency ?? 'IDR'
        ]); 

        // Dispatch job to categorize the transaction
        CategorizeTransactionJob::dispatch($transaction);

        // Preserve current filter parameters
        $redirectParams = [];
        if ($request->year) $redirectParams['year'] = $request->year;
        if ($request->month) $redirectParams['month'] = $request->month;
        if ($request->currency && $request->currency !== 'IDR') $redirectParams['currency'] = $request->currency;

        return redirect()->route('income.index', $redirectParams);
    }

    public function update(Request $request, Income $income)
    {
        $request->validate([
            'description' => 'required|min:3',            
            'amount' => 'required|min:3',            
            'date' => 'required',
            'currency' => 'required'
        ]);
        
        // Check if description has changed
        $descriptionChanged = $income->description !== $request->description;
        
        $income->update([
            'amount' => $request->amount,
            'description' => $request->description,
            'transaction_date' => $request->date,
            'currency' => $request->currency
        ]);
        
        // If description changed, dispatch job to recategorize the transaction
        if ($descriptionChanged) {
            CategorizeTransactionJob::dispatch($income, true);
        }
        
        // Preserve current filter parameters
        $redirectParams = [];
        if ($request->year) $redirectParams['year'] = $request->year;
        if ($request->month) $redirectParams['month'] = $request->month;
        if ($request->currency && $request->currency !== 'IDR') $redirectParams['currency'] = $request->currency;
        
        return redirect()->route('income.index', $redirectParams);
    }

    public function destroy(Income $income, Request $request)
    {
        $income->delete();
        
        // Preserve current filter parameters
        $redirectParams = [];
        if ($request->year) $redirectParams['year'] = $request->year;
        if ($request->month) $redirectParams['month'] = $request->month;
        if ($request->currency && $request->currency !== 'IDR') $redirectParams['currency'] = $request->currency;
        
        return redirect()->route('income.index', $redirectParams);
    }
}
