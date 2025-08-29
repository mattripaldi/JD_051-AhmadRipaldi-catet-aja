<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ModalController;

Route::get('/', function () {
    return redirect()->route('login');
})->name('welcome');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Modal routes
    Route::get('modal/sample', [ModalController::class, 'sample'])->name('modal.sample');
    Route::post('modal/sample', [ModalController::class, 'storeSample'])->name('modal.sample.store');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
