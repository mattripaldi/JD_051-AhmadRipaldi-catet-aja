<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\Outcome;
use Illuminate\Http\Request;
use App\Services\CurrencyService;
use App\Jobs\CategorizeTransactionJob;
use Illuminate\Support\Facades\Auth;
use App\Concerns\TransactionQueryTrait;
use App\Concerns\DashboardCalculationsTrait;

class OutcomeController extends Controller
{
    use TransactionQueryTrait, DashboardCalculationsTrait {
        DashboardCalculationsTrait::calculatePercentageChange insteadof TransactionQueryTrait;
    }

    protected $currencyService;

    public function __construct(CurrencyService $currencyService)
    {
        $this->currencyService = $currencyService;
    }

    public function create(Request $request, $accountId)
    {
        // Get available currencies for the user
        $currencies = Auth::user()->currencies()
                        ->select('name', 'symbol')
                        ->orderBy('name')
                        ->get()
                        ->map(function ($currency) {
                            return [
                                'name' => $currency->name,
                                'symbol' => $currency->symbol,
                                'display' => $currency->name . ' (' . $currency->symbol . ')'
                            ];
                        });
        
        // Add IDR as default if not exists
        if ($currencies->where('name', 'IDR')->isEmpty()) {
            $currencies->prepend([
                'name' => 'IDR',
                'symbol' => 'Rp',
                'display' => 'IDR (Rp)'
            ]);
        }

        return Inertia::modal('Outcomes/Create', [
            'filters' => [
                'year' => (int) Carbon::now()->year,
                'month' => (int) Carbon::now()->month,
                'currency' => 'IDR',
            ],
            'currencies' => $currencies,
        ])->baseRoute('outcome.index', ['account' => $accountId]);
    }

    public function edit(Request $request, $accountId, Outcome $outcome)
    {
        $year = $request->query('year', Carbon::now()->year);
        $month = $request->query('month', Carbon::now()->month);
        $currency = $request->query('currency', 'IDR');

        // Get available currencies for the user
        $currencies = Auth::user()->currencies()
                        ->select('name', 'symbol')
                        ->orderBy('name')
                        ->get()
                        ->map(function ($currency) {
                            return [
                                'name' => $currency->name,
                                'symbol' => $currency->symbol,
                                'display' => $currency->name . ' (' . $currency->symbol . ')'
                            ];
                        });
        
        // Add IDR as default if not exists
        if ($currencies->where('name', 'IDR')->isEmpty()) {
            $currencies->prepend([
                'name' => 'IDR',
                'symbol' => 'Rp',
                'display' => 'IDR (Rp)'
            ]);
        }

        return Inertia::modal('Outcomes/Edit', [
            'transaction' => [
                'id' => $outcome->id,
                'description' => $outcome->description,
                'amount' => (float) $outcome->amount,
                'date' => $outcome->transaction_date,
                'transaction_date' => $outcome->transaction_date,
                'currency' => $outcome->currency ?? 'IDR',
            ],
            'filters' => [
                'year' => (int) $year,
                'month' => (int) $month,
                'currency' => $currency,
            ],
            'currencies' => $currencies,
        ])->baseRoute('outcome.index', ['account' => $accountId]);
    }

    public function confirmDelete($accountId, Outcome $outcome)
    {
        return Inertia::modal('Outcomes/Delete', [
            'transaction' => [
                'id' => $outcome->id,
                'account_id' => $outcome->account_id,
                'description' => $outcome->description,
                'amount' => (float) $outcome->amount,
                'date' => $outcome->transaction_date,
                'currency' => $outcome->currency ?? 'IDR',
            ],
        ])->baseRoute('outcome.index', ['account' => $accountId]);
    }

    public function index(Request $request)
    {
        $year = $request->query('year', Carbon::now()->year);
        $month = $request->query('month', Carbon::now()->month);
        $mode = $request->query('mode', 'month');
        $currency = $request->query('currency', 'IDR');
        $search = $request->query('search', '');
        $category = $request->query('category', null);
        
        // Get paginated transactions with optimized query
        $query = $this->buildTransactionQuery('outcome', $year, $month, $mode, $currency, $search, $category)
            ->select(['id', 'user_id', 'description', 'amount', 'transaction_date', 'currency', 'category_id', 'categorization_status']);
        $perPage = 10;
        $data = $query->paginate($perPage)->withQueryString();
        
        // Get all transactions for calculations
        $allTransactions = $this->getAllTransactionsForCalculations('outcome', $year, $month, $mode, $currency);
        $previousPeriodTransactions = $this->getPreviousPeriodTransactions($year, $month, $mode, $currency, 'outcome');

        // Calculate totals using trait
        $currentTotals = $this->calculateTotalsFromTransactions($allTransactions, $currency, 'outcome');
        $previousTotals = $this->calculateTotalsFromTransactions($previousPeriodTransactions, $currency, 'outcome');
        
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
                    'type' => 'outcome',
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

        // Calculate currency breakdown based on user's actual currencies
        $userCurrencies = $this->getUserCurrencies();
        $currencyBreakdown = [];
        
        foreach ($userCurrencies as $userCurrency) {
            $currencyBreakdown[$userCurrency] = [
                'balance' => $this->calculateTotalWithCurrencyConversion($year, $month, 'outcome', $userCurrency, $mode)
            ];
        }
        
        // Fallback to IDR if no currencies found
        if (empty($currencyBreakdown)) {
            $currencyBreakdown['IDR'] = [
                'balance' => $this->calculateTotalWithCurrencyConversion($year, $month, 'outcome', 'IDR', $mode)
            ];
        }

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

        // Get exchange rates for all user currencies to IDR for the current month
        $currencyRates = $this->currencyService->getExchangeRatesForUserCurrencies(
            array_keys($currencyBreakdown), 
            Auth::id(), 
            null, 
            $year, 
            $month
        );

        // Get available currencies for the user
        $currencies = Auth::user()->currencies()
                        ->select('name', 'symbol')
                        ->orderBy('name')
                        ->get()
                        ->map(function ($currency) {
                            return [
                                'name' => $currency->name,
                                'symbol' => $currency->symbol,
                                'display' => $currency->name . ' (' . $currency->symbol . ')'
                            ];
                        });

        return Inertia::render('Outcomes/Index', [
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
                'currencyRates' => $currencyRates,
                'showCurrencyTabs' => ($year > 2024 || ($year == 2024 && $month >= 4)),
            ],
            'currencyBreakdown' => $currencyBreakdown,
            'currencies' => $currencies,
        ]);
    }

    public function store(Request $request, $accountId)
    {
        $request->validate([
            'description' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
        ]);

        $transaction = Outcome::create([
            'user_id' => Auth::id(),
            'account_id' => $accountId,
            'amount' => $request->amount,
            'description' => $request->description,
            'transaction_date' => $request->date,
            'currency' => $request->currency ?? 'IDR'
        ]);

        // // Temporary Disable 
        // // Dispatch job to categorize the transaction
        // CategorizeTransactionJob::dispatch($transaction);

        // Preserve current filter parameters
        $redirectParams = [];
        if ($request->year) $redirectParams['year'] = $request->year;
        if ($request->month) $redirectParams['month'] = $request->month;
        if ($request->currency && $request->currency !== 'IDR') $redirectParams['currency'] = $request->currency;

        return redirect()->route('outcome.index', ['account' => $accountId] + $redirectParams);
    }

    public function update(Request $request, $accountId, Outcome $outcome)
    {
        $request->validate([
            'description' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
        ]);
        
        // Check if description has changed
        $descriptionChanged = $outcome->description !== $request->description;
        
        $outcome->update([
            'amount' => $request->amount,
            'description' => $request->description,
            'transaction_date' => $request->date,
            'currency' => $request->currency
        ]);
        
        // // Temporary Disable 
        // // If description changed, dispatch job to recategorize the transaction
        // if ($descriptionChanged) {
        //     CategorizeTransactionJob::dispatch($outcome, true);
        // }
        
        // Preserve current filter parameters
        $redirectParams = [];
        if ($request->year) $redirectParams['year'] = $request->year;
        if ($request->month) $redirectParams['month'] = $request->month;
        if ($request->currency && $request->currency !== 'IDR') $redirectParams['currency'] = $request->currency;

        return redirect()->route('outcome.index', ['account' => $accountId] + $redirectParams);
    }

    public function destroy(Request $request, $accountId, Outcome $outcome)
    {
        $outcome->delete();
        
        // Preserve current filter parameters
        $redirectParams = [];
        if ($request->year) $redirectParams['year'] = $request->year;
        if ($request->month) $redirectParams['month'] = $request->month;
        if ($request->currency && $request->currency !== 'IDR') $redirectParams['currency'] = $request->currency;

        return redirect()->route('outcome.index', ['account' => $accountId] + $redirectParams);
    }
}
