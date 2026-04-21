<?php

use App\Http\Controllers\Affiliate\AffiliateLoginController;
use App\Http\Controllers\Affiliate\AffiliateLogoutController;
use App\Http\Controllers\Affiliate\AffiliatePasswordController;
use App\Http\Controllers\Affiliate\AffiliateProfileController;
use App\Http\Controllers\Affiliate\AffiliateRegisterController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest:affiliate')->group(function () {
    Route::get('login', [AffiliateLoginController::class, 'create'])->name('affiliate.login');
    Route::post('login', [AffiliateLoginController::class, 'store'])->name('affiliate.login.store');

    Route::get('register', [AffiliateRegisterController::class, 'create'])->name('affiliate.register');
    Route::post('register', [AffiliateRegisterController::class, 'store'])->name('affiliate.register.store');
});

Route::middleware('auth:affiliate')->group(function () {
    Route::view('dashboard', 'affiliate.dashboard')->name('affiliate.dashboard');

    Route::get('profile', [AffiliateProfileController::class, 'edit'])->name('affiliate.profile.edit');
    Route::put('profile', [AffiliateProfileController::class, 'update'])->name('affiliate.profile.update');

    Route::get('password', [AffiliatePasswordController::class, 'edit'])->name('affiliate.password.edit');
    Route::put('password', [AffiliatePasswordController::class, 'update'])->name('affiliate.password.update');

    Route::post('logout', AffiliateLogoutController::class)->name('affiliate.logout');
});
