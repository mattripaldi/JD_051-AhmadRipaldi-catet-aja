<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountController extends Controller
{
    public function index()
    {
        $accounts = Auth::user()->accounts()->latest()->get();

        return Inertia::render('Account/Index', [
            'accounts' => $accounts
        ]);
    }

    public function create()
    {
        return Inertia::modal('Account/Create')
                    ->baseRoute('account.index');
    }

    public function edit(Account $account)
    {
        return Inertia::modal('Account/Edit', [
            'account' => $account
        ])->baseRoute('account.index');
    }

    public function confirmDelete(Account $account)
    {
        return Inertia::modal('Account/Delete', [
            'account' => $account
        ])->baseRoute('account.index');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $validated['user_id'] = Auth::id();

        Account::create($validated);

        return redirect()->back()->with('success', 'Account created successfully!');
    }

    public function update(Request $request, Account $account)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $account->update($validated);

        return redirect()->back()->with('success', 'Account updated successfully!');
    }

    public function destroy(Account $account)
    {
        $account->delete();

        return redirect()
            ->route('account.index')
            ->with('success', 'Account deleted successfully!');
    }

    public function select(Account $account)
    {
        // Update the user's current account
        Auth::user()->update(['current_account_id' => $account->id]);

        // Redirect to the account dashboard
        return redirect()->route('account.dashboard', ['account' => $account->id]);
    }
}
