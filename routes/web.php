<?php

use App\Http\Controllers\ContractSigningController;
use App\Http\Controllers\PaymentReturnController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

Route::get('/contract/{token}', [ContractSigningController::class, 'show'])->name('contract.show');
Route::post('/contract/{token}', [ContractSigningController::class, 'sign'])->name('contract.sign');

/*
 * Where Thawani returns the guardian's browser after checkout. Public by necessity — they
 * arrive from a hosted page with no session — so the payment is addressed by its unguessable
 * ULID, and the `outcome` segment is a hint only: the controller never reads it as a result,
 * it asks the provider. Rate-limited because it triggers an outbound provider call.
 */
Route::get('/payments/{payment}/return/{outcome}', PaymentReturnController::class)
    ->middleware('throttle:20,1')
    ->whereIn('outcome', ['success', 'cancel'])
    ->name('payments.return');

require __DIR__.'/settings.php';
