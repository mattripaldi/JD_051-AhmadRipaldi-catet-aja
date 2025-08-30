<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Income;
use App\Models\Outcome;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Services\CurrencyService;
use App\Concerns\DashboardCalculationsTrait;

class DashboardController extends Controller
{
    use DashboardCalculationsTrait;

    private $currencyService;

    public function __construct(CurrencyService $currencyService)
    {
        $this->currencyService = $currencyService;
    }

    public function __invoke(Request $request, Account $account)
    {
        $user = Auth::user();

        // Ensure the account belongs to the authenticated user
        if ($account->user_id !== $user->id) {
            abort(403, 'Unauthorized');
        }

        // Ensure the user has selected this account as their current account
        if ($user->current_account_id !== $account->id) {
            return redirect()->route('account.index')->with('error', 'Please select an account first.');
        }

        // Get current year and month
        $year = $request->query('year', Carbon::now()->year);
        $month = $request->query('month', Carbon::now()->month);
        $mode = $request->query('mode', 'month');

        // Get currency breakdown and available currencies for the components
        $currencyBreakdown = $this->getCurrencyBreakdown($year, $month, $mode, $account->id);
        $availableCurrencies = $this->getUserCurrencies($account->id);

        // Get exchange rates for frontend currency conversion
        $exchangeRates = [];
        foreach ($availableCurrencies as $currency) {
            if ($currency !== 'IDR') {
                $rate = $this->currencyService->getToIdrRate($currency, Auth::id(), $account->id);
                $exchangeRates[strtolower($currency) . 'ToIdrRate'] = $rate;
            }
        }

        // Get default currency ID (IDR for now, but can be made configurable)
        $defaultCurrencyId = Auth::user()->currencies()
            ->where('name', 'IDR')
            ->value('id') ?? null;

        // Calculate stats based on mode using optimized methods
        if ($mode === 'month') {
            $stats = $this->calculateMonthlyStats($year, $month, $defaultCurrencyId, $account->id);
        } elseif ($mode === 'year') {
            $stats = $this->calculateYearlyStats($year, $defaultCurrencyId, $account->id);
        } else {
            $stats = $this->calculateAllTimeStats($defaultCurrencyId, $account->id);
        }

        // Ensure stats has all required keys with defaults
        $stats = array_merge([
            'totalRevenue' => 0,
            'totalOutcome' => 0,
            'balance' => 0,
            'revenueChange' => 0,
            'outcomeChange' => 0,
            'balanceChange' => 0,
            'currentPeriod' => 'Unknown',
            'previousPeriod' => '',
            'previousPeriodRevenue' => 0,
            'previousPeriodOutcome' => 0,
            'previousPeriodBalance' => 0,
        ], $stats ?? []);

        // Get 10 most recent transactions (combined incomes and outcomes)
        $recentIncomes = Income::with(['category', 'currency'])
            ->select(['id', 'user_id', 'account_id', 'description', 'amount', 'transaction_date', 'currency_id', 'category_id', 'categorization_status'])
            ->where('user_id', Auth::id())
            ->where('account_id', $account->id)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($income) {
                return [
                    'id' => $income->id,
                    'description' => $income->description,
                    'amount' => (float) $income->amount,
                    'type' => 'income',
                    'date' => $income->transaction_date,
                    'transaction_date' => $income->transaction_date,
                    'currency_id' => $income->currency_id,
                    'currency' => $income->currency ? $income->currency->name : 'IDR',
                    'currency_symbol' => $income->currency ? $income->currency->symbol : 'Rp',
                    'category' => $income->category ? $income->category->name : null,
                    'category_icon' => $income->category ? $income->category->icon : 'dollar-sign',
                    'categorization_status' => $income->categorization_status ?? 'completed',
                    'created_at' => $income->created_at,
                ];
            });

        $recentOutcomes = Outcome::with(['category', 'currency'])
            ->select(['id', 'user_id', 'account_id', 'description', 'amount', 'transaction_date', 'currency_id', 'category_id', 'categorization_status'])
            ->where('user_id', Auth::id())
            ->where('account_id', $account->id)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($outcome) {
                return [
                    'id' => $outcome->id,
                    'description' => $outcome->description,
                    'amount' => (float) $outcome->amount,
                    'type' => 'outcome',
                    'date' => $outcome->transaction_date,
                    'transaction_date' => $outcome->transaction_date,
                    'currency_id' => $outcome->currency_id,
                    'currency' => $outcome->currency ? $outcome->currency->name : 'IDR',
                    'currency_symbol' => $outcome->currency ? $outcome->currency->symbol : 'Rp',
                    'category' => $outcome->category ? $outcome->category->name : null,
                    'category_icon' => $outcome->category ? $outcome->category->icon : 'dollar-sign',
                    'categorization_status' => $outcome->categorization_status ?? 'completed',
                    'created_at' => $outcome->created_at,
                ];
            });

        // Combine and sort recent transactions by date
        $recentTransactions = $recentIncomes->concat($recentOutcomes)
            ->sortByDesc('transaction_date')
            ->take(10)
            ->values();

        // Calculate overall stats (for all time) using optimized method
        $overallStats = $this->getOptimizedPeriodStats(null, null, $defaultCurrencyId, 'all', $account->id);
        $overallIncome = (float) ($overallStats['income'] ?? 0);
        $overallOutcome = (float) ($overallStats['outcome'] ?? 0);
        $overallBalance = $overallIncome - $overallOutcome;

        $monthlyData = $this->getMonthlyData($year, $defaultCurrencyId, $account->id) ?? [];
        $yearlyData = $this->getYearlyData($defaultCurrencyId, $account->id) ?? [];

        // Calculate daily averages for the default currency and period
        $dailyAverages = $this->calculateDailyAverages($year, $month, $mode, $defaultCurrencyId, $account->id);

        return Inertia::render('Dashboard', [
            'account' => $account,
            'stats' => array_merge([
                'overall_income' => $overallIncome,
                'overall_outcome' => $overallOutcome,
                'overall_balance' => $overallBalance,
                'showCurrencyTabs' => ($year > 2024 || ($year == 2024 && $month >= 4)),
                'dailyIncomeAverage' => $dailyAverages['dailyIncomeAverage'],
                'dailyOutcomeAverage' => $dailyAverages['dailyOutcomeAverage'],
            ], $stats, $exchangeRates),
            'chartData' => [
                'monthly' => $monthlyData,
                'yearly' => $yearlyData,
            ],
            'recentTransactions' => $recentTransactions,
            'filters' => [
                'year' => (int) $year,
                'month' => (int) $month,
                'mode' => $mode,
            ],
            'currencyBreakdown' => $currencyBreakdown,
            'availableCurrencies' => $availableCurrencies,
        ]);
    }
}
