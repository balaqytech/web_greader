<?php

use App\Http\Controllers\Api\V1\BotContactController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\ProgramController;
use App\Http\Controllers\Api\V1\ReadingAssessmentFormSubmissionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('branches', BranchController::class)->only(['index', 'show']);

Route::apiResource('programs', ProgramController::class)->only(['index', 'show']);

Route::apiResource('leads', LeadController::class)->only(['index', 'store']);
Route::post('leads/{lead}/transition', [LeadController::class, 'transition']);

Route::apiResource(
    'reading-assessment-form-submissions',
    ReadingAssessmentFormSubmissionController::class
)
    ->parameters([
        'reading-assessment-form-submissions' => 'submission'
    ])
    ->only(['index', 'store', 'show']);

Route::apiResource('bot-contacts', BotContactController::class)->only(['index', 'store', 'show']);
