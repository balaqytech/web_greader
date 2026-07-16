<?php

use App\Http\Controllers\Api\V1\BotContactController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ProgramController;
use App\Http\Controllers\Api\V1\ReadingAssessmentFormSubmissionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('branches', BranchController::class)->only(['index', 'show']);

Route::apiResource('programs', ProgramController::class)->only(['index', 'show']);

Route::get('leads/counts', [LeadController::class, 'counts']);
Route::get('leads', [LeadController::class, 'index'])->middleware('auth:sanctum')->name('leads.index');
Route::post('leads', [LeadController::class, 'store'])->name('leads.store');

Route::apiResource(
    'reading-assessment-form-submissions',
    ReadingAssessmentFormSubmissionController::class
)
    ->parameters([
        'reading-assessment-form-submissions' => 'submission',
    ])
    ->only(['index', 'store', 'show']);

Route::apiResource('bot-contacts', BotContactController::class)->only(['index', 'store', 'show']);

// Service-token only: chatbot/guardian-facing payment initiation and receipt upload. Cash is
// never offered here (see PaymentMethod::isAvailableToChatbot()) — it is staff-only.
Route::middleware(['auth:sanctum', 'throttle:payments'])
    ->prefix('payments')
    ->name('api.payments.')
    ->group(function () {
        Route::post('thawani', [PaymentController::class, 'initiateThawani'])
            ->middleware('abilities:payments:initiate')
            ->name('initiate-thawani');

        Route::post('bank-transfer', [PaymentController::class, 'initiateBankTransfer'])
            ->middleware('abilities:payments:initiate')
            ->name('initiate-bank-transfer');

        Route::post('{payment}/receipt', [PaymentController::class, 'uploadReceipt'])
            ->middleware('abilities:payments:upload-receipt')
            ->name('upload-receipt');
    });
