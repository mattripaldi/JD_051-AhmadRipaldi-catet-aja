<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ModalController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\DashboardController;

Route::get('/', function () {
    return redirect()->route('login');
})->name('welcome');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('account')->group(function () {
        Route::get('/', [AccountController::class, 'index'])->name('account.index');
        Route::get('/create', [AccountController::class, 'create'])->name('account.create');
        Route::get('/{account}/edit', [AccountController::class, 'edit'])->name('account.edit');
        Route::get('/{account}/delete', [AccountController::class, 'confirmDelete'])->name('account.confirm-delete');

        Route::post('/', [AccountController::class, 'store'])->name('account.store');
        Route::put('/{account}', [AccountController::class, 'update'])->name('account.update');
        Route::delete('/{account}', [AccountController::class, 'destroy'])->name('account.destroy');
        Route::post('/{account}/select', [AccountController::class, 'select'])->name('account.select');

        Route::prefix('{account}')->group(function () {
            Route::get('/dashboard', DashboardController::class)->name('account.dashboard');
        });
    });

    Route::get('modal/sample/{account}', [ModalController::class, 'sample'])->name('modal.sample');
    Route::post('modal/sample/{account}', [ModalController::class, 'storeSample'])->name('modal.sample.store');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
