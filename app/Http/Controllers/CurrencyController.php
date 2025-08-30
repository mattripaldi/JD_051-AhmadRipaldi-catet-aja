<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Models\Currency;
use App\Services\CurrencyService;

class CurrencyController extends Controller
{
    protected $currencyService;

    public function __construct(CurrencyService $currencyService)
    {
        $this->currencyService = $currencyService;
    }

    /**
     * Get all currencies for the authenticated user
     */
    public function index($accountId)
    {
        $currencies = Auth::user()->currencies()
                        ->orderBy('name')
                        ->get();

        return Inertia::render('settings/currency/Index', [
            'currencies' => $currencies,
        ]);
    }

    /**
     * Show create currency modal
     */
    public function create(Request $request, $accountId)
    {
        return Inertia::modal('settings/currency/Create')
                    ->baseRoute('currency.index', ['account' => $accountId]);
    }

    /**
     * Store a new currency for the user
     */
    public function store(Request $request, $accountId)
    {
        $request->validate([
            'name' => 'required|string|max:3|unique:currencies,name,NULL,id,user_id,' . Auth::id(),
            'symbol' => 'required|string|max:10',
        ]);

        $currencyCode = strtoupper($request->name);

        // Validate currency code against API
        if (!$this->currencyService->validateCurrencyCode($currencyCode)) {
            return redirect()->back()->withErrors(['name' => 'Kode Mata Uang Tidak Ditemukan']);
        }

        // Automatically fetch fresh exchange rate from API (bypass cache for new currencies)
        $exchangeRate = $this->currencyService->getFreshExchangeRate($currencyCode, 'IDR');

        Log::info("Creating currency {$currencyCode} with exchange rate: {$exchangeRate}");

        // Handle both model instances and IDs
        $accountIdValue = is_object($accountId) ? $accountId->id : $accountId;

        Currency::create([
            'user_id' => Auth::id(),
            'account_id' => $accountIdValue,
            'name' => $currencyCode,
            'symbol' => $request->symbol,
            'exchange_rate' => $exchangeRate,
        ]);

        return redirect()->back()->with('success', 'Currency created successfully!');
    }



    /**
     * Show delete currency confirmation modal
     */
    public function confirmDelete(Request $request, $accountId, Currency $currency)
    {
        return Inertia::modal('settings/currency/Delete', [
            'currency' => $currency
        ])->baseRoute('currency.index', ['account' => $accountId]);
    }

    /**
     * Delete a currency
     */
    public function destroy(Request $request, $accountId, Currency $currency)
    {
        // Prevent deletion of IDR currency
        if ($currency->name === 'IDR') {
            return redirect()->back()->withErrors(['error' => 'Cannot delete IDR currency']);
        }

        $currency->delete();

        return redirect()->route('currency.index', ['account' => $accountId])->with('success', 'Currency deleted successfully!');
    }
}
