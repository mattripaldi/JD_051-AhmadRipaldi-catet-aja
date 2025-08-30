<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
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

        Currency::create([
            'user_id' => Auth::id(),
            'account_id' => $accountId,
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
        // Ensure the currency belongs to the authenticated user and is user-level
        if ($currency->user_id !== Auth::id() || $currency->account_id !== (int) $accountId) {
            abort(403);
        }

        return Inertia::modal('settings/currency/Delete', [
            'currency' => $currency
        ])->baseRoute('currency.index', ['account' => $accountId]);
    }

    /**
     * Delete a currency
     */
    public function destroy(Request $request, $accountId, Currency $currency)
    {
        // Ensure the currency belongs to the authenticated user and is user-level
        if ($currency->user_id !== Auth::id() || $currency->account_id !== (int) $accountId) {
            abort(403);
        }

        // Prevent deletion of IDR currency
        if ($currency->name === 'IDR') {
            return redirect()->back()->withErrors(['error' => 'Cannot delete IDR currency']);
        }

        $currency->delete();

        return redirect()->route('currency.index', ['account' => $accountId])->with('success', 'Currency deleted successfully!');
    }

    /**
     * Get supported currencies list (for reference)
     */
    public function supportedCurrencies(Request $request, $accountId)
    {
        $supportedCurrencies = [
            ['code' => 'IDR', 'name' => 'Indonesian Rupiah', 'symbol' => 'Rp'],
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$'],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€'],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£'],
            ['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥'],
            ['code' => 'SGD', 'name' => 'Singapore Dollar', 'symbol' => 'S$'],
            ['code' => 'AUD', 'name' => 'Australian Dollar', 'symbol' => 'A$'],
            ['code' => 'CAD', 'name' => 'Canadian Dollar', 'symbol' => 'C$'],
            ['code' => 'CHF', 'name' => 'Swiss Franc', 'symbol' => 'CHF'],
            ['code' => 'CNY', 'name' => 'Chinese Yuan', 'symbol' => '¥'],
        ];

        return response()->json($supportedCurrencies);
    }

    /**
     * Ensure user has default IDR currency
     */
    public function ensureDefaultCurrency(Request $request, $accountId)
    {
        $existingIdr = Currency::where('user_id', Auth::id())
            ->where('name', 'IDR')
            ->first();

        if (!$existingIdr) {
            Currency::create([
                'user_id' => Auth::id(),
                'account_id' => $accountId,
                'name' => 'IDR',
                'symbol' => 'Rp',
                'exchange_rate' => 1.0,
            ]);
        }

        return response()->json(['message' => 'Default currency ensured']);
    }
}
