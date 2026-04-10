<?php

use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\ProgramController;
use App\Http\Controllers\Api\V1\RegisterController;
use Illuminate\Support\Facades\Route;

Route::get('branches', [BranchController::class, 'index']);
Route::get('programs', [ProgramController::class, 'index']);
Route::post('register', RegisterController::class);

Route::apiResource('leads', LeadController::class)->only(['index', 'store']);
