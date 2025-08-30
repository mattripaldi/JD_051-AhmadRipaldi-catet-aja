<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
    public function index(Request $request, $accountId)
    {
        $currencies = Currency::where('user_id', Auth::id())
            ->where('account_id', $accountId)
            ->orderBy('name')
            ->get()
            ->map(function ($currency) {
                return [
                    'id' => $currency->id,
                    'name' => $currency->name,
                    'symbol' => $currency->symbol,
                    'exchange_rate' => (float) $currency->exchange_rate,
                ];
            });

        return response()->json($currencies);
    }

    /**
     * Store a new currency for the user
     */
    public function store(Request $request, $accountId)
    {
        $request->validate([
            'name' => 'required|string|max:3|unique:currencies,name,NULL,id,user_id,' . Auth::id() . ',account_id,' . $accountId,
            'symbol' => 'required|string|max:10',
            'exchange_rate' => 'required|numeric|min:0',
        ]);

        $currency = Currency::create([
            'user_id' => Auth::id(),
            'account_id' => $accountId,
            'name' => strtoupper($request->name),
            'symbol' => $request->symbol,
            'exchange_rate' => $request->exchange_rate,
        ]);

        return response()->json([
            'id' => $currency->id,
            'name' => $currency->name,
            'symbol' => $currency->symbol,
            'exchange_rate' => (float) $currency->exchange_rate,
        ], 201);
    }

    /**
     * Update an existing currency
     */
    public function update(Request $request, $accountId, Currency $currency)
    {
        // Ensure the currency belongs to the authenticated user and account
        if ($currency->user_id !== Auth::id() || $currency->account_id != $accountId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:3|unique:currencies,name,' . $currency->id . ',id,user_id,' . Auth::id() . ',account_id,' . $accountId,
            'symbol' => 'required|string|max:10',
            'exchange_rate' => 'required|numeric|min:0',
        ]);

        $currency->update([
            'name' => strtoupper($request->name),
            'symbol' => $request->symbol,
            'exchange_rate' => $request->exchange_rate,
        ]);

        return response()->json([
            'id' => $currency->id,
            'name' => $currency->name,
            'symbol' => $currency->symbol,
            'exchange_rate' => (float) $currency->exchange_rate,
            'created_at' => $currency->created_at,
            'updated_at' => $currency->updated_at,
        ]);
    }

    /**
     * Delete a currency
     */
    public function destroy($accountId, Currency $currency)
    {
        // Ensure the currency belongs to the authenticated user and account
        if ($currency->user_id !== Auth::id() || $currency->account_id != $accountId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Prevent deletion of IDR currency
        if ($currency->name === 'IDR') {
            return response()->json(['error' => 'Cannot delete IDR currency'], 400);
        }

        $currency->delete();

        return response()->json(['message' => 'Currency deleted successfully']);
    }

    /**
     * Get supported currencies list (for reference)
     */
    public function supportedCurrencies($accountId)
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
     * Ensure user has default IDR currency for specific account
     */
    public function ensureDefaultCurrency($accountId)
    {
        $existingIdr = Currency::where('user_id', Auth::id())
            ->where('account_id', $accountId)
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
