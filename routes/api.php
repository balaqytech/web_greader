<?php

use App\Http\Controllers\Api\V1\BotContactController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ProgramController;
use App\Http\Controllers\Api\V1\ReadingAssessmentFormSubmissionController;
use App\Support\Api\FasihServiceAbilities as Ability;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public catalogs
|--------------------------------------------------------------------------
| Branch and program reference data is public (used to render chatbot menus)
| and carries no PII, so it stays open — throttled per IP since these requests
| carry no service token.
*/
Route::middleware('throttle:api-public')->group(function () {
    Route::apiResource('branches', BranchController::class)->only(['index', 'show']);
    Route::apiResource('programs', ProgramController::class)->only(['index', 'show']);
});

/*
|--------------------------------------------------------------------------
| Fasih service account
|--------------------------------------------------------------------------
| Everything else requires a real Sanctum personal-access token owned by the
| `service_fasih` principal (auth:sanctum + fasih.service), plus the exact
| ability for the operation. Reads, writes, and payment operations get
| isolated per-token rate buckets.
*/
Route::middleware(['auth:sanctum', 'fasih.service'])->group(function () {
    // Reads — 60/min per token.
    Route::middleware('throttle:api-read')->group(function () {
        Route::get('leads/counts', [LeadController::class, 'counts'])
            ->middleware('abilities:'.Ability::LeadsRead)
            ->name('leads.counts');

        Route::get('leads', [LeadController::class, 'index'])
            ->middleware('abilities:'.Ability::LeadsRead)
            ->name('leads.index');

        Route::get('bot-contacts', [BotContactController::class, 'index'])
            ->middleware('abilities:'.Ability::BotContactsManage)
            ->name('bot-contacts.index');

        Route::get('bot-contacts/{botContact}', [BotContactController::class, 'show'])
            ->middleware('abilities:'.Ability::BotContactsManage)
            ->name('bot-contacts.show');

        Route::get('reading-assessment-form-submissions', [ReadingAssessmentFormSubmissionController::class, 'index'])
            ->middleware('abilities:'.Ability::AssessmentsManage)
            ->name('reading-assessment-form-submissions.index');

        Route::get('reading-assessment-form-submissions/{submission}', [ReadingAssessmentFormSubmissionController::class, 'show'])
            ->middleware('abilities:'.Ability::AssessmentsManage)
            ->name('reading-assessment-form-submissions.show');
    });

    // Writes — 10/min per token.
    Route::middleware('throttle:api-write')->group(function () {
        Route::post('leads', [LeadController::class, 'store'])
            ->middleware('abilities:'.Ability::LeadsCreate)
            ->name('leads.store');

        Route::post('bot-contacts', [BotContactController::class, 'store'])
            ->middleware('abilities:'.Ability::BotContactsManage)
            ->name('bot-contacts.store');

        Route::post('reading-assessment-form-submissions', [ReadingAssessmentFormSubmissionController::class, 'store'])
            ->middleware('abilities:'.Ability::AssessmentsManage)
            ->name('reading-assessment-form-submissions.store');
    });

    // Chatbot/guardian-facing payment initiation and receipt upload. Cash is never offered
    // here (see PaymentMethod::isAvailableToChatbot()) — it is staff-only. 5/min per token.
    Route::middleware('throttle:payments')
        ->prefix('payments')
        ->name('api.payments.')
        ->group(function () {
            Route::post('thawani', [PaymentController::class, 'initiateThawani'])
                ->middleware('abilities:'.Ability::PaymentsInitiate)
                ->name('initiate-thawani');

            Route::post('bank-transfer', [PaymentController::class, 'initiateBankTransfer'])
                ->middleware('abilities:'.Ability::PaymentsInitiate)
                ->name('initiate-bank-transfer');

            Route::post('{payment}/receipt', [PaymentController::class, 'uploadReceipt'])
                ->middleware('abilities:'.Ability::PaymentsUploadReceipt)
                ->name('upload-receipt');
        });
});
