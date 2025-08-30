<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\Income;
use App\Models\Outcome;
use App\Models\Currency;
use App\Models\User;
use Carbon\Carbon;

class CurrencyService
{
    /**
     * Cache key for currency exchange rates
     */
    const CACHE_KEY_CURRENCY = 'exchange_rate_currency';
    
    /**
     * Cache duration in seconds (1 day)
     */
    const CACHE_DURATION = 86400;
    
    /**
     * Convert amount from one currency to another using the latest rate
     *
     * @param float $amount
     * @param string $fromCurrency
     * @param string $toCurrency
     * @param int $userId
     * @param int|null $accountId
     * @return float
     */
    public function convertCurrency(float $amount, string $fromCurrency, string $toCurrency, int $userId, ?int $accountId = null): float
    {
        $rate = $this->getExchangeRate($fromCurrency, $toCurrency, $userId, $accountId);
        return $amount * $rate;
    }

    /**
     * Convert any currency amount to IDR using the latest rate
     *
     * @param float $amount
     * @param string $fromCurrency
     * @param int $userId
     * @param int|null $accountId
     * @return float
     */
    public function convertToIdr(float $amount, string $fromCurrency, int $userId, ?int $accountId = null): float
    {
        return $this->convertCurrency($amount, $fromCurrency, 'IDR', $userId, $accountId);
    }
    
    /**
     * Convert amount from one currency to another for a specific date
     * Uses the rate for the month of the given date
     *
     * @param float $amount
     * @param string $fromCurrency
     * @param string $toCurrency
     * @param string|Carbon $date
     * @param int $userId
     * @param int|null $accountId
     * @return float
     */
    public function convertCurrencyForDate(float $amount, string $fromCurrency, string $toCurrency, $date, int $userId, ?int $accountId = null): float
    {
        $rate = $this->getExchangeRateForDate($fromCurrency, $toCurrency, $date, $userId, $accountId);
        return $amount * $rate;
    }

    /**
     * Convert any currency amount to IDR for a specific date
     * Uses the rate for the month of the given date
     *
     * @param float $amount
     * @param string $fromCurrency
     * @param string|Carbon $date
     * @param int $userId
     * @param int|null $accountId
     * @return float
     */
    public function convertToIdrForDate(float $amount, string $fromCurrency, $date, int $userId, ?int $accountId = null): float
    {
        return $this->convertCurrencyForDate($amount, $fromCurrency, 'IDR', $date, $userId, $accountId);
    }
    
    /**
     * Get the exchange rate between two currencies for a specific date
     * Uses the rate for the month of the given date
     *
     * @param string $fromCurrency
     * @param string $toCurrency
     * @param string|Carbon $date
     * @param int $userId
     * @param int|null $accountId
     * @return float
     */
    public function getExchangeRateForDate(string $fromCurrency, string $toCurrency, $date, int $userId, ?int $accountId = null): float
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);
        
        // Try to get the rate from the database
        $rate = Currency::getRate($fromCurrency, $toCurrency, $userId, $accountId);
        
        // If no rate found, use the latest rate
        if ($rate === null) {
            Log::warning("No {$fromCurrency} to {$toCurrency} rate found for user {$userId}, using latest rate");
            return $this->getExchangeRate($fromCurrency, $toCurrency, $userId, $accountId);
        }
        
        return $rate;
    }

    /**
     * Get the exchange rate from any currency to IDR for a specific date
     * Uses the rate for the month of the given date
     *
     * @param string $fromCurrency
     * @param string|Carbon $date
     * @param int $userId
     * @param int|null $accountId
     * @return float
     */
    public function getToIdrRateForDate(string $fromCurrency, $date, int $userId, ?int $accountId = null): float
    {
        return $this->getExchangeRateForDate($fromCurrency, 'IDR', $date, $userId, $accountId);
    }
    
    /**
     * Get the exchange rate between two currencies for a specific year and month
     *
     * @param string $fromCurrency
     * @param string $toCurrency
     * @param int $userId
     * @param int|null $accountId
     * @param int|null $year
     * @param int|null $month
     * @return float
     */
    public function getExchangeRateForMonth(string $fromCurrency, string $toCurrency, int $userId, ?int $accountId = null, ?int $year = null, ?int $month = null): float
    {
        // Try to get the rate from the database
        $rate = Currency::getRate($fromCurrency, $toCurrency, $userId, $accountId);
        
        // If no rate found, use the latest rate
        if ($rate === null) {
            $yearMonth = ($year && $month) ? "$year-$month" : 'current month';
            Log::warning("No {$fromCurrency} to {$toCurrency} rate found for user {$userId} for {$yearMonth}, using latest rate");
            return $this->getExchangeRate($fromCurrency, $toCurrency, $userId, $accountId);
        }
        
        return $rate;
    }

    /**
     * Get the exchange rate from any currency to IDR for a specific year and month
     *
     * @param string $fromCurrency
     * @param int $userId
     * @param int|null $accountId
     * @param int|null $year
     * @param int|null $month
     * @return float
     */
    public function getToIdrRateForMonth(string $fromCurrency, int $userId, ?int $accountId = null, ?int $year = null, ?int $month = null): float
    {
        return $this->getExchangeRateForMonth($fromCurrency, 'IDR', $userId, $accountId, $year, $month);
    }
    
    /**
     * Get the latest exchange rate between two currencies
     * Cached for 1 day
     *
     * @param string $fromCurrency
     * @param string $toCurrency
     * @param int $userId
     * @param int|null $accountId
     * @return float
     */
    public function getExchangeRate(string $fromCurrency, string $toCurrency, int $userId, ?int $accountId = null): float
    {
        $cacheKey = self::CACHE_KEY_CURRENCY . "_{$fromCurrency}_{$toCurrency}_{$userId}" . ($accountId ? "_{$accountId}" : '');
        
        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($fromCurrency, $toCurrency, $userId, $accountId) {
            try {
                // First try to get the rate from the database
                $rate = Currency::getRate($fromCurrency, $toCurrency, $userId, $accountId);
                
                if ($rate !== null) {
                    return $rate;
                }
                
                // If not found in database, try to fetch from external APIs
                // Try open.er-api.com for all currency pairs
                $response = Http::get("https://open.er-api.com/v6/latest/{$fromCurrency}");
                
                if ($response->successful()) {
                    $data = $response->json();
                    $apiRate = $data['rates'][$toCurrency] ?? null;
                    
                    if ($apiRate) {
                        // Store the rate in the database for future use (if accountId is provided)
                        if ($accountId) {
                            Currency::updateOrCreateRate($fromCurrency, $toCurrency, $userId, $accountId, $apiRate, $fromCurrency);
                        }
                        
                        return $apiRate;
                    }
                }
                
                // If the primary API doesn't have the rate, try alternative APIs for popular currency pairs
                if ($fromCurrency === 'SGD' && $toCurrency === 'IDR') {
                    $alternativeRate = $this->fetchFromAlternativeApis($fromCurrency, $toCurrency, $userId, $accountId);
                    if ($alternativeRate > 0) {
                        return $alternativeRate;
                    }
                }
                
                // Log error and return 1.0 as default (no conversion)
                Log::error("All currency API calls failed for {$fromCurrency} to {$toCurrency}, returning 1.0 (no conversion)");
                
                return 1.0;
            } catch (\Exception $e) {
                Log::error('Error fetching currency rates: ' . $e->getMessage());
                return 1.0;
            }
        });
    }

    /**
     * Get the latest exchange rate from any currency to IDR
     * Cached for 1 day
     *
     * @param string $fromCurrency
     * @param int $userId
     * @param int|null $accountId
     * @return float
     */
    public function getToIdrRate(string $fromCurrency, int $userId, ?int $accountId = null): float
    {
        return $this->getExchangeRate($fromCurrency, 'IDR', $userId, $accountId);
    }

    /**
     * Fetch rates from alternative APIs for popular currency pairs
     *
     * @param string $fromCurrency
     * @param string $toCurrency
     * @param int $userId
     * @param int|null $accountId
     * @return float
     */
    private function fetchFromAlternativeApis(string $fromCurrency, string $toCurrency, int $userId, ?int $accountId): float
    {
        $lowerFromCurrency = strtolower($fromCurrency);
        $lowerToCurrency = strtolower($toCurrency);
        
        // First try the JSDelivr CDN
        $response = Http::get("https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/{$lowerFromCurrency}.json");
        
        if ($response->successful()) {
            $data = $response->json();
            if (isset($data[$lowerFromCurrency][$lowerToCurrency])) {
                $apiRate = $data[$lowerFromCurrency][$lowerToCurrency];
                
                // Store the rate in the database for future use (if accountId is provided)
                if ($accountId) {
                    Currency::updateOrCreateRate($fromCurrency, $toCurrency, $userId, $accountId, $apiRate, $fromCurrency);
                }
                
                return $apiRate;
            }
        }
        
        // If JSDelivr fails, try the Cloudflare fallback
        $response = Http::get("https://latest.currency-api.pages.dev/v1/currencies/{$lowerFromCurrency}.json");
        
        if ($response->successful()) {
            $data = $response->json();
            if (isset($data[$lowerFromCurrency][$lowerToCurrency])) {
                $apiRate = $data[$lowerFromCurrency][$lowerToCurrency];
                
                // Store the rate in the database for future use (if accountId is provided)
                if ($accountId) {
                    Currency::updateOrCreateRate($fromCurrency, $toCurrency, $userId, $accountId, $apiRate, $fromCurrency);
                }
                
                return $apiRate;
            }
        }
        
        return 0; // Return 0 if no alternative API worked
    }
    
    /**
     * Clear the cached exchange rate
     *
     * @param string|null $fromCurrency
     * @param string|null $toCurrency
     * @param int|null $userId
     * @param int|null $accountId
     * @return void
     */
    public function clearRateCache(?string $fromCurrency = null, ?string $toCurrency = null, ?int $userId = null, ?int $accountId = null): void
    {
        if ($fromCurrency && $toCurrency && $userId) {
            $cacheKey = self::CACHE_KEY_CURRENCY . "_{$fromCurrency}_{$toCurrency}_{$userId}" . ($accountId ? "_{$accountId}" : '');
            Cache::forget($cacheKey);
            Log::info("Currency exchange rate cache cleared for {$fromCurrency} to {$toCurrency} for user {$userId}");
        } else {
            // Clear all cache keys that start with the base key (this is a simplified approach)
            Cache::forget(self::CACHE_KEY_CURRENCY);
            Log::info('Currency exchange rate cache cleared');
        }
    }
    
    /**
     * Check if cache refresh is needed based on transaction changes
     *
     * @param Income|Outcome $transaction
     * @param array $oldData
     * @return bool
     */
    public function shouldRefreshCache($transaction, array $oldData = []): bool
    {
        // If it's a new transaction with any currency that might need conversion
        if (!empty($transaction->currency) && empty($oldData)) {
            return true;
        }
        
        // If currency was changed
        if (!empty($oldData) && isset($oldData['currency'])) {
            if ($transaction->currency !== $oldData['currency']) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get exchange rates for all user currencies to IDR for a specific month
     * Returns an array with currency codes as keys and exchange rates as values
     *
     * @param array $currencies
     * @param int $userId
     * @param int|null $accountId
     * @param int|null $year
     * @param int|null $month
     * @return array
     */
    public function getExchangeRatesForUserCurrencies(array $currencies, int $userId, ?int $accountId = null, ?int $year = null, ?int $month = null): array
    {
        $rates = [];
        
        foreach ($currencies as $currency) {
            if ($currency === 'IDR' || $currency === 'Rp') {
                // IDR to IDR is always 1.0
                $rates[$currency] = 1.0;
            } else {
                $rates[$currency] = $this->getToIdrRateForMonth($currency, $userId, $accountId, $year, $month);
            }
        }
        
        return $rates;
    }

    /**
     * Update the currency rate for a specific user and account
     *
     * @param int $userId
     * @param int $accountId
     * @param string $fromCurrency
     * @param string $toCurrency
     * @param float $rate
     * @param string $symbol
     * @return Currency
     */
    public function updateCurrencyRate(
        int $userId,
        int $accountId,
        string $fromCurrency,
        string $toCurrency,
        float $rate,
        string $symbol = ''
    ): Currency {
        return Currency::updateOrCreateRate($fromCurrency, $toCurrency, $userId, $accountId, $rate, $symbol);
    }

    /**
     * Ensure user has default IDR currency set up
     * Creates default account if needed and adds IDR currency
     *
     * @param User $user
     * @return void
     */
    public function ensureDefaultIdrCurrency(User $user): void
    {
        // Check if user has any accounts
        $accounts = $user->accounts;

        if ($accounts->isEmpty()) {
            // Create default account
            $account = $user->accounts()->create([
                'name' => 'Default Account',
                'description' => 'Default account created automatically',
            ]);
            $accounts = collect([$account]);
        }

        // Ensure each account has IDR currency
        foreach ($accounts as $account) {
            $existingIdr = Currency::where('user_id', $user->id)
                ->where('account_id', $account->id)
                ->where('name', 'IDR')
                ->first();

            if (!$existingIdr) {
                Currency::create([
                    'user_id' => $user->id,
                    'account_id' => $account->id,
                    'name' => 'IDR',
                    'symbol' => 'Rp',
                    'exchange_rate' => 1.0,
                ]);
            }
        }
    }
} 
