<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\ProgramController;

Route::get('branches', [BranchController::class, 'index']);
Route::get('programs', [ProgramController::class, 'index']);
