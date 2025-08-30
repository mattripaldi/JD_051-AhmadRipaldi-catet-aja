<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Account;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountOwnership
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get the account parameter from the route
        $accountParam = $request->route('account');

        // If no account parameter, continue (this middleware might be applied to routes that don't have {account})
        if (!$accountParam) {
            return $next($request);
        }

        $account = null;

        try {
            // Check if the parameter is already a model instance (route model binding)
            if ($accountParam instanceof Account) {
                $account = $accountParam;
            } elseif (is_object($accountParam) && method_exists($accountParam, 'getKey')) {
                // Handle other model instances that might be passed
                $account = $accountParam;
            } elseif (is_numeric($accountParam)) {
                // Try to find the account by ID
                $account = Account::find($accountParam);
            } elseif (is_string($accountParam) && is_numeric($accountParam)) {
                // Handle string numeric values
                $account = Account::find((int) $accountParam);
            }
        } catch (\Exception $e) {
            // If there's any issue with account resolution, redirect to account index
            return redirect()->route('account.index')->with('error', 'Invalid account parameter.');
        }

        // If account doesn't exist, redirect to account index
        if (!$account || !($account instanceof Account)) {
            return redirect()->route('account.index')->with('error', 'Account not found.');
        }

        // Check if the account belongs to the authenticated user
        if ($account->user_id !== Auth::id()) {
            // Redirect to user's current account if they have one, otherwise to account index
            $user = Auth::user();
            if ($user && $user->current_account_id && $user->currentAccount) {
                return redirect()->route('account.dashboard', ['account' => $user->current_account_id])
                    ->with('error', 'You do not have permission to access this account.');
            }

            return redirect()->route('account.index')
                ->with('error', 'You do not have permission to access this account.');
        }

        return $next($request);
    }
}
