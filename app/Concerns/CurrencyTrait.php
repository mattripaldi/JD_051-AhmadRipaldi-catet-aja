<?php

namespace App\Concerns;

use Illuminate\Support\Facades\Auth;
use App\Models\Currency;

trait CurrencyTrait
{
    /**
     * Get the default currency ID (IDR) for the authenticated user
     *
     * @return int|null
     */
    protected function getDefaultCurrencyId($accountId)
    {
        // Handle both model instances and IDs
        $accountIdValue = is_object($accountId) ? $accountId->id : $accountId;

        return Currency::where('user_id', Auth::id())
            ->where('account_id', $accountIdValue)
            ->where('name', 'IDR')
            ->value('id');
    }
}
