<?php

use App\Http\Controllers\Affiliate\AffiliateLoginController;
use App\Http\Controllers\Affiliate\AffiliateLogoutController;
use App\Http\Controllers\Affiliate\AffiliatePasswordController;
use App\Http\Controllers\Affiliate\AffiliateProfileController;
use App\Http\Controllers\Affiliate\AffiliateRegisterController;
use Akira\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Route;

Route::middleware('guest:affiliate')->group(function () {
    Route::view('login', 'pages::affiliate.auth.login')->name('affiliate.login');
    Route::post('login', AffiliateLoginController::class)->name('affiliate.login.store');

    Route::view('register', 'pages::affiliate.auth.register')->name('affiliate.register');
    Route::post('register', AffiliateRegisterController::class)->name('affiliate.register.store');
});

Route::middleware('auth:affiliate')->group(function () {
    Route::livewire('dashboard', 'pages::affiliate.dashboard')->name('affiliate.dashboard');

    Route::livewire('profile', 'pages::affiliate.profile')->name('affiliate.profile.edit');
    Route::put('profile', AffiliateProfileController::class)->name('affiliate.profile.update');

    Route::view('password', 'pages::affiliate.password')->name('affiliate.password.edit');
    Route::put('password', AffiliatePasswordController::class)->name('affiliate.password.update');

    Route::post('logout', AffiliateLogoutController::class)->name('affiliate.logout');
});
