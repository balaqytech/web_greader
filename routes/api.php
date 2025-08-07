<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\ProgramController;
use App\Http\Controllers\Api\V1\RegisterController;

Route::get('branches', [BranchController::class, 'index']);
Route::get('programs', [ProgramController::class, 'index']);
Route::post('register', RegisterController::class);
