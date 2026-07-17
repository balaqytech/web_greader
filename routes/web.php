<?php

use App\Http\Controllers\ApplicationDocumentDownloadController;
use App\Http\Controllers\ContractSigningController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Controllers\PaymentReturnController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

/*
 * Authenticated, authorized download of a single document file version. The parent document's
 * view policy (own-branch) is the gate; the file itself is streamed from the private disk and
 * has no public URL. Any version — current or historical — downloads through here.
 */
Route::get('/application-documents/files/{file}/download', ApplicationDocumentDownloadController::class)
    ->middleware('auth')
    ->name('application-documents.files.download');

Route::get('/contract/{token}', [ContractSigningController::class, 'show'])->name('contract.show');
Route::post('/contract/{token}', [ContractSigningController::class, 'sign'])->name('contract.sign');

Route::get('/payments/{payment}/receipt', PaymentReceiptController::class)
    ->middleware('auth')
    ->name('payments.receipt.download');

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
