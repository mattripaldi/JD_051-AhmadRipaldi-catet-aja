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
use App\Concerns\CurrencyTrait;

class OutcomeController extends Controller
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

        return Inertia::modal('Outcomes/Create', [
            'filters' => [
                'year' => (int) Carbon::now()->year,
                'month' => (int) Carbon::now()->month,
                'currency_id' => null, // Will default to IDR
            ],
            'currencies' => $currencies,
        ])->baseRoute('outcome.index', ['account' => $accountId]);
    }

    public function edit(Request $request, $accountId, Outcome $outcome)
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

        return Inertia::modal('Outcomes/Edit', [
            'transaction' => [
                'id' => $outcome->id,
                'description' => $outcome->description,
                'amount' => (float) $outcome->amount,
                'date' => $outcome->transaction_date,
                'transaction_date' => $outcome->transaction_date,
                'currency_id' => $outcome->currency_id,
            ],
            'filters' => [
                'year' => (int) $year,
                'month' => (int) $month,
                'currency_id' => $currencyId,
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
                'currency_id' => $outcome->currency_id,
            ],
        ])->baseRoute('outcome.index', ['account' => $accountId]);
    }

    public function index($accountId, Request $request)
    {
        $year = $request->query('year', Carbon::now()->year);
        $month = $request->query('month', Carbon::now()->month);
        $mode = $request->query('mode', 'month');
        $currencyId = $request->query('currency_id', $this->getDefaultCurrencyId($accountId));
        $search = $request->query('search', '');
        $category = $request->query('category', null);

        // Get paginated transactions with optimized query
        $query = $this->buildTransactionQuery('outcome', $year, $month, $mode, $currencyId, $search, $category, $accountId)
            ->select(['id', 'user_id', 'description', 'amount', 'transaction_date', 'currency_id', 'category_id', 'categorization_status']);
        $perPage = 10;
        $data = $query->paginate($perPage)->withQueryString();

        // Get all transactions for calculations
        $allTransactions = $this->getAllTransactionsForCalculations('outcome', $year, $month, $mode, $currencyId, $accountId);
        $previousPeriodTransactions = $this->getPreviousPeriodTransactions($year, $month, $mode, $currencyId, 'outcome', $accountId);

        // Calculate totals using trait
        $currentTotals = $this->calculateTotalsFromTransactions($allTransactions, $currencyId, 'outcome');
        $previousTotals = $this->calculateTotalsFromTransactions($previousPeriodTransactions, $currencyId, 'outcome');

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
                'balance' => $this->calculateTotalWithCurrencyConversion($year, $month, 'outcome', $currMap, $mode, $accountId)
            ];
        }

        // Fallback to IDR if no currencies found
        if (empty($currencyBreakdown)) {
            $currencyBreakdown['IDR'] = [
                'balance' => $this->calculateTotalWithCurrencyConversion($year, $month, 'outcome', null, $mode, $accountId)
            ];
        }

        // Determine period names for display
        if ($mode === 'month') {
            $currentPeriodName = Carbon::create($year, $month, 1)->locale('id')->isoFormat('MMMM YYYY');
            $previousMonth = $month == 1 ? 12 : $month - 1;
            $previousYear = $month == 1 ? $year - 1 : $year;
            $previousPeriodName = Carbon::create($previousYear, $previousMonth, 1)->locale('id')->isoFormat('MMMM YYYY');
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

        return Inertia::render('Outcomes/Index', [
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

        // Handle both model instances and IDs
        $accountIdValue = is_object($accountId) ? $accountId->id : $accountId;

        $transaction = Outcome::create([
            'user_id' => Auth::id(),
            'account_id' => $accountIdValue,
            'amount' => $request->amount,
            'description' => $request->description,
            'transaction_date' => $request->date,
            'currency_id' => $request->currency_id
        ]);

        // Dispatch job to categorize the transaction
        CategorizeTransactionJob::dispatch($transaction);

        // Preserve current filter parameters
        $redirectParams = [];
        if ($request->year) $redirectParams['year'] = $request->year;
        if ($request->month) $redirectParams['month'] = $request->month;
        if ($request->currency_id) $redirectParams['currency_id'] = $request->currency_id;

        return redirect()->route('outcome.index', ['account' => $accountId] + $redirectParams);
    }

    public function update(Request $request, $accountId, Outcome $outcome)
    {
        $request->validate([
            'description' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
            'currency_id' => 'nullable|integer|exists:currencies,id',
        ]);

        // Check if description has changed
        $descriptionChanged = $outcome->description !== $request->description;

        $outcome->update([
            'amount' => $request->amount,
            'description' => $request->description,
            'transaction_date' => $request->date,
            'currency_id' => $request->currency_id
        ]);

        if ($descriptionChanged) {
            CategorizeTransactionJob::dispatch($outcome, true);
        }

        // Preserve current filter parameters
        $redirectParams = [];
        if ($request->year) $redirectParams['year'] = $request->year;
        if ($request->month) $redirectParams['month'] = $request->month;
        if ($request->currency_id) $redirectParams['currency_id'] = $request->currency_id;

        return redirect()->route('outcome.index', ['account' => $accountId] + $redirectParams);
    }

    public function destroy(Request $request, $accountId, Outcome $outcome)
    {
        $outcome->delete();

        // Preserve current filter parameters
        $redirectParams = [];
        if ($request->year) $redirectParams['year'] = $request->year;
        if ($request->month) $redirectParams['month'] = $request->month;
        if ($request->currency_id) $redirectParams['currency_id'] = $request->currency_id;

        return redirect()->route('outcome.index', ['account' => $accountId] + $redirectParams);
    }
}
