<?php

use App\Http\Controllers\ContractSigningController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

Route::get('/contract/{token}', [ContractSigningController::class, 'show'])->name('contract.show');
Route::post('/contract/{token}', [ContractSigningController::class, 'sign'])->name('contract.sign');

require __DIR__.'/settings.php';
