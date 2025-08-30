<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\Income;
use Illuminate\Http\Request;
use App\Services\CurrencyService;
use App\Jobs\CategorizeTransactionJob;
use Illuminate\Support\Facades\Auth;
use App\Concerns\TransactionQueryTrait;
use App\Concerns\DashboardCalculationsTrait;
use App\Concerns\CurrencyTrait;

class IncomeController extends Controller
{
    use TransactionQueryTrait, DashboardCalculationsTrait, CurrencyTrait {
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
                        ->select('id', 'name', 'symbol')
                        ->orderBy('name')
                        ->get()
                        ->map(function ($currency) {
                            return [
                                'id' => $currency->id,
                                'name' => $currency->name,
                                'symbol' => $currency->symbol,
                                'display' => $currency->name . ' (' . $currency->symbol . ')'
                            ];
                        });

        return Inertia::modal('Incomes/Create', [
            'filters' => [
                'year' => (int) Carbon::now()->year,
                'month' => (int) Carbon::now()->month,
                'currency_id' => null,
            ],
            'currencies' => $currencies,
        ])->baseRoute('income.index', ['account' => $accountId]);
    }

    public function edit(Request $request, $accountId, Income $income)
    {
        $year = $request->query('year', Carbon::now()->year);
        $month = $request->query('month', Carbon::now()->month);
        $currencyId = $request->query('currency_id', null);

        // Get available currencies for the user
        $currencies = Auth::user()->currencies()
                        ->select('id', 'name', 'symbol')
                        ->orderBy('name')
                        ->get()
                        ->map(function ($currency) {
                            return [
                                'id' => $currency->id,
                                'name' => $currency->name,
                                'symbol' => $currency->symbol,
                                'display' => $currency->name . ' (' . $currency->symbol . ')'
                            ];
                        });

        // Add IDR as default if not exists
        if ($currencies->where('name', 'IDR')->isEmpty()) {
            $currencies->prepend([
                'id' => null, // Will be resolved to default IDR currency
                'name' => 'IDR',
                'symbol' => 'Rp',
                'display' => 'IDR (Rp)'
            ]);
        }

        return Inertia::modal('Incomes/Edit', [
            'transaction' => [
                'id' => $income->id,
                'description' => $income->description,
                'amount' => (float) $income->amount,
                'date' => $income->transaction_date,
                'transaction_date' => $income->transaction_date,
                'currency_id' => $income->currency_id,
            ],
            'filters' => [
                'year' => (int) $year,
                'month' => (int) $month,
                'currency_id' => $currencyId,
            ],
            'currencies' => $currencies,
        ])->baseRoute('income.index', ['account' => $accountId]);
    }

    public function confirmDelete($accountId, Income $income)
    {
        return Inertia::modal('Incomes/Delete', [
            'transaction' => [
                'id' => $income->id,
                'account_id' => $income->account_id,
                'description' => $income->description,
                'amount' => (float) $income->amount,
                'date' => $income->transaction_date,
                'currency_id' => $income->currency_id,
            ],
        ])->baseRoute('income.index', ['account' => $accountId]);
    }

    public function index($accountId, Request $request)
    {
        $year = $request->query('year', Carbon::now()->year);
        $month = $request->query('month', Carbon::now()->month);
        $mode = $request->query('mode', 'month');
        $currencyId = $request->query('currency_id', $this->getDefaultCurrencyId($accountId));
        $search = $request->query('search', '');
        $category = $request->query('category', null);

        $query = $this->buildTransactionQuery('income', $year, $month, $mode, $currencyId, $search, $category)
            ->select(['id', 'user_id', 'description', 'amount', 'transaction_date', 'currency_id', 'category_id', 'categorization_status']);
        $perPage = 10;
        $data = $query->paginate($perPage)->withQueryString();

        // Get all transactions for calculations
        $allTransactions = $this->getAllTransactionsForCalculations('income', $year, $month, $mode, $currencyId);
        $previousPeriodTransactions = $this->getPreviousPeriodTransactions($year, $month, $mode, $currencyId, 'income');

        // Calculate totals using trait
        $currentTotals = $this->calculateTotalsFromTransactions($allTransactions, $currencyId, 'income');
        $previousTotals = $this->calculateTotalsFromTransactions($previousPeriodTransactions, $currencyId, 'income');

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
                    'type' => 'income',
                    'date' => $transaction->transaction_date,
                    'transaction_date' => $transaction->transaction_date,
                    'currency_id' => $transaction->currency_id,
                    'currency' => $transaction->currency ? $transaction->currency->name : 'IDR',
                    'currency_symbol' => $transaction->currency ? $transaction->currency->symbol : 'Rp',
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

        // Get currency mapping for breakdown calculations
        $currencyMapping = Auth::user()->currencies()->pluck('id', 'name')->toArray();

        foreach ($userCurrencies as $userCurrency) {
            $currMap = $currencyMapping[$userCurrency] ?? null;
            $currencyBreakdown[$userCurrency] = [
                'balance' => $this->calculateTotalWithCurrencyConversion($year, $month, 'income', $currMap, $mode)
            ];
        }

        // Fallback to IDR if no currencies found
        if (empty($currencyBreakdown)) {
            $currencyBreakdown['IDR'] = [
                'balance' => $this->calculateTotalWithCurrencyConversion($year, $month, 'income', null, $mode)
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

        // Get available currencies for the user (similar to Dashboard)
        $userCurrencies = $this->getUserCurrencies();

        // Get exchange rates for frontend currency conversion (like Dashboard)
        $exchangeRates = [];
        foreach ($userCurrencies as $currency) {
            if ($currency !== 'IDR') {
                $rate = $this->currencyService->getToIdrRate($currency, Auth::id(), null);
                $exchangeRates[strtolower($currency) . 'ToIdrRate'] = $rate;
            }
        }

        // Get available currencies for the user
        $currencies = Auth::user()->currencies()
                        ->select('id', 'name', 'symbol')
                        ->orderBy('name')
                        ->get()
                        ->map(function ($currency) {
                            return [
                                'id' => $currency->id,
                                'name' => $currency->name,
                                'symbol' => $currency->symbol,
                                'display' => $currency->name . ' (' . $currency->symbol . ')'
                            ];
                        });

        return Inertia::render('Incomes/Index', [
            'transactions' => $transactions,
            'filters' => [
                'year' => (int) $year,
                'month' => (int) $month,
                'mode' => $mode,
                'search' => $search,
                'category' => $category,
                'currency_id' => $currencyId,
            ],
            'stats' => array_merge([
                'totalRevenue' => $currentTotals['totalRevenue'],
                'totalOutcome' => $currentTotals['totalOutcome'],
                'revenueChange' => round($revenueChange, 1),
                'outcomeChange' => round($outcomeChange, 1),
                'currentPeriod' => $currentPeriodName,
                'previousPeriod' => $previousPeriodName,
                'showCurrencyTabs' => ($year > 2024 || ($year == 2024 && $month >= 4)),
            ], $exchangeRates),
            'currencyBreakdown' => $currencyBreakdown,
            'currencies' => $currencies,
            'availableCurrencies' => $userCurrencies,
        ]);
    }

    public function store(Request $request, $accountId)
    {
        $request->validate([
            'description' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
            'currency_id' => 'nullable|integer|exists:currencies,id',
        ]);

        $transaction = Income::create([
            'user_id' => Auth::id(),
            'account_id' => $accountId,
            'amount' => $request->amount,
            'description' => $request->description,
            'transaction_date' => $request->date,
            'currency_id' => $request->currency_id
        ]);

        // // Temporary Disable
        // // Dispatch job to categorize the transaction
        // CategorizeTransactionJob::dispatch($transaction);

        // Preserve current filter parameters
        $redirectParams = [];
        if ($request->year) $redirectParams['year'] = $request->year;
        if ($request->month) $redirectParams['month'] = $request->month;
        if ($request->currency_id) $redirectParams['currency_id'] = $request->currency_id;

        return redirect()->route('income.index', ['account' => $accountId] + $redirectParams);
    }

    public function update(Request $request, $accountId, Income $income)
    {
        $request->validate([
            'description' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
            'currency_id' => 'nullable|integer|exists:currencies,id',
        ]);

        // Check if description has changed
        $descriptionChanged = $income->description !== $request->description;

        $income->update([
            'amount' => $request->amount,
            'description' => $request->description,
            'transaction_date' => $request->date,
            'currency_id' => $request->currency_id
        ]);

        // // Temporary Disable
        // // If description changed, dispatch job to recategorize the transaction
        // if ($descriptionChanged) {
        //     CategorizeTransactionJob::dispatch($income, true);
        // }

        // Preserve current filter parameters
        $redirectParams = [];
        if ($request->year) $redirectParams['year'] = $request->year;
        if ($request->month) $redirectParams['month'] = $request->month;
        if ($request->currency_id) $redirectParams['currency_id'] = $request->currency_id;

        return redirect()->route('income.index', ['account' => $accountId] + $redirectParams);
    }

    public function destroy(Request $request, $accountId, Income $income)
    {
        $income->delete();

        // Preserve current filter parameters
        $redirectParams = [];
        if ($request->year) $redirectParams['year'] = $request->year;
        if ($request->month) $redirectParams['month'] = $request->month;
        if ($request->currency_id) $redirectParams['currency_id'] = $request->currency_id;

        return redirect()->route('income.index', ['account' => $accountId] + $redirectParams);
    }
}
