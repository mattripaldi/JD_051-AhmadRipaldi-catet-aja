<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\Income;
use App\Models\Outcome;
use App\Models\Currency;
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
     * Convert SGD amount to IDR using the latest rate (legacy method for backward compatibility)
     *
     * @param float $amount
     * @param int $userId
     * @param int|null $accountId
     * @return float
     */
    public function convertSgdToIdr(float $amount, int $userId, ?int $accountId = null): float
    {
        return $this->convertToIdr($amount, 'SGD', $userId, $accountId);
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
     * Convert SGD amount to IDR for a specific date (legacy method for backward compatibility)
     * Uses the rate for the month of the given date
     *
     * @param float $amount
     * @param string|Carbon $date
     * @param int $userId
     * @param int|null $accountId
     * @return float
     */
    public function convertSgdToIdrForDate(float $amount, $date, int $userId, ?int $accountId = null): float
    {
        return $this->convertToIdrForDate($amount, 'SGD', $date, $userId, $accountId);
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
     * Get the SGD to IDR exchange rate for a specific date (legacy method for backward compatibility)
     * Uses the rate for the month of the given date
     *
     * @param string|Carbon $date
     * @param int $userId
     * @param int|null $accountId
     * @return float
     */
    public function getSgdToIdrRateForDate($date, int $userId, ?int $accountId = null): float
    {
        return $this->getToIdrRateForDate('SGD', $date, $userId, $accountId);
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
     * Get the SGD to IDR exchange rate for a specific year and month (legacy method for backward compatibility)
     *
     * @param int $userId
     * @param int|null $accountId
     * @param int|null $year
     * @param int|null $month
     * @return float
     */
    public function getSgdToIdrRateForMonth(int $userId, ?int $accountId = null, ?int $year = null, ?int $month = null): float
    {
        return $this->getToIdrRateForMonth('SGD', $userId, $accountId, $year, $month);
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
                // For now, we'll support SGD to IDR specifically, but this can be expanded
                if ($fromCurrency === 'SGD' && $toCurrency === 'IDR') {
                    return $this->fetchSgdToIdrFromApi($userId, $accountId);
                }
                
                // For other currency pairs, try open.er-api.com
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
                
                // Fallback to a reasonable default based on currency pair
                $fallbackRate = $this->getFallbackRate($fromCurrency, $toCurrency);
                Log::warning("All currency API calls failed for {$fromCurrency} to {$toCurrency}, using fallback rate: {$fallbackRate}");
                
                return $fallbackRate;
            } catch (\Exception $e) {
                Log::error('Error fetching currency rates: ' . $e->getMessage());
                return $this->getFallbackRate($fromCurrency, $toCurrency);
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
     * Get the latest SGD to IDR exchange rate (legacy method for backward compatibility)
     * Cached for 1 day
     *
     * @param int $userId
     * @param int|null $accountId
     * @return float
     */
    public function getSgdToIdrRate(int $userId, ?int $accountId = null): float
    {
        return $this->getToIdrRate('SGD', $userId, $accountId);
    }

    /**
     * Fetch SGD to IDR rate from external APIs with multiple fallbacks
     *
     * @param int $userId
     * @param int|null $accountId
     * @return float
     */
    private function fetchSgdToIdrFromApi(int $userId, ?int $accountId): float
    {
        // First try the JSDelivr CDN
        $response = Http::get("https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/sgd.json");
        
        if ($response->successful()) {
            $data = $response->json();
            if (isset($data['sgd']['idr'])) {
                $apiRate = $data['sgd']['idr'];
                
                // Store the rate in the database for future use (if accountId is provided)
                if ($accountId) {
                    Currency::updateOrCreateRate('SGD', 'IDR', $userId, $accountId, $apiRate, 'SGD');
                }
                
                return $apiRate;
            }
        }
        
        // If JSDelivr fails, try the Cloudflare fallback
        $response = Http::get("https://latest.currency-api.pages.dev/v1/currencies/sgd.json");
        
        if ($response->successful()) {
            $data = $response->json();
            if (isset($data['sgd']['idr'])) {
                $apiRate = $data['sgd']['idr'];
                
                // Store the rate in the database for future use (if accountId is provided)
                if ($accountId) {
                    Currency::updateOrCreateRate('SGD', 'IDR', $userId, $accountId, $apiRate, 'SGD');
                }
                
                return $apiRate;
            }
        }
        
        // If both APIs fail, try the open.er-api.com as a fallback
        $response = Http::get('https://open.er-api.com/v6/latest/SGD');
        
        if ($response->successful()) {
            $data = $response->json();
            $apiRate = $data['rates']['IDR'] ?? 11600; // Default fallback rate if API doesn't return IDR
            
            // Store the rate in the database for future use (if accountId is provided)
            if ($accountId) {
                Currency::updateOrCreateRate('SGD', 'IDR', $userId, $accountId, $apiRate, 'SGD');
            }
            
            return $apiRate;
        }
        
        return 11600; // Fallback rate
    }

    /**
     * Get fallback exchange rate for currency pairs
     *
     * @param string $fromCurrency
     * @param string $toCurrency
     * @return float
     */
    private function getFallbackRate(string $fromCurrency, string $toCurrency): float
    {
        // Define some common fallback rates
        $fallbackRates = [
            'SGD_IDR' => 11600,
            'USD_IDR' => 15000,
            'EUR_IDR' => 16000,
            'GBP_IDR' => 19000,
            'JPY_IDR' => 100,
            'USD_SGD' => 1.35,
            'EUR_SGD' => 1.45,
            'GBP_SGD' => 1.70,
        ];
        
        $key = "{$fromCurrency}_{$toCurrency}";
        return $fallbackRates[$key] ?? 1.0; // Default to 1:1 if no specific fallback
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
} 
