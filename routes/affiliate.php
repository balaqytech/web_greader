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

    Route::get('/download-qr-code', function () {
        $affiliate = auth('affiliate')->user();
        $affiliate_url = 'https://g-reader-school.com/?ref=' . ($affiliate->code ?? '#');
        $qr_code = QrCode::format('png')
            ->size(2000)
            ->merge('public/logo.png', 0.2)
            ->margin(2)
            ->text($affiliate_url);

        if (str_starts_with((string) $qr_code, '<img')) {
            preg_match('/src="data:image\/png;base64,([^"]+)"/', (string) $qr_code, $matches);

            if (! isset($matches[1])) {
                abort(500, 'QR code PNG data could not be extracted.');
            }

            $qr_code = base64_decode($matches[1]);
        }

        return response($qr_code, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="qrcode.png"',
        ]);
    })->name('affiliate.download-qr-code');

    Route::livewire('profile', 'pages::affiliate.profile')->name('affiliate.profile.edit');
    Route::put('profile', AffiliateProfileController::class)->name('affiliate.profile.update');

    Route::view('password', 'pages::affiliate.password')->name('affiliate.password.edit');
    Route::put('password', AffiliatePasswordController::class)->name('affiliate.password.update');

    Route::post('logout', AffiliateLogoutController::class)->name('affiliate.logout');
});
