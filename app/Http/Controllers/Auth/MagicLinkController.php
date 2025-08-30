<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CurrencyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MagicLinkController extends Controller
{
    /**
     * Show the magic link request form
     */
    public function create(): Response
    {
        return Inertia::render('Auth/MagicLink');
    }

    /**
     * Send magic link to user
     */
    public function sendMagicLink(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Find user by email
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            // Create user if they don't exist (magic link registration)
            $user = User::create([
                'name' => explode('@', $request->email)[0], // Use email prefix as name
                'email' => $request->email,
                'password' => Hash::make(Str::random(32)), // Random password since they won't use it
                'email_verified_at' => now(),
            ]);

            // Ensure new user has default IDR currency
            $currencyService = app(CurrencyService::class);
            $currencyService->ensureDefaultIdrCurrency($user);
        }

        // Send magic link using custom notification
        $user->notify(new \App\Notifications\MagicLinkNotification(Password::createToken($user)));

        return back()->with('status', 'Magic link sent! Please check your email.');
    }

    /**
     * Handle magic link callback for automatic login
     */
    public function authenticate(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
        ]);

        // Find user
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return redirect()->route('login')->withErrors([
                'email' => 'No account found with this email address.'
            ]);
        }

        // Verify the token using Laravel's password broker
        $credentials = [
            'email' => $request->email,
            'token' => $request->token,
            'password' => '', // Not used in token verification
        ];

        if (!Password::tokenExists($user, $request->token)) {
            return redirect()->route('login')->withErrors([
                'token' => 'This magic link is invalid or has expired.'
            ]);
        }

        // Delete the token after successful verification
        Password::deleteToken($user);

        // Log the user in
        Auth::login($user);

                // Check if user has a current account and it exists
        if ($user->current_account_id && $user->currentAccount) {
            return redirect(route('account.dashboard', ['account' => $user->current_account_id]));
        }

        // Otherwise, redirect to account selection
        return redirect(route('account.index'));
    }
}
