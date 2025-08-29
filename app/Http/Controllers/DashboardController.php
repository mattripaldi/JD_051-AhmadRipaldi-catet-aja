<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __invoke(Account $account)
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

        return Inertia::render('Dashboard', [
            'account' => $account
        ]);
    }
}
