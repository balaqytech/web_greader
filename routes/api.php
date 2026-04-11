<?php

use App\Http\Controllers\Api\V1\BranchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('branches', BranchController::class)->only(['index', 'show']);
