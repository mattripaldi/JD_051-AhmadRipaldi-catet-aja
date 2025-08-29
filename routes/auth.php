<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    // Traditional registration removed - using magic link authentication instead
    // Route::get('register', [RegisteredUserController::class, 'create'])
    //     ->name('register');
    // Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');

    // Social Authentication Routes
    Route::get('google/redirect', [SocialAuthController::class, 'redirectToGoogle'])
        ->name('google.redirect');

    Route::get('google/callback', [SocialAuthController::class, 'handleGoogleCallback'])
        ->name('google.callback');

    // Magic Link Authentication Routes
    Route::get('magic-link', [MagicLinkController::class, 'create'])
        ->name('magic-link.create');

    Route::post('magic-link', [MagicLinkController::class, 'sendMagicLink'])
        ->name('magic-link.send');

    Route::get('magic-link/authenticate', [MagicLinkController::class, 'authenticate'])
        ->name('magic-link.authenticate')
        ->middleware('signed');
});

Route::middleware('auth')->group(function () {
    // Email verification removed - magic link authentication automatically verifies emails
    // Route::get('verify-email', EmailVerificationPromptController::class)
    //     ->name('verification.notice');
    // Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
    //     ->middleware(['signed', 'throttle:6,1'])
    //     ->name('verification.verify');
    // Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
    //     ->middleware('throttle:6,1')
    //     ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
