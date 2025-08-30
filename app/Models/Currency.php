<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Currency extends Model
{
    protected $fillable = [
        'user_id',
        'account_id',
        'name',
        'symbol',
        'exchange_rate',
    ];

    protected $attributes = [
        'account_id' => null,
    ];

    protected $casts = [
        'exchange_rate' => 'decimal:2',
    ];

    /**
     * Get the user that owns the currency.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the account that owns the currency.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }    /**
     * Get exchange rate for specific currencies and user
     *
     * @param string $fromCurrency
     * @param string $toCurrency
     * @param int $userId
     * @param int|null $accountId
     * @return float|null
     */
    public static function getRate(string $fromCurrency, string $toCurrency, int $userId, ?int $accountId = null): ?float
    {
        $query = static::where('user_id', $userId)
            ->where('name', $fromCurrency);

        if ($accountId) {
            // Handle both model instances and IDs
            $accountIdValue = is_object($accountId) ? $accountId->id : $accountId;
            $query->where(function ($q) use ($accountIdValue) {
                $q->where('account_id', $accountIdValue)
                  ->orWhereNull('account_id');
            });
        } else {
            $query->whereNull('account_id');
        }

        $currency = $query->first();

        return $currency ? (float) $currency->exchange_rate : null;
    }

    /**
     * Update or create currency rate for user
     *
     * @param string $fromCurrency
     * @param string $toCurrency
     * @param int $userId
     * @param int|null $accountId
     * @param float $rate
     * @param string $symbol
     * @return static
     */
    public static function updateOrCreateRate(
        string $fromCurrency,
        string $toCurrency,
        int $userId,
        ?int $accountId = null,
        float $rate,
        string $symbol = ''
    ): static {
        // Handle both model instances and IDs
        $accountIdValue = is_object($accountId) ? $accountId->id : $accountId;

        return static::updateOrCreate(
            [
                'user_id' => $userId,
                'account_id' => $accountIdValue,
                'name' => $fromCurrency,
            ],
            [
                'symbol' => $symbol,
                'exchange_rate' => $rate,
            ]
        );
    }
}
