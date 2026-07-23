<?php

use App\Http\Controllers\Api\AuthController;
use App\Rest\Controllers\SeancesController;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::get('/me', [AuthController::class, 'me']);
});

Rest::resource('seances', SeancesController::class)->middleware('auth:api');
