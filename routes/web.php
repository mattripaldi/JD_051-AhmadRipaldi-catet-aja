<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AiChatController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\OutcomeController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\DashboardController;

Route::get('/', function () {
    if (Auth::check()) {
        $user = Auth::user();

        if ($user->current_account_id && $user->currentAccount) {
            return redirect()->route('account.dashboard', ['account' => $user->current_account_id]);
        }

        if ($user->accounts()->exists()) {
            return redirect()->route('account.index');
        }
    }

    return redirect()->route('login');
})->name('welcome');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('ai-chat/send', [AiChatController::class, 'sendMessage'])->name('ai-chat.send');
    Route::get('ai-chat/starters', [AiChatController::class, 'getStarters'])->name('ai-chat.starters');
    
    Route::prefix('account')->group(function () {
        Route::get('/', [AccountController::class, 'index'])->name('account.index');
        Route::get('/create', [AccountController::class, 'create'])->name('account.create');
        Route::get('/{account}/edit', [AccountController::class, 'edit'])->middleware('ensure.account.ownership')->name('account.edit');
        Route::get('/{account}/delete', [AccountController::class, 'confirmDelete'])->middleware('ensure.account.ownership')->name('account.confirm-delete');

        Route::post('/', [AccountController::class, 'store'])->name('account.store');
        Route::put('/{account}', [AccountController::class, 'update'])->middleware('ensure.account.ownership')->name('account.update');
        Route::delete('/{account}', [AccountController::class, 'destroy'])->middleware('ensure.account.ownership')->name('account.destroy');
        Route::post('/{account}/select', [AccountController::class, 'select'])->middleware('ensure.account.ownership')->name('account.select');

        Route::prefix('{account}')->middleware('ensure.account.ownership')->group(function () {

            Route::get('/dashboard', DashboardController::class)->name('account.dashboard');

            // Income Routes
            Route::get('/income', [IncomeController::class, 'index'])->name('income.index');
            Route::get('/income/create', [IncomeController::class, 'create'])->name('income.create');
            Route::get('/income/{income}/edit', [IncomeController::class, 'edit'])->name('income.edit');
            Route::get('/income/{income}/delete', [IncomeController::class, 'confirmDelete'])->name('income.confirm-delete');
            Route::post('/income', [IncomeController::class, 'store'])->name('income.store');
            Route::put('/income/{income}', [IncomeController::class, 'update'])->name('income.update');
            Route::delete('/income/{income}', [IncomeController::class, 'destroy'])->name('income.destroy');

            // Outcome Routes
            Route::get('/outcome', [OutcomeController::class, 'index'])->name('outcome.index');
            Route::get('/outcome/create', [OutcomeController::class, 'create'])->name('outcome.create');
            Route::get('/outcome/{outcome}/edit', [OutcomeController::class, 'edit'])->name('outcome.edit');
            Route::get('/outcome/{outcome}/delete', [OutcomeController::class, 'confirmDelete'])->name('outcome.confirm-delete');
            Route::post('/outcome', [OutcomeController::class, 'store'])->name('outcome.store');
            Route::put('/outcome/{outcome}', [OutcomeController::class, 'update'])->name('outcome.update');
            Route::delete('/outcome/{outcome}', [OutcomeController::class, 'destroy'])->name('outcome.destroy');

            // Currency management routes
            Route::get('/currency', [CurrencyController::class, 'index'])->name('currency.index');
            Route::get('/currency/create', [CurrencyController::class, 'create'])->name('currency.create');
            Route::get('/currency/{currency}/delete', [CurrencyController::class, 'confirmDelete'])->name('currency.confirm-delete');
            Route::post('/currency', [CurrencyController::class, 'store'])->name('currency.store');
            Route::delete('/currency/{currency}', [CurrencyController::class, 'destroy'])->name('currency.destroy');
        });
    });

});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
