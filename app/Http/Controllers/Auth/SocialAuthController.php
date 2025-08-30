<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Inertia\Inertia;
use Inertia\Response;

class SocialAuthController extends Controller
{
    /**
     * Redirect to Google OAuth
     */
    public function redirectToGoogle(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle Google OAuth callback
     */
    public function handleGoogleCallback(): \Illuminate\Http\RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            $user = User::findOrCreateFromProvider('google', $googleUser);

            Auth::login($user);

                        // Check if user has a current account and it exists
            if ($user->current_account_id && $user->currentAccount) {
                return redirect(route('account.dashboard', ['account' => $user->current_account_id]));
            }

            // Otherwise, redirect to account selection
            return redirect(route('account.index'));
        } catch (\Exception $e) {
            return redirect()->route('login')->withErrors([
                'social' => 'Unable to authenticate with Google. Please try again.'
            ]);
        }
    }
}
